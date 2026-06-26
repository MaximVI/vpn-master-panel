<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => isset($_SERVER['HTTPS']),
    ]);
}

try {
    require_once __DIR__ . '/../app/Core/Database.php';
    $db = \App\Core\Database::getInstance();
    $db->initTables();
} catch (Exception $e) {
    http_response_code(500);
    die("Database error: " . $e->getMessage());
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Выход
if ($uri === '/logout') {
    session_destroy();
    header('Location: /login');
    exit;
}

// Логин
if ($uri === '/login' || $uri === '/') {
    if (isset($_SESSION['user_id'])) {
        header('Location: /dashboard');
        exit;
    }
    
    $error = '';
    if ($method === 'POST') {
        $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$_POST['email'] ?? '']);
        if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: /dashboard');
            exit;
        }
        $error = 'Неверный email или пароль';
    }
    
    require __DIR__ . '/../templates/login.php';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Дашборд
if ($uri === '/dashboard') {
    $servers = $db->fetchAll("SELECT * FROM servers ORDER BY created_at DESC");
    $clientsCount = $db->fetchColumn("SELECT COUNT(*) FROM clients");
    $activeServers = $db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'active'");
    
    require __DIR__ . '/../templates/dashboard.php';
    exit;
}

// Страница клиентов
if (preg_match('#^/server/(\d+)/clients$#', $uri, $m)) {
    $serverId = (int)$m[1];
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
    if (!$server) { http_response_code(404); die("Сервер не найден"); }
    
    $clients = $db->fetchAll("SELECT * FROM clients WHERE server_id = ? ORDER BY created_at DESC", [$serverId]);
    require __DIR__ . '/../templates/clients.php';
    exit;
}

// API: Добавление сервера
if ($uri === '/api/add-server' && $method === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF error']); exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip_address'] ?? '');
    $password = $_POST['auth_value'] ?? '';
    
    if (empty($name) || empty($ip) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все поля']); exit;
    }
    
    $key = hash('sha256', 'vpn-master-secret-key', true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    
    $id = $db->insert('servers', [
        'name' => $name,
        'ip_address' => $ip,
        'ssh_port' => (int)($_POST['ssh_port'] ?? 22),
        'ssh_username' => $_POST['ssh_username'] ?? 'root',
        'auth_type' => 'password',
        'auth_value' => base64_encode($iv . $encrypted),
        'status' => 'pending',
    ]);
    
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

// API: Тест сервера
if ($uri === '/api/test-server' && $method === 'POST') {
    header('Content-Type: application/json');
    
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [(int)($_POST['id'] ?? 0)]);
    if (!$server) { echo json_encode(['success' => false, 'message' => 'Сервер не найден']); exit; }
    
    try {
        $key = hash('sha256', 'vpn-master-secret-key', true);
        $data = base64_decode($server['auth_value']);
        $iv = substr($data, 0, 16);
        $password = openssl_decrypt(substr($data, 16), 'aes-256-cbc', $key, 0, $iv);
        
        $conn = @ssh2_connect($server['ip_address'], (int)$server['ssh_port']);
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Нет соединения']); exit;
        }
        if (!@ssh2_auth_password($conn, $server['ssh_username'], $password)) {
            echo json_encode(['success' => false, 'message' => 'Неверный пароль']); exit;
        }
        
        $stream = ssh2_exec($conn, 'cat /etc/os-release | head -1 && wg --version 2>/dev/null || echo "no-wg"');
        stream_set_blocking($stream, true);
        $info = stream_get_contents($stream);
        
        $db->update('servers', ['status' => 'active', 'last_check' => date('Y-m-d H:i:s')], ['id' => $server['id']]);
        
        echo json_encode(['success' => true, 'message' => 'OK', 'info' => trim($info)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// API: Удаление сервера
if ($uri === '/api/delete-server' && $method === 'POST') {
    header('Content-Type: application/json');
    $db->delete('servers', ['id' => (int)($_POST['id'] ?? 0)]);
    echo json_encode(['success' => true]);
    exit;
}

// API: Создание клиента
if ($uri === '/api/create-client' && $method === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF error']); exit;
    }
    
    $serverId = (int)($_POST['server_id'] ?? 0);
    $clientName = trim($_POST['client_name'] ?? '');
    
    if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $clientName)) {
        echo json_encode(['success' => false, 'message' => 'Имя: 3-32 символа']); exit;
    }
    
    // Генерируем ключи
    $privateKey = trim(shell_exec('wg genkey 2>/dev/null') ?? '');
    if (empty($privateKey)) $privateKey = base64_encode(random_bytes(32));
    
    $publicKey = trim(shell_exec("echo '$privateKey' | wg pubkey 2>/dev/null") ?? '');
    if (empty($publicKey)) {
        $keyPair = sodium_crypto_box_keypair();
        $publicKey = base64_encode(sodium_crypto_box_publickey($keyPair));
        $privateKey = base64_encode(sodium_crypto_box_secretkey($keyPair));
    }
    
    // Свободный IP
    $used = $db->fetchAll("SELECT ip_address FROM clients WHERE server_id = ?", [$serverId]);
    $usedIPs = array_column($used, 'ip_address');
    $nextIP = null;
    for ($i = 10; $i <= 254; $i++) {
        $ip = "10.8.0.$i";
        if (!in_array($ip, $usedIPs)) { $nextIP = $ip; break; }
    }
    if (!$nextIP) { echo json_encode(['success' => false, 'message' => 'Нет IP']); exit; }
    
    $clientId = $db->insert('clients', [
        'server_id' => $serverId,
        'name' => $clientName,
        'public_key' => $publicKey,
        'private_key' => $privateKey,
        'ip_address' => $nextIP,
        'enabled' => 1,
    ]);
    
    echo json_encode([
        'success' => true,
        'client' => ['id' => $clientId, 'name' => $clientName, 'ip_address' => $nextIP]
    ]);
    exit;
}

