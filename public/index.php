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
            $_SESSION['user_name'] = $user['name'];
            header('Location: /dashboard');
            exit;
        }
        $error = 'Неверный email или пароль';
    }
    
    require __DIR__ . '/../templates/login.php';
    exit;
}

// Дашборд
if ($uri === '/dashboard') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    
    $servers = $db->fetchAll("SELECT * FROM servers ORDER BY created_at DESC");
    $clientsCount = $db->fetchColumn("SELECT COUNT(*) FROM clients");
    $activeServers = $db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'active'");
    
    require __DIR__ . '/../templates/dashboard.php';
    exit;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>404</h1><p><a href="/dashboard">На главную</a></p></body></html>';
