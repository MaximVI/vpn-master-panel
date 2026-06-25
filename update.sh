#!/bin/bash
# VPN Master Panel - Update Script v2.1
# Обновление без Git, через скачивание архива с GitHub

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   🔄 VPN Master Panel - Обновление   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════╝${NC}"
echo ""

if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Запустите от root: sudo bash update.sh${NC}"
    exit 1
fi

INSTALL_DIR="/opt/vpn-master-panel"
BACKUP_DIR="/opt/vpn-master-panel-backups"
DOWNLOAD_URL="https://github.com/MaximVI/vpn-master-panel/archive/refs/heads/main.zip"
TMP_DIR="/tmp/vpn-panel-update-$$"

if [ ! -f "$INSTALL_DIR/public/index.php" ]; then
    echo -e "${RED}Панель не найдена в $INSTALL_DIR${NC}"
    exit 1
fi

# Версия
CURRENT_VERSION=$(grep "'version'" "$INSTALL_DIR/config/app.php" 2>/dev/null | grep -oP "\d+\.\d+\.\d+" || echo "?")
echo -e "${BLUE}Текущая версия: ${YELLOW}$CURRENT_VERSION${NC}"

# Проверка интернета
echo -e "${GREEN}[1/5] Проверка подключения...${NC}"
if ! ping -c 1 github.com &>/dev/null && ! ping -c 1 raw.githubusercontent.com &>/dev/null; then
    echo -e "${YELLOW}Нет доступа к GitHub. Проверьте интернет.${NC}"
    exit 1
fi
echo "   OK"

# Бэкап
echo -e "${GREEN}[2/5] Создание бэкапа...${NC}"
BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
mkdir -p "$BACKUP_PATH"

if [ -f "$INSTALL_DIR/storage/database/panel.sqlite" ]; then
    cp "$INSTALL_DIR/storage/database/panel.sqlite" "$BACKUP_PATH/"
    echo "   БД сохранена"
fi

# Загрузка
echo -e "${GREEN}[3/5] Загрузка обновлений...${NC}"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

echo "   Скачивание main.zip..."

# Пробуем wget
if wget -q --timeout=30 -O "$TMP_DIR/main.zip" "$DOWNLOAD_URL" 2>/dev/null; then
    echo "   Скачано через wget"
elif curl -sL --connect-timeout 30 -o "$TMP_DIR/main.zip" "$DOWNLOAD_URL" 2>/dev/null; then
    echo "   Скачано через curl"
else
    echo -e "${RED}Ошибка загрузки${NC}"
    exit 1
fi

# Распаковка
echo "   Распаковка..."
if ! command -v unzip &>/dev/null; then
    apt-get install -y -qq unzip
fi

unzip -qo "$TMP_DIR/main.zip" -d "$TMP_DIR/"
EXTRACTED_DIR=$(ls "$TMP_DIR" | grep "vpn-master-panel-")
SOURCE_DIR="$TMP_DIR/$EXTRACTED_DIR"

# Копирование
echo "   Копирование файлов..."
if command -v rsync &>/dev/null; then
    rsync -a --exclude='storage' --exclude='.env' "$SOURCE_DIR/" "$INSTALL_DIR/"
else
    cp -rf "$SOURCE_DIR/"* "$INSTALL_DIR/"
fi

mkdir -p "$INSTALL_DIR/storage/"{logs,database,backups,cache}

# Применение
echo -e "${GREEN}[4/5] Применение...${NC}"

chown -R www-data:www-data "$INSTALL_DIR" 2>/dev/null || true
chmod -R 755 "$INSTALL_DIR" 2>/dev/null || true
chmod -R 775 "$INSTALL_DIR/storage" 2>/dev/null || true
echo "   Права обновлены"

php8.3 -r 'require_once "/opt/vpn-master-panel/app/Core/Database.php"; App\Core\Database::getInstance()->initTables(); echo "OK\n";' 2>/dev/null && echo "   БД обновлена" || echo "   БД: без изменений"

rm -rf /tmp/php-* "$INSTALL_DIR/storage/cache/"* "$TMP_DIR" 2>/dev/null || true

# Перезапуск
echo -e "${GREEN}[5/5] Перезапуск сервисов...${NC}"
systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     Обновление завершено             ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════╝${NC}"
echo ""
echo -e "HTTP статус: ${HTTP_CODE}"
echo -e "Бэкап: ${BACKUP_PATH}"
echo ""
