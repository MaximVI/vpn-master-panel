#!/bin/bash
set -e

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   🔐 VPN Master Panel - Установщик   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════╝${NC}"
echo ""

# Проверка прав
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Запустите от root: sudo bash install.sh${NC}"
    exit 1
fi

# Проверка ОС
if [ -f /etc/os-release ]; then
    . /etc/os-release
    echo -e "${BLUE}📦 ОС: $NAME $VERSION_ID${NC}"
    
    if [[ ! "$VERSION_ID" =~ ^(22.04|24.04)$ ]]; then
        echo -e "${YELLOW}⚠️  Рекомендуется Ubuntu 22.04 или 24.04${NC}"
        echo -e "${YELLOW}   У вас: $VERSION_ID. Продолжить? (y/n)${NC}"
        read -r REPLY
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 0
        fi
    fi
fi

INSTALL_DIR="/opt/vpn-master-panel"
SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 1: Установка пакетов
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[1/7] Установка системных пакетов...${NC}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

# Основные пакеты
PACKAGES="nginx wireguard-tools qrencode curl git unzip"

# Проверяем и устанавливаем PHP 8.3
if ! command -v php8.3 &> /dev/null; then
    echo -e "${YELLOW}   Добавление репозитория PHP...${NC}"
    apt-get install -y -qq software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
fi

PACKAGES="$PACKAGES php8.3-cli php8.3-fpm php8.3-sqlite3"
PACKAGES="$PACKAGES php8.3-curl php8.3-mbstring php8.3-xml php8.3-gd"
PACKAGES="$PACKAGES php8.3-ssh2"  # ВАЖНО: SSH2 расширение

echo -e "   Установка: $PACKAGES"
apt-get install -y -qq $PACKAGES

# Проверяем, что SSH2 установился
if ! php8.3 -m | grep -q ssh2; then
    echo -e "${YELLOW}⚠️  SSH2 не установлен, пробуем ещё раз...${NC}"
    apt-get install -y -qq php8.3-ssh2 || {
        echo -e "${YELLOW}   SSH2 недоступен. Функции SSH будут отключены.${NC}"
    }
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 2: Копирование файлов
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[2/7] Копирование файлов...${NC}"

# ИСПРАВЛЕНИЕ ОШИБКИ #1: Не копируем если уже в целевой папке
if [ "$SOURCE_DIR" != "$INSTALL_DIR" ]; then
    echo "   Копирование из $SOURCE_DIR в $INSTALL_DIR"
    mkdir -p $INSTALL_DIR
    rsync -a "$SOURCE_DIR/" "$INSTALL_DIR/" \
        --exclude='.git' \
        --exclude='storage' \
        --exclude='.gitignore' \
        2>/dev/null || cp -r "$SOURCE_DIR/"* "$INSTALL_DIR/" 2>/dev/null || true
else
    echo "   Установка из целевой директории ($INSTALL_DIR)"
fi

# Создаём структуру storage
mkdir -p $INSTALL_DIR/storage/{logs,database,backups,cache}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 3: Права доступа
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[3/7] Настройка прав доступа...${NC}"

# ИСПРАВЛЕНИЕ ОШИБКИ #2: Правильные права для www-data
chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/storage

# Проверяем, что права применились
if [ -d "$INSTALL_DIR/public" ]; then
    OWNER=$(stat -c '%U' "$INSTALL_DIR/public" 2>/dev/null || echo "unknown")
    echo "   Владелец public/: $OWNER"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 4: Настройка PHP
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[4/7] Настройка PHP...${NC}"

# Проверяем версию PHP
PHP_VER=$(php8.3 -v 2>/dev/null | head -1 || echo "не установлен")
echo "   Версия PHP: $PHP_VER"

# ИСПРАВЛЕНИЕ ОШИБКИ #5: Определяем сокет PHP-FPM
if [ -S /run/php/php8.3-fpm.sock ]; then
    PHP_SOCKET="unix:/run/php/php8.3-fpm.sock"
    echo "   Найден сокет: /run/php/php8.3-fpm.sock"
elif [ -S /var/run/php/php8.3-fpm.sock ]; then
    PHP_SOCKET="unix:/var/run/php/php8.3-fpm.sock"
    echo "   Найден сокет: /var/run/php/php8.3-fpm.sock"
else
    echo "   Сокет не найден, использую TCP 127.0.0.1:9000"
    PHP_SOCKET="127.0.0.1:9000"
    sed -i 's|listen = .*|listen = 127.0.0.1:9000|' /etc/php/8.3/fpm/pool.d/www.conf
