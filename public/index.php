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
use App\Services\ClientService;
use App\Services\SSHService;

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

// === ПРОВЕРКА CSRF ДЛЯ POST ЗАПРОСОВ ===
function checkCsrf(): void {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Неверный CSRF токен']);
        exit;
    }
}

// === API: Добавление сервера ===
if ($uri === '/api/servers/add' && $method === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();
    
    $controller = new ServerController();
    $result = $controller->add($_POST);
    echo json_encode($result);
    exit;
}

// === API: Удаление сервера ===
if (preg_match('#^/api/servers/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();
    
    $controller = new ServerController();
    $result = $controller->delete((int)$m[1]);
    echo json_encode($result);
    exit;
}

// === API: Проверка соединения с сервером ===
if (preg_match('#^/api/servers/(\d+)/test$#', $uri, $m)) {
    header('Content-Type: application/json');
    
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [(int)$m[1]]);
    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        exit;
    }
    
    try {
        $ssh = new SSHService($server);
        $result = $ssh->testConnection();
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// === API: Создание клиента ===
if ($uri === '/api/clients/create' && $method === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();
    
    try {
        $clientService = new ClientService();
        $result = $clientService->createClient(
            (int)$_POST['server_id'],
            $_POST['client_name'] ?? ''
        );
        echo json_encode(['success' => true, 'client' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// === API: Удаление клиента ===
if (preg_match('#^/api/clients/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();
    
    try {
        $clientService = new ClientService();
        $clientService->deleteClient((int)$m[1]);
        echo json_encode(['success' => true, 'message' => 'Клиент удалён']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// === API: Toggle клиента ===
if (preg_match('#^/api/clients/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();
    
    try {
        $clientService = new ClientService();
        $clientService->toggleClient((int)$m[1], (bool)($_POST['enabled'] ?? false));
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// === СКАЧИВАНИЕ КОНФИГА ===
if (preg_match('#^/client/(\d+)/config$#', $uri, $m)) {
    try {
        $clientService = new ClientService();
        $config = $clientService->generateConfig((int)$m[1]);
        
        $client = $db->fetch("SELECT * FROM clients WHERE id = ?", [(int)$m[1]]);
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . ($client['name'] ?? 'client') . '.conf"');
        echo $config;
    } catch (Exception $e) {
        http_response_code(404);
        echo "Ошибка: " . $e->getMessage();
    }
    exit;
}

// === QR-КОД ===
if (preg_match('#^/client/(\d+)/qr$#', $uri, $m)) {
    try {
        $clientService = new ClientService();
        $config = $clientService->generateConfig((int)$m[1]);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
        file_put_contents($tempFile, $config);
        
        header('Content-Type: image/png');
        passthru("qrencode -t PNG -o - < $tempFile 2>/dev/null || echo 'QR generation failed'");
        unlink($tempFile);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Ошибка генерации QR";
    }
    exit;
}

// === СТРАНИЦА КЛИЕНТОВ СЕРВЕРА ===
if (preg_match('#^/server/(\d+)/clients$#', $uri, $m)) {
    $serverId = (int)$m[1];
    $server = $db->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
    
    if (!$server) {
        http_response_code(404);
        echo "Сервер не найден";
        exit;
    }
    
    $clientService = new ClientService();
    $clients = $clientService->getClientsByServer($serverId);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Клиенты - <?= htmlspecialchars($server['name']) ?></title>
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
            }
            .header h1 { font-size: 20px; }
            .header a { color: rgba(255,255,255,0.8); text-decoration: none; margin-left: 16px; }
            
            .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }
            
            .breadcrumb { margin-bottom: 20px; }
            .breadcrumb a { color: #667eea; text-decoration: none; }
            
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
                text-decoration: none;
                display: inline-block;
            }
            .btn-primary { background: #667eea; color: white; }
            .btn-danger { background: #fc8181; color: white; }
            .btn-success { background: #48bb78; color: white; }
            .btn-info { background: #4299e1; color: white; }
            .btn-sm { padding: 6px 12px; font-size: 12px; }
            
            table { width: 100%; border-collapse: collapse; }
            th {
                text-align: left;
                padding: 12px;
                background: #f7fafc;
                font-weight: 600;
                border-bottom: 2px solid #e2e8f0;
            }
            td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            
            .badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }
            .badge-success { background: #c6f6d5; color: #22543d; }
            .badge-danger { background: #fed7d7; color: #742a2a; }
            
            code {
                background: #edf2f7;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 12px;
            }
            
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
            
            .empty-state { text-align: center; padding: 40px; color: #a0aec0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🔐 <?= htmlspecialchars($server['name']) ?></h1>
            <div>
                <a href="/">← Назад к серверам</a>
                <a href="/logout">Выйти</a>
            </div>
        </div>
        
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>👥 Клиенты VPN</h2>
                    <button class="btn btn-primary" onclick="openAddClientModal()">+ Добавить клиента</button>
                </div>
                
                <div style="margin-bottom: 20px; padding: 12px; background: #f7fafc; border-radius: 8px;">
                    <strong>IP сервера:</strong> <code><?= htmlspecialchars($server['ip_address']) ?></code>
                    <strong style="margin-left: 20px;">Статус:</strong> 
                    <span class="badge badge-<?= $server['status'] === 'active' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($server['status']) ?>
                    </span>
                </div>
                
                <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <p style="font-size: 48px;">👤</p>
                        <p>Нет клиентов</p>
                        <button class="btn btn-primary" style="margin-top: 15px;" onclick="openAddClientModal()">
                            Создать первого клиента
                        </button>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>IP</th>
                                <th>Публичный ключ</th>
                                <th>Статус</th>
                                <th>Создан</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($client['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($client['ip_address']) ?></code></td>
                                <td><code><?= substr($client['public_key'], 0, 20) ?>...</code></td>
                                <td>
                                    <span class="badge badge-<?= $client['enabled'] ? 'success' : 'danger' ?>">
                                        <?= $client['enabled'] ? 'Активен' : 'Отключен' ?>
                                    </span>
                                </td>
                                <td><?= date('d.m.Y', strtotime($client['created_at'])) ?></td>
                                <td>
                                    <a href="/client/<?= $client['id'] ?>/config" class="btn btn-info btn-sm">📥 Конфиг</a>
                                    <a href="/client/<?= $client['id'] ?>/qr" class="btn btn-info btn-sm" target="_blank">📱 QR</a>
                                    <button onclick="toggleClient(<?= $client['id'] ?>, <?= $client['enabled'] ? '0' : '1' ?>)" 
                                            class="btn btn-sm <?= $client['enabled'] ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $client['enabled'] ? '🔒 Откл' : '🔓 Вкл' ?>
                                    </button>
                                    <button onclick="deleteClient(<?= $client['id'] ?>)" class="btn btn-danger btn-sm">🗑</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Модальное окно добавления клиента -->
        <div class="modal" id="addClientModal">
            <div class="modal-content">
                <h2>Новый клиент</h2>
                <form id="addClientForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="server_id" value="<?= $serverId ?>">
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; margin-bottom:4px; font-weight:500;">Имя клиента</label>
                        <input type="text" name="client_name" required 
                               pattern="[a-zA-Z0-9_-]{3,32}"
                               placeholder="Например: my-phone"
                               style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:6px;">
                        <small style="color:#a0aec0;">3-32 символа, буквы, цифры, - и _</small>
                    </div>
                    
                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="button" onclick="closeAddClientModal()" 
                                style="padding:10px 20px; background:#e2e8f0; border:none; border-radius:8px; cursor:pointer;">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            function openAddClientModal() {
                document.getElementById('addClientModal').classList.add('active');
            }
            
            function closeAddClientModal() {
                document.getElementById('addClientModal').classList.remove('active');
            }
            
            document.getElementById('addClientForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                
                try {
                    const response = await fetch('/api/clients/create', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                    }
                } catch (err) {
                    alert('Ошибка соединения с сервером');
                }
            });
            
            async function deleteClient(id) {
                if (!confirm('Удалить клиента?')) return;
                
                const formData = new FormData();
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                try {
                    const response = await fetch('/api/clients/' + id + '/delete', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                } catch (err) {
                    alert('Ошибка');
                }
            }
            
            async function toggleClient(id, enabled) {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                formData.append('enabled', enabled);
                
                try {
                    const response = await fetch('/api/clients/' + id + '/toggle', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                } catch (err) {
                    alert('Ошибка');
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// === ГЛАВНАЯ СТРАНИЦА (ДАШБОРД) ===
$controller = new ServerController();
$servers = $controller->index();
$currentPage = 'dashboard';

// Подключаем дашборд
require __DIR__ . '/dashboard.php';
</html>
