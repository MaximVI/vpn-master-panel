#!/bin/bash
set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   🔐 VPN Master Panel - Установщик   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════╝${NC}"
echo ""

# Проверка root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Запустите от root: sudo bash install.sh${NC}"
    exit 1
fi

# Определение версии Ubuntu
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ ! "$VERSION_ID" =~ ^(22.04|24.04)$ ]]; then
        echo -e "${YELLOW}⚠️  Рекомендуется Ubuntu 22.04 или 24.04${NC}"
        echo -e "${YELLOW}   У вас: $NAME $VERSION_ID${NC}"
        read -p "Продолжить? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
fi

echo -e "${GREEN}[1/6] Установка пакетов...${NC}"
apt-get update -qq
apt-get install -y -qq \
    php8.3-cli php8.3-fpm php8.3-sqlite3 \
    php8.3-curl php8.3-mbstring php8.3-xml \
    php8.3-gd php8.3-ssh2 \
    nginx wireguard-tools qrencode curl git unzip

echo -e "${GREEN}[2/6] Настройка директорий...${NC}"
INSTALL_DIR="/opt/vpn-master-panel"
mkdir -p $INSTALL_DIR
cp -r . $INSTALL_DIR/
chown -R www-data:www-data $INSTALL_DIR/storage
chmod -R 750 $INSTALL_DIR
chmod -R 770 $INSTALL_DIR/storage

echo -e "${GREEN}[3/6] Настройка PHP...${NC}"
# Проверяем версию PHP
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
echo "PHP версия: $PHP_VERSION"

# Настройка php.ini для продакшена
PHP_INI="/etc/php/8.3/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^display_errors = .*/display_errors = Off/' $PHP_INI
    sed -i 's/^log_errors = .*/log_errors = On/' $PHP_INI
    sed -i 's/^error_reporting = .*/error_reporting = E_ALL/' $PHP_INI
fi

echo -e "${GREEN}[4/6] Настройка Nginx...${NC}"
cat > /etc/nginx/sites-available/vpn-panel << 'NGINXEOF'
server {
    listen 80;
    server_name _;
    root /opt/vpn-master-panel/public;
    index index.php;
    
    access_log /var/log/nginx/vpn-panel-access.log;
    error_log /var/log/nginx/vpn-panel-error.log;
    
    client_max_body_size 50M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }
    
    location /storage {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/vpn-panel /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

echo -e "${GREEN}[5/6] Настройка базы данных...${NC}"
php -r '
require_once "/opt/vpn-master-panel/app/Core/Database.php";
$db = App\Core\Database::getInstance();
$db->initTables();
echo "База данных инициализирована\n";
'

echo -e "${GREEN}[6/6] Финальные настройки...${NC}"
# Настройка файрвола
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 443/udp
    ufw allow 22/tcp
    echo "y" | ufw enable 2>/dev/null || true
fi

# Получаем IP сервера
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     ✅ Установка завершена!          ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📍 Панель управления:${NC} http://${SERVER_IP}"
echo ""
echo -e "${BLUE}🔑 Данные для входа:${NC}"
echo -e "   Email:    ${YELLOW}admin@vpn.local${NC}"
echo -e "   Пароль:   ${YELLOW}admin123${NC}"
echo ""
echo -e "${RED}⚠️  ОБЯЗАТЕЛЬНО смените пароль после первого входа!${NC}"
echo ""
echo -e "${BLUE}📚 Документация:${NC} https://github.com/MaximVI/vpn-master-panel"
echo ""
echo -e "${BLUE}💡 Команды управления:${NC}"
echo "   systemctl restart nginx    - перезапуск веб-сервера"
echo "   systemctl status php8.3-fpm - статус PHP"
echo "   tail -f /opt/vpn-master-panel/storage/logs/*.log - просмотр логов"
echo ""
