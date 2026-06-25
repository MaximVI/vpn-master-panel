#!/bin/bash
# Скрипт диагностики проблем

echo "🔍 Диагностика VPN Master Panel"
echo "================================"
echo ""

# Проверка файлов
echo "📁 Проверка файлов:"
ls -la /opt/vpn-master-panel/public/index.php 2>/dev/null && echo "   ✅ index.php существует" || echo "   ❌ index.php не найден"

# Проверка прав
echo ""
echo "🔐 Проверка прав:"
OWNER=$(stat -c '%U:%G' /opt/vpn-master-panel/public/ 2>/dev/null)
echo "   Владелец public/: $OWNER"
if [ "$OWNER" = "www-data:www-data" ]; then
    echo "   ✅ Права правильные"
else
    echo "   ❌ Ожидается www-data:www-data"
fi

# Проверка сервисов
echo ""
echo "⚙️  Проверка сервисов:"
systemctl is-active nginx 2>/dev/null && echo "   ✅ Nginx запущен" || echo "   ❌ Nginx не запущен"
systemctl is-active php8.3-fpm 2>/dev/null && echo "   ✅ PHP-FPM запущен" || echo "   ❌ PHP-FPM не запущен"

# Проверка портов
echo ""
echo "🌐 Проверка портов:"
ss -tlnp | grep -q ":80 " && echo "   ✅ Порт 80 слушается" || echo "   ❌ Порт 80 не слушается"

# Проверка БД
echo ""
echo "🗄 Проверка базы данных:"
if [ -f /opt/vpn-master-panel/storage/database/panel.sqlite ]; then
    echo "   ✅ Файл БД существует"
    sqlite3 /opt/vpn-master-panel/storage/database/panel.sqlite "SELECT COUNT(*) FROM users;" 2>/dev/null && echo "   ✅ БД читается" || echo "   ❌ БД не читается"
else
    echo "   ❌ Файл БД не найден"
fi

# Проверка PHP
echo ""
echo "🐘 Проверка PHP:"
php8.3 -v 2>/dev/null | head -1 || echo "   ❌ PHP 8.3 не найден"
php8.3 -m 2>/dev/null | grep -q ssh2 && echo "   ✅ SSH2 модуль есть" || echo "   ❌ SSH2 модуль отсутствует"

# HTTP тест
echo ""
echo "🌍 HTTP тест:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null)
echo "   Код ответа: $HTTP_CODE"

# Логи
echo ""
echo "📋 Последние ошибки:"
tail -3 /var/log/nginx/vpn-panel-error.log 2>/dev/null || echo "   Логов нет"

echo ""
echo "================================"
echo "Диагностика завершена"
