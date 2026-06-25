#!/bin/bash
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
    echo -e "${RED}❌ Запустите от root: sudo bash update.sh${NC}"
    exit 1
fi

INSTALL_DIR="/opt/vpn-master-panel"
BACKUP_DIR="/opt/vpn-master-panel-backups"
GITHUB_REPO="https://github.com/MaximVI/vpn-master-panel"
TMP_DIR="/tmp/vpn-panel-update-$$"

if [ ! -f "$INSTALL_DIR/public/index.php" ]; then
    echo -e "${RED}❌ Панель не найдена в $INSTALL_DIR${NC}"
    exit 1
fi

CURRENT_VERSION=$(grep "'version'" "$INSTALL_DIR/config/app.php" 2>/dev/null | grep -oP "\d+\.\d+\.\d+" || echo "неизвестно")
echo -e "${BLUE}📦 Текущая версия: ${YELLOW}$CURRENT_VERSION${NC}"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 1: Проверка обновлений
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[1/5] Проверка обновлений...${NC}"

REMOTE_VERSION=""
VERSION_URL="https://raw.githubusercontent.com/MaximVI/vpn-master-panel/main/config/version.php"

if command -v curl &> /dev/null; then
    VERSION_CONTENT=$(curl -s --connect-timeout 10 "$VERSION_URL" 2>/dev/null || echo "")
    if [ -n "$VERSION_CONTENT" ]; then
        REMOTE_VERSION=$(echo "$VERSION_CONTENT" | grep -oP "'version'\s*=>\s*'([\d.]+)'" | grep -oP "[\d.]+")
    fi
elif command -v wget &> /dev/null; then
    VERSION_CONTENT=$(wget -qO- --timeout=10 "$VERSION_URL" 2>/dev/null || echo "")
    if [ -n "$VERSION_CONTENT" ]; then
        REMOTE_VERSION=$(echo "$VERSION_CONTENT" | grep -oP "'version'\s*=>\s*'([\d.]+)'" | grep -oP "[\d.]+")
    fi
fi

if [ -z "$REMOTE_VERSION" ]; then
    echo -e "${YELLOW}⚠️  Не удалось проверить версию на GitHub${NC}"
    echo -e "${YELLOW}   Обновляем принудительно.${NC}"
else
    echo -e "${BLUE}   Доступна версия: ${GREEN}$REMOTE_VERSION${NC}"
    if [ "$CURRENT_VERSION" = "$REMOTE_VERSION" ]; then
        echo -e "${GREEN}   ✅ У вас актуальная версия${NC}"
        echo -e "${YELLOW}   Всё равно обновить? (y/n)${NC}"
        read -r REPLY
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Отменено."
            exit 0
        fi
    else
        echo -e "${YELLOW}   Доступно обновление: $CURRENT_VERSION → $REMOTE_VERSION${NC}"
    fi
fi

echo -e "${YELLOW}   Начать обновление? (y/n)${NC}"
read -r REPLY
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Отменено."
    exit 0
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 2: Бэкап
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[2/5] Создание резервной копии...${NC}"

BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
mkdir -p "$BACKUP_PATH"

if [ -f "$INSTALL_DIR/storage/database/panel.sqlite" ]; then
    cp "$INSTALL_DIR/storage/database/panel.sqlite" "$BACKUP_PATH/"
    echo "   ✅ База данных сохранена"
fi

if [ -f "$INSTALL_DIR/config/app.php" ]; then
    cp "$INSTALL_DIR/config/app.php" "$BACKUP_PATH/app.php.bak"
    echo "   ✅ Конфигурация сохранена"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 3: Загрузка обновлений с GitHub
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[3/5] Загрузка обновлений с GitHub...${NC}"

rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

DOWNLOAD_URL="${GITHUB_REPO}/archive/refs/heads/main.zip"

echo "   Скачивание $DOWNLOAD_URL"

# Пробуем wget, затем curl
if command -v wget &> /dev/null; then
    wget -q --timeout=60 -O "$TMP_DIR/main.zip" "$DOWNLOAD_URL" 2>/dev/null