fi

# Настройка php.ini
PHP_INI="/etc/php/8.3/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^display_errors = .*/display_errors = Off/' $PHP_INI
    sed -i 's/^log_errors = .*/log_errors = On/' $PHP_INI
    sed -i 's/^error_reporting = .*/error_reporting = E_ALL/' $PHP_INI
fi

systemctl restart php8.3-fpm
systemctl enable php8.3-fpm

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 5: Настройка Nginx
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[5/7] Настройка Nginx...${NC}"

cat > /etc/nginx/sites-available/vpn-panel << NGINXEOF
server {
    listen 80;
    server_name _;
    root /opt/vpn-master-panel/public;
    index index.php index.html;
    
    access_log /var/log/nginx/vpn-panel-access.log;
    error_log /var/log/nginx/vpn-panel-error.log;
    
    client_max_body_size 50M;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php\$ {
        fastcgi_pass $PHP_SOCKET;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location /storage {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/vpn-panel /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Проверка конфигурации
if nginx -t 2>&1 | grep -q "successful"; then
    systemctl restart nginx
    systemctl enable nginx
    echo "   Nginx настроен успешно"
else
    echo -e "${RED}❌ Ошибка в конфигурации Nginx:${NC}"
    nginx -t
    exit 1
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 6: База данных
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[6/7] Инициализация базы данных...${NC}"

php8.3 -r '
try {
    require_once "/opt/vpn-master-panel/app/Core/Database.php";
    $db = App\Core\Database::getInstance();
    $db->initTables();
    
    // Проверяем, что таблицы создались
    $tables = $db->getPdo()->query("SELECT name FROM sqlite_master WHERE type=\"table\"")->fetchAll();
    echo "   Таблиц создано: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "     - " . $t["name"] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка БД: " . $e->getMessage() . "\n";
    echo "   Проверьте права на storage/\n";
    exit(1);
}
'

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 7: Финальные проверки
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[7/7] Финальные проверки...${NC}"

# Проверяем, что сайт отвечает
sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")
echo "   HTTP статус: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo "   ✅ Сайт отвечает"
else
    echo -e "${YELLOW}   ⚠️  Сайт вернул код $HTTP_CODE${NC}"
    echo "   Проверьте: tail -f /var/log/nginx/vpn-panel-error.log"
fi

# Проверка PHP обработки
PHP_TEST=$(curl -s http://localhost/ 2>/dev/null | head -5)
if echo "$PHP_TEST" | grep -q "<!DOCTYPE\|html\|login"; then
    echo "   ✅ PHP работает"
else
    echo -e "${YELLOW}   ⚠️  PHP может не работать${NC}"
fi

# Настройка файрвола
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp 2>/dev/null || true
    ufw allow 443/tcp 2>/dev/null || true
    ufw allow 443/udp 2>/dev/null || true
    ufw allow 22/tcp 2>/dev/null || true
fi

# IP сервера
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || curl -s icanhazip.com 2>/dev/null || hostname -I | awk '{print $1}')

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Готово!
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     ✅ Установка завершена!          ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}┌─────────────────────────────────────┐${NC}"
echo -e "${BLUE}│${NC}  📍 Панель: ${GREEN}http://${SERVER_IP}${NC}"
echo -e "${BLUE}│${NC}  🔑 Email:   ${GREEN}admin@vpn.local${NC}"
echo -e "${BLUE}│${NC}  🔑 Пароль:  ${GREEN}admin123${NC}"
echo -e "${BLUE}└─────────────────────────────────────┘${NC}"
echo ""
echo -e "${RED}⚠️  Смените пароль после первого входа!${NC}"
echo ""
echo -e "${BLUE}📋 Полезные команды:${NC}"
echo "   systemctl status nginx            - статус веб-сервера"
echo "   systemctl status php8.3-fpm       - статус PHP"
echo "   tail -f /var/log/nginx/vpn-panel-error.log - логи ошибок"
echo "   php8.3 /opt/vpn-master-panel/cron.php - ручной запуск крона"
echo ""
echo -e "${BLUE}🔧 Если сайт не открывается:${NC}"
echo "   1. Проверьте права: ls -la /opt/vpn-master-panel/public/"
echo "   2. Проверьте логи: tail -20 /var/log/nginx/vpn-panel-error.log"
echo "   3. Перезапустите: systemctl restart nginx php8.3-fpm"
echo ""
