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
use App\Controllers\ServerController;

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
$auth = new AuthService();

// === СТРАНИЦА ЛОГИНА ===
if ($uri === '/login') {
    $error = null;
    
    if ($method === 'POST') {
        $result = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: /');
            exit;
        }
        $error = $result['message'];
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VPN Master Panel - Вход</title>
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
            }
            input:focus { outline: none; border-color: #667eea; }
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
            }
            button:hover { background: #5a67d8; }
            .error { background: #fed7d7; color: #c53030; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 VPN Master Panel</h1>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
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

// === ВЫХОД ===
if ($uri === '/logout') {
    $auth->logout();
    header('Location: /login');
    exit;
}

// === ПРОВЕРКА АВТОРИЗАЦИИ ===
if (!$auth->isLoggedIn()) {
    header('Location: /login');
    exit;
}

// === API: Добавление сервера ===
if ($uri === '/api/servers/add' && $method === 'POST') {
    header('Content-Type: application/json');
    
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Неверный CSRF токен']);
        exit;
    }
    
    $controller = new ServerController();
    $result = $controller->add($_POST);
    echo json_encode($result);
    exit;
}

// === API: Удаление сервера ===
if (preg_match('#^/api/servers/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Неверный CSRF токен']);
        exit;
    }
    
    $controller = new ServerController();
    $result = $controller->delete((int)$m[1]);
    echo json_encode($result);
    exit;
}

// === ГЛАВНАЯ СТРАНИЦА ===
$controller = new ServerController();
$servers = $controller->index();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Master Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f7fafc; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 20px; }
        .header a { color: rgba(255,255,255,0.8); text-decoration: none; margin-left: 16px; }
        .header a:hover { color: white; }
        
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }
        
        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-danger { background: #fc8181; color: white; }
        .btn-danger:hover { background: #f56565; }
        .btn-info { background: #4299e1; color: white; }
        .btn-info:hover { background: #3182ce; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background: #c6f6d5; color: #22543d; }
        .badge-pending { background: #fefcbf; color: #744210; }
        .badge-error { background: #fed7d7; color: #742a2a; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h2 { margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 4px; color: #4a5568; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        
        .empty-state { text-align: center; padding: 40px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 VPN Master Panel</h1>
        <div>
            <span><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
            <a href="/logout">Выйти</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>🖥 Серверы VPN</h2>
                <button class="btn btn-primary" onclick="openAddServerModal()">+ Добавить сервер</button>
            </div>
            
            <?php if (empty($servers)): ?>
                <div class="empty-state">
                    <p style="font-size: 48px;">🖥</p>
                    <p style="font-size: 18px; margin-top: 10px;">Нет добавленных серверов</p>
                    <p style="margin-top: 5px;">Нажмите "Добавить сервер" чтобы начать</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>IP-адрес</th>
                            <th>SSH порт</th>
                            <th>Статус</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($server['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($server['ip_address']) ?></code></td>
                            <td><?= $server['ssh_port'] ?></td>
                            <td>
                                <span class="badge badge-<?= $server['status'] === 'active' ? 'active' : ($server['status'] === 'error' ? 'error' : 'pending') ?>">
                                    <?= htmlspecialchars($server['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($server['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-info btn-sm">👥 Клиенты</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteServer(<?= $server['id'] ?>)">🗑 Удалить</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Модальное окно добавления сервера -->
    <div class="modal" id="addServerModal">
        <div class="modal-content">
            <h2>Добавить сервер</h2>
            <form id="addServerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label>Название сервера</label>
                    <input type="text" name="name" placeholder="Например: Germany-1" required>
                </div>
                
                <div class="form-group">
                    <label>IP-адрес</label>
                    <input type="text" name="ip_address" placeholder="123.456.789.0" required>
                </div>
                
                <div class="form-group">
                    <label>SSH порт</label>
                    <input type="number" name="ssh_port" value="22" required>
                </div>
                
                <div class="form-group">
                    <label>Пользователь SSH</label>
                    <input type="text" name="ssh_username" value="root" required>
                </div>
                
                <div class="form-group">
                    <label>Тип аутентификации</label>
                    <select name="auth_type">
                        <option value="password">Пароль</option>
                        <option value="key">SSH ключ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Пароль / SSH ключ</label>
                    <input type="text" name="auth_value" placeholder="••••••••" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeAddServerModal()" style="background:#e2e8f0;">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddServerModal() {
            document.getElementById('addServerModal').classList.add('active');
        }
        
        function closeAddServerModal() {
            document.getElementById('addServerModal').classList.remove('active');
        }
        
        document.getElementById('addServerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('/api/servers/add', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при добавлении сервера');
                }
            } catch (err) {
                alert('Ошибка соединения');
            }
        });
        
        async function deleteServer(id) {
            if (!confirm('Вы уверены, что хотите удалить этот сервер? Все клиенты будут удалены!')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            
            try {
                const response = await fetch('/api/servers/' + id + '/delete', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при удалении');
                }
            } catch (err) {
                alert('Ошибка соединения');
            }
        }
    </script>
</body>
</html>
