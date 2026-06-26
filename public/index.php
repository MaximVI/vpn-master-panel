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

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Выход
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/logout') {
    session_destroy();
    header('Location: /login');
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Логин
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
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
            $_SESSION['user_name'] = $user['name'];
            header('Location: /dashboard');
            exit;
        }
        $error = 'Неверный email или пароль';
    }
    
    require __DIR__ . '/../templates/login.php';
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Проверка авторизации для остальных страниц
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Дашборд
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/dashboard') {
    $servers = $db->fetchAll("SELECT * FROM servers ORDER BY created_at DESC");
    $clientsCount = $db->fetchColumn("SELECT COUNT(*) FROM clients");
    $activeServers = $db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'active'");
    
    require __DIR__ . '/../templates/dashboard.php';
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Страница клиентов сервера
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (preg_match('#^/server/(\d+)/clients$#', $uri, $m)) {
    $serverId = (int)$m[1];
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
    
    if (!$server) {
        http_response_code(404);
        die("Сервер не найден");
    }
    
    $clients = $db->fetchAll(
        "SELECT * FROM clients WHERE server_id = ? ORDER BY created_at DESC",
        [$serverId]
    );
    
    require __DIR__ . '/../templates/clients.php';
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// API: Добавление сервера
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/api/add-server' && $method === 'POST') {
    header('Content-Type: application/json');
    
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF error']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip_address'] ?? '');
    $port = (int)($_POST['ssh_port'] ?? 22);
    $username = trim($_POST['ssh_username'] ?? 'root');
    $password = $_POST['auth_value'] ?? '';
    
    if (empty($name) || empty($ip) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все поля']);
        exit;
    }
    
    // Шифруем пароль
    $key = hash('sha256', 'vpn-master-secret-key', true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    $encValue = base64_encode($iv . $encrypted);
    
    $id = $db->insert('servers', [
        'name' => $name,
        'ip_address' => $ip,
        'ssh_port' => $port,
        'ssh_username' => $username,
        'auth_type' => 'password',
        'auth_value' => $encValue,
        'status' => 'pending',
    ]);
    
    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Сервер добавлен']);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// API: Проверка соединения с сервером
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/api/test-server' && $method === 'POST') {
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$id]);
    
    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        exit;
    }
    
    try {
        // Расшифровываем пароль
        $key = hash('sha256', 'vpn-master-secret-key', true);
        $data = base64_decode($server['auth_value']);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $password = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        // Пробуем SSH
        $connection = @ssh2_connect($server['ip_address'], (int)$server['ssh_port']);
        
        if (!$connection) {
            echo json_encode(['success' => false, 'message' => 'Не удалось подключиться']);
            exit;
        }
        
        if (@ssh2_auth_password($connection, $server['ssh_username'], $password)) {
            // Проверяем систему
            $stream = ssh2_exec($connection, 'cat /etc/os-release | head -1 && which wg && uptime -p');
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            
            $db->update('servers', 
                ['status' => 'active', 'last_check' => date('Y-m-d H:i:s')],
                ['id' => $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Соединение установлено',
                'info' => trim($output)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Неверный пароль']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// API: Удаление сервера
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/api/delete-server' && $method === 'POST') {
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    $db->delete('servers', ['id' => $id]);
    
    echo json_encode(['success' => true, 'message' => 'Сервер удалён']);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// API: Создание клиента
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($uri === '/api/create-client' && $method === 'POST') {
    header('Content-Type: application/json');
    
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF error']);
        exit;
    }
    
    $serverId = (int)($_POST['server_id'] ?? 0);
    $clientName = trim($_POST['client_name'] ?? '');
    
    if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $clientName)) {
        echo json_encode(['success' => false, 'message' => 'Имя: 3-32 символа (буквы, цифры, -, _)']);
        exit;
    }
    
    // Генерируем ключи
    $privateKey = trim(shell_exec('wg genkey 2>/dev/null') ?? base64_encode(random_bytes(32)));
    $publicKey = trim(shell_exec("echo '$privateKey' | wg pubkey 2>/dev/null") ?? '');
    
    if (empty($publicKey)) {
        $publicKey = base64_encode(hash('sha256', base64_decode($privateKey), true));
    }
    
    // Ищем свободный IP
    $existingIPs = $db->fetchAll("SELECT ip_address FROM clients WHERE server_id = ?", [$serverId]);
    $usedIPs = array_column($existingIPs, 'ip_address');
    
    $nextIP = null;
    for ($i = 10; $i <= 254; $i++) {
        $ip = "10.8.0.$i";
        if (!in_array($ip, $usedIPs)) {
            $nextIP = $ip;
            break;
        }
    }
    
    if (!$nextIP) {
        echo json_encode(['success' => false, 'message' => 'Нет свободных IP']);
        exit;
    }
    
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
        'client' => [
            'id' => $clientId,
            'name' => $clientName,
            'ip_address' => $nextIP,
            'public_key' => substr($publicKey, 0, 20) . '...',
        ]
    ]);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Скачивание конфига клиента
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (preg_match('#^/client/(\d+)/config$#', $uri, $m)) {
    $clientId = (int)$m[1];
    $client = $db->fetch("SELECT c.*, s.ip_address as server_ip FROM clients c JOIN servers s ON c.server_id = s.id WHERE c.id = ?", [$clientId]);
    
    if (!$client) {
        http_response_code(404);
        die("Клиент не найден");
    }
    
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$client['server_id']]);
    
    // Пробуем получить публичный ключ сервера
    $serverPubKey = '';
    try {
        $key = hash('sha256', 'vpn-master-secret-key', true);
        $data = base64_decode($server['auth_value']);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $password = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        $conn = @ssh2_connect($server['ip_address'], (int)$server['ssh_port']);
        if ($conn && @ssh2_auth_password($conn, $server['ssh_username'], $password)) {
            $stream = ssh2_exec($conn, 'cat /etc/wireguard/server_public.key 2>/dev/null || wg show wg0 public-key 2>/dev/null');
            stream_set_blocking($stream, true);
            $serverPubKey = trim(stream_get_contents($stream));
        }
    } catch (Exception $e) {
        $serverPubKey = 'SERVER_PUBLIC_KEY';
    }
    
    $config = "# VPN Client: {$client['name']}\n";
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
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $client['name'] . '.conf"');
    echo $config;
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// QR-код для клиента
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (preg_match('#^/client/(\d+)/qr$#', $uri, $m)) {
    $clientId = (int)$m[1];
    $client = $db->fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);
    
    if (!$client) {
        http_response_code(404);
        die("Клиент не найден");
    }
    
    // Генерируем конфиг для QR
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$client['server_id']]);
    
    $config = "[Interface]\nPrivateKey = {$client['private_key']}\nAddress = {$client['ip_address']}/32\nDNS = 1.1.1.1\n\n[Peer]\nPublicKey = SERVER_KEY\nEndpoint = {$server['ip_address']}:443\nAllowedIPs = 0.0.0.0/0\n";
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'qr_');
    file_put_contents($tmpFile, $config);
    
    header('Content-Type: image/png');
    passthru("qrencode -t PNG -o - < $tmpFile 2>/dev/null");
    unlink($tmpFile);
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// 404
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>404</h1><p><a href="/dashboard">На главную</a></p></body></html>';
