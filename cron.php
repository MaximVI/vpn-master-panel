<?php
/**
 * Скрипт для запуска по cron
 * Добавьте в crontab:
 * */5 * * * * php /opt/vpn-master-panel/cron.php
 */

require_once __DIR__ . '/public/index.php';

use App\Core\Database;
use App\Services\CronService;

try {
    $db = Database::getInstance();
    $db->initTables();
    
    $cron = new CronService();
    
    // Каждые 5 минут собираем статистику
    $cron->collectAllStats();
    
    // Раз в день в 3:00 чистим старые логи
    if (date('H:i') === '03:00') {
        $cron->cleanup();
    }
    
    echo date('Y-m-d H:i:s') . " - Cron completed\n";
} catch (Exception $e) {
    echo date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    exit(1);
}
