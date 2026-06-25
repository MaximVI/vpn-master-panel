<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/SSHService.php';
require_once __DIR__ . '/../app/Services/ClientService.php';
require_once __DIR__ . '/../app/Services/StatsService.php';

use App\Core\Database;
use App\Services\SSHService;
use App\Services\ClientService;
use App\Services\StatsService;

session_start();

header('Content-Type: application/json');

// Проверка авторизации (кроме проверки обновлений)
$action = $_GET['action'] ?? '';

if (!in_array($action, ['check_update', 'version'])) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Неверный CSRF токен']);
            exit;
        }
    }
}

$db = Database::getInstance();

try {
    switch ($action) {
        // Проверка версии
        case 'version':
            $version = require __DIR__ . '/../config/version.php';
            echo json_encode([
                'success' => true,
                'version' => $version['version'],
                'release_date' => $version['release_date'],
                'changelog' => $version['changelog'],
            ]);
            break;
        
        // Проверка обновлений
        case 'check_update':
            $currentVersion = require __DIR__ . '/../config/version.php';
            
            // Пытаемся получить последнюю версию с GitHub
            $latestVersion = null;
            $updateAvailable = false;
            
            if (function_exists('curl_init')) {
                $ch = curl_init('https://raw.githubusercontent.com/MaximVI/vpn-master-panel/main/config/version.php');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'VPN-Panel-Update-Checker');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    // Парсим версию из ответа
                    preg_match("/'version'\s*=>\s*'([\d.]+)'/", $response, $matches);
                    if (isset($matches[1])) {
                        $latestVersion = $matches[1];
                        $updateAvailable = version_compare($latestVersion, $currentVersion['version'], '>');
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'current_version' => $currentVersion['version'],
                'latest_version' => $latestVersion ?? 'неизвестно',
                'update_available' => $updateAvailable,
            ]);
            break;
        
        // ... остальные действия (add_server, test_server, и т.д.)
        // (код из предыдущей версии api.php)
        
        default:
            echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
