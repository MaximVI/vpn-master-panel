<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Services\AuthService;

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => isset($_SERVER['HTTPS']),
]);

try {
    $db = Database::getInstance();
    $db->initTables();
} catch (Exception $e) {
    http_response_code(500);
    die("Database error. Check logs.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Роутинг
if ($uri === '/login') {
    $error = null;
    
    if ($method === 'POST') {
        $auth = new AuthService();
        $result = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
        
        if ($result['success']) {
            header('Location: /');
            exit;
        }
        $error = $result['message'];
    }
    
    // Простая форма логина
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VPN Panel - Вход</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
            }
            h1 { text-align: center; margin-bottom: 30px; color: #2d3748; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; color: #4a5568; font-weight: 500; }
            input {
                width: 100%;
                padding: 12px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 16px;
                transition: border-color 0.2s;
            }
            input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            }
            button {
                width: 100%;
                padding: 14px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }
            button:hover { background: #5a67d8; }
            .error {
                background: #fed7d7;
                color: #c53030;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 VPN Panel</h1>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required autofocus placeholder="admin@vpn.local">
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($uri === '/logout') {
    $auth = new AuthService();
    $auth->logout();
    header('Location: /login');
    exit;
}

// Проверка авторизации
$auth = new AuthService();
if (!$auth->isLoggedIn()) {
    header('Location: /login');
    exit;
}

// Главная страница
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Panel - Дашборд</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f7fafc; }
        .header { background: #2d3748; color: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header a { color: #a0aec0; text-decoration: none; margin-left: 16px; }
        .header a:hover { color: white; }
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .card h2 { margin-bottom: 16px; color: #2d3748; }
        .welcome { font-size: 18px; color: #4a5568; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-primary { background: #4299e1; color: white; }
        .btn-primary:hover { background: #3182ce; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 VPN Panel</h1>
        <div>
            <span><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
            <a href="/logout">Выйти</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Добро пожаловать!</h2>
            <p class="welcome">Панель управления VPN-серверами готова к работе.</p>
            <br>
            <button class="btn btn-primary" onclick="alert('Функционал добавления серверов в разработке')">
                + Добавить сервер
            </button>
        </div>
    </div>
</body>
</html>