// Скачивание конфига
if (preg_match('#^/client/(\d+)/config$#', $uri, $m)) {
    $client = $db->fetch("SELECT c.*, s.ip_address as srv_ip, s.name as srv_name FROM clients c JOIN servers s ON c.server_id = s.id WHERE c.id = ?", [(int)$m[1]]);
    if (!$client) { http_response_code(404); die("Не найден"); }
    
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$client['server_id']]);
    
    // Пробуем получить ключ сервера
    $serverPubKey = '';
    try {
        $key = hash('sha256', 'vpn-master-secret-key', true);
        $data = base64_decode($server['auth_value']);
        $iv = substr($data, 0, 16);
        $pass = openssl_decrypt(substr($data, 16), 'aes-256-cbc', $key, 0, $iv);
        
        $conn = @ssh2_connect($server['ip_address'], (int)$server['ssh_port']);
        if ($conn && @ssh2_auth_password($conn, $server['ssh_username'], $pass)) {
            $s = ssh2_exec($conn, 'cat /etc/wireguard/server_public.key 2>/dev/null || wg pubkey < /etc/wireguard/server_private.key 2>/dev/null || echo ""');
            stream_set_blocking($s, true);
            $serverPubKey = trim(stream_get_contents($s));
        }
    } catch (Exception $e) {}
    
    if (empty($serverPubKey)) $serverPubKey = '(добавьте сюда публичный ключ сервера)';
    
    $config = "# VPN Client: {$client['name']}\n";
    $config .= "# Server: {$client['srv_name']}\n";
    $config .= "# Created: " . date('Y-m-d H:i:s') . "\n\n";
    $config .= "[Interface]\n";
    $config .= "PrivateKey = {$client['private_key']}\n";
    $config .= "Address = {$client['ip_address']}/32\n";
    $config .= "DNS = 1.1.1.1, 8.8.8.8\n\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = {$serverPubKey}\n";
    $config .= "Endpoint = {$server['ip_address']}:443\n";
    $config .= "AllowedIPs = 0.0.0.0/0\n";
    $config .= "PersistentKeepalive = 25\n";
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $client['name'] . '.conf"');
    echo $config;
    exit;
}

// QR-код
if (preg_match('#^/client/(\d+)/qr$#', $uri, $m)) {
    $client = $db->fetch("SELECT c.*, s.ip_address as srv_ip FROM clients c JOIN servers s ON c.server_id = s.id WHERE c.id = ?", [(int)$m[1]]);
    if (!$client) { http_response_code(404); die("Не найден"); }
    
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$client['server_id']]);
    
    // Ключ сервера
    $serverPubKey = '';
    try {
        $key = hash('sha256', 'vpn-master-secret-key', true);
        $data = base64_decode($server['auth_value']);
        $iv = substr($data, 0, 16);
        $pass = openssl_decrypt(substr($data, 16), 'aes-256-cbc', $key, 0, $iv);
        $conn = @ssh2_connect($server['ip_address'], (int)$server['ssh_port']);
        if ($conn && @ssh2_auth_password($conn, $server['ssh_username'], $pass)) {
            $s = ssh2_exec($conn, 'cat /etc/wireguard/server_public.key 2>/dev/null || echo ""');
            stream_set_blocking($s, true);
            $serverPubKey = trim(stream_get_contents($s));
        }
    } catch (Exception $e) {}
    
    $config = "[Interface]\nPrivateKey = {$client['private_key']}\nAddress = {$client['ip_address']}/32\nDNS = 1.1.1.1\n\n[Peer]\nPublicKey = {$serverPubKey}\nEndpoint = {$server['ip_address']}:443\nAllowedIPs = 0.0.0.0/0\n";
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'qr_');
    file_put_contents($tmpFile, $config);
    
    header('Content-Type: image/png');
    passthru("qrencode -t PNG -o - < $tmpFile 2>/dev/null || echo 'QR error'");
    unlink($tmpFile);
    exit;
}

// API: Удаление клиента
if ($uri === '/api/delete-client' && $method === 'POST') {
    header('Content-Type: application/json');
    $db->delete('clients', ['id' => (int)($_POST['id'] ?? 0)]);
    echo json_encode(['success' => true]);
    exit;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>404</h1><p><a href="/dashboard">На главную</a></p></body></html>';