elif command -v curl &> /dev/null; then
    curl -sL --connect-timeout 60 -o "$TMP_DIR/main.zip" "$DOWNLOAD_URL" 2>/dev/null
else
    echo -e "${RED}❌ Нужен wget или curl${NC}"
    exit 1
fi

if [ ! -f "$TMP_DIR/main.zip" ] || [ ! -s "$TMP_DIR/main.zip" ]; then
    echo -e "${RED}❌ Не удалось скачать архив${NC}"
    echo -e "${YELLOW}   Проверьте интернет-соединение${NC}"
    exit 1
fi

echo "   Распаковка архива..."
if ! command -v unzip &> /dev/null; then
    apt-get install -y -qq unzip
fi

unzip -qo "$TMP_DIR/main.zip" -d "$TMP_DIR/"

# Находим распакованную папку
EXTRACTED_DIR=$(ls "$TMP_DIR" | grep "vpn-master-panel-")
SOURCE_DIR="$TMP_DIR/$EXTRACTED_DIR"

if [ -z "$EXTRACTED_DIR" ] || [ ! -d "$SOURCE_DIR" ]; then
    echo -e "${RED}❌ Ошибка распаковки${NC}"
    ls -la "$TMP_DIR/"
    exit 1
fi

echo "   Копирование файлов в $INSTALL_DIR..."

# Копируем всё кроме storage и .env
rsync -a --exclude='storage' --exclude='.env' --exclude='.git' "$SOURCE_DIR/" "$INSTALL_DIR/" 2>/dev/null || \
cp -rf "$SOURCE_DIR/"* "$INSTALL_DIR/" 2>/dev/null

# Убеждаемся что storage существует
mkdir -p "$INSTALL_DIR/storage/"{logs,database,backups,cache}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 4: Применение
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[4/5] Применение обновлений...${NC}"

# Права
chown -R www-data:www-data "$INSTALL_DIR" 2>/dev/null || true
chmod -R 755 "$INSTALL_DIR" 2>/dev/null || true
chmod -R 775 "$INSTALL_DIR/storage" 2>/dev/null || true
echo "   ✅ Права доступа обновлены"

# Обновление БД
echo "   Проверка базы данных..."
php8.3 -r '
try {
    require_once "/opt/vpn-master-panel/app/Core/Database.php";
    App\Core\Database::getInstance()->initTables();
    echo "   ✅ БД актуальна\n";
} catch (Exception $e) {
    echo "   ⚠️  " . $e->getMessage() . "\n";
}
' 2>/dev/null || echo "   ⚠️  Не удалось проверить БД"

# Очистка кеша
rm -rf /tmp/php-* 2>/dev/null || true
rm -rf "$INSTALL_DIR/storage/cache/"* 2>/dev/null || true
echo "   ✅ Кеш очищен"

# Удаляем временные файлы
rm -rf "$TMP_DIR"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 5: Перезапуск
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[5/5] Перезапуск сервисов...${NC}"

systemctl restart php8.3-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
echo "   ✅ Сервисы перезапущены"

# Проверка
sleep 3
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════╗${NC}"
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo -e "${GREEN}║     ✅ Обновление завершено!         ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${GREEN}   Сайт работает (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}║     ⚠️  Возможны проблемы            ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}   HTTP код: $HTTP_CODE${NC}"
    echo -e "${YELLOW}   Бэкап сохранён: $BACKUP_PATH${NC}"
    echo -e "${YELLOW}   Восстановить: cp $BACKUP_PATH/panel.sqlite $INSTALL_DIR/storage/database/${NC}"
fi

echo ""
echo -e "${BLUE}📦 Новая версия:${NC} $(grep "'version'" "$INSTALL_DIR/config/app.php" 2>/dev/null | grep -oP "[\d.]+" || echo "неизвестно")"
echo -e "${BLUE}💾 Бэкап:${NC} $BACKUP_PATH"
echo ""
