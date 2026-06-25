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

# Проверка, что панель установлена
if [ ! -f "$INSTALL_DIR/public/index.php" ]; then
    echo -e "${RED}❌ Панель не найдена в $INSTALL_DIR${NC}"
    echo -e "${YELLOW}   Используйте install.sh для новой установки${NC}"
    exit 1
fi

# Текущая версия
CURRENT_VERSION=$(grep "'version'" "$INSTALL_DIR/config/app.php" 2>/dev/null | grep -oP "\d+\.\d+\.\d+" || echo "неизвестно")
echo -e "${BLUE}📦 Текущая версия: ${YELLOW}$CURRENT_VERSION${NC}"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 1: Проверка новой версии на GitHub
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[1/6] Проверка обновлений...${NC}"

if command -v git &> /dev/null && [ -d "$INSTALL_DIR/.git" ]; then
    cd "$INSTALL_DIR"
    
    # Сохраняем текущий хеш
    OLD_HASH=$(git rev-parse HEAD 2>/dev/null || echo "")
    
    # Получаем обновления
    git fetch origin main 2>/dev/null || {
        echo -e "${YELLOW}⚠️  Не удалось подключиться к GitHub${NC}"
        echo -e "${YELLOW}   Проверьте интернет-соединение${NC}"
        exit 1
    }
    
    NEW_HASH=$(git rev-parse origin/main 2>/dev/null || echo "")
    
    if [ "$OLD_HASH" = "$NEW_HASH" ]; then
        echo -e "${GREEN}✅ У вас последняя версия!${NC}"
        exit 0
    fi
    
    # Смотрим что изменилось
    echo -e "${BLUE}📋 Изменения:${NC}"
    git log --oneline $OLD_HASH..$NEW_HASH 2>/dev/null | head -10 || echo "   Не удалось получить список изменений"
    
    echo ""
    echo -e "${YELLOW}Доступно обновление. Продолжить? (y/n)${NC}"
    read -r REPLY
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Отменено."
        exit 0
    fi
    
    # Скачиваем обновления
    echo "   Скачивание обновлений..."
    git pull origin main
else
    echo -e "${YELLOW}⚠️  Git не найден или панель установлена не из репозитория${NC}"
    echo -e "${YELLOW}   Обновление возможно только для установок из Git${NC}"
    exit 1
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 2: Резервное копирование
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[2/6] Создание резервной копии...${NC}"

BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
mkdir -p "$BACKUP_PATH"

# Копируем БД и конфиги
if [ -f "$INSTALL_DIR/storage/database/panel.sqlite" ]; then
    cp "$INSTALL_DIR/storage/database/panel.sqlite" "$BACKUP_PATH/"
    echo "   ✅ База данных сохранена"
fi

if [ -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env" "$BACKUP_PATH/"
    echo "   ✅ .env сохранён"
fi

# Сохраняем конфиги
cp "$INSTALL_DIR/config/app.php" "$BACKUP_PATH/app.php.bak" 2>/dev/null || true
echo "   ✅ Конфигурация сохранена"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 3: Применение обновлений
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[3/6] Применение обновлений...${NC}"

# Обновляем права на новые файлы
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/storage"

echo "   ✅ Права обновлены"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 4: Обновление базы данных
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[4/6] Обновление базы данных...${NC}"

php8.3 -r '
try {
    require_once "/opt/vpn-master-panel/app/Core/Database.php";
    $db = App\Core\Database::getInstance();
    $db->initTables();  // Создаст новые таблицы, если их нет
    echo "   ✅ Структура БД обновлена\n";
} catch (Exception $e) {
    echo "   ⚠️  " . $e->getMessage() . "\n";
}
'

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 5: Очистка кеша
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[5/6] Очистка кеша...${NC}"

# Очищаем кеш PHP
rm -rf /tmp/php-* 2>/dev/null || true

# Очищаем сессии
rm -f /tmp/sess_* 2>/dev/null || true

# Очищаем кеш панели
rm -rf "$INSTALL_DIR/storage/cache/"* 2>/dev/null || true

echo "   ✅ Кеш очищен"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Шаг 6: Перезапуск сервисов
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo -e "${GREEN}[6/6] Перезапуск сервисов...${NC}"

systemctl restart php8.3-fpm
systemctl reload nginx

echo "   ✅ Сервисы перезапущены"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Проверка
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo ""
echo -e "${BLUE}🔍 Проверка после обновления...${NC}"

sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo -e "${GREEN}✅ Сайт работает (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}❌ Сайт вернул код $HTTP_CODE${NC}"
    echo -e "${YELLOW}   Восстановление из бэкапа...${NC}"
    
    # Восстанавливаем БД
    if [ -f "$BACKUP_PATH/panel.sqlite" ]; then
        cp "$BACKUP_PATH/panel.sqlite" "$INSTALL_DIR/storage/database/panel.sqlite"
        echo "   ✅ БД восстановлена"
    fi
    
    echo -e "${YELLOW}   Бэкап сохранён в $BACKUP_PATH${NC}"
fi

# Новая версия
NEW_VERSION=$(grep "'version'" "$INSTALL_DIR/config/app.php" 2>/dev/null | grep -oP "\d+\.\d+\.\d+" || echo "неизвестно")

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     ✅ Обновление завершено!         ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📦 Версия:${NC} $CURRENT_VERSION → ${GREEN}$NEW_VERSION${NC}"
echo -e "${BLUE}💾 Бэкап:${NC} $BACKUP_PATH"
echo ""
echo -e "${BLUE}📍 Панель:${NC} http://$(curl -s ifconfig.me 2>/dev/null || echo 'ваш-ip')"
echo ""
echo -e "${YELLOW}💡 Если что-то пошло не так:${NC}"
echo "   1. Бэкап в $BACKUP_PATH"
echo "   2. Восстановить: cp $BACKUP_PATH/panel.sqlite $INSTALL_DIR/storage/database/"
echo ""
