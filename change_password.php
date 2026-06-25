<?php
/**
 * Скрипт смены пароля администратора
 * Использование: php change_password.php
 */

if (PHP_SAPI !== 'cli') {
    die("Запускайте из командной строки: php change_password.php\n");
}

echo "╔═══════════════════════════════════╗\n";
echo "║   🔐 Смена пароля администратора  ║\n";
echo "╚═══════════════════════════════════╝\n\n";

echo "Новый пароль (минимум 8 символов): ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    die("❌ Пароль должен быть не менее 8 символов!\n");
}

echo "Повторите пароль: ";
$password2 = trim(fgets(STDIN));

if ($password !== $password2) {
    die("❌ Пароли не совпадают!\n");
}

require_once __DIR__ . '/app/Core/Database.php';

try {
    $db = App\Core\Database::getInstance();
    
    $hash = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
    
    $updated = $db->update('users', ['password' => $hash], ['email' => 'admin@vpn.local']);
    
    if ($updated > 0) {
        echo "\n✅ Пароль успешно изменён!\n";
    } else {
        echo "\n❌ Не удалось изменить пароль. Проверьте права на БД.\n";
    }
} catch (Exception $e) {
    die("❌ Ошибка: " . $e->getMessage() . "\n");
}
