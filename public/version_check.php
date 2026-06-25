<?php
// Проверка версии (вызывается из index.php)
$versionFile = __DIR__ . '/../config/version.php';
if (file_exists($versionFile)) {
    $version = require $versionFile;
    define('APP_VERSION', $version['version']);
} else {
    define('APP_VERSION', '0.0.0');
}
