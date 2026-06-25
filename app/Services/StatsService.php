<?php
namespace App\Services;

use App\Core\Database;

class StatsService
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Общая статистика системы
     */
    public function getDashboardStats(): array
    {
        return [
            'total_servers' => $this->db->fetchColumn("SELECT COUNT(*) FROM servers"),
            'active_servers' => $this->db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'active'"),
            'total_clients' => $this->db->fetchColumn("SELECT COUNT(*) FROM clients"),
            'active_clients' => $this->db->fetchColumn("SELECT COUNT(*) FROM clients WHERE enabled = 1"),
            'total_traffic' => $this->getTotalTraffic(),
            'connections_today' => $this->getTodayConnections(),
            'unread_notifications' => $this->db->fetchColumn(
                "SELECT COUNT(*) FROM notifications WHERE is_read = 0"
            ),
        ];
    }
    
    /**
     * Получить общий трафик
     */
    public function getTotalTraffic(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_bytes), 0) FROM traffic_logs"
        );
    }
    
    /**
     * Трафик по дням (для графиков)
     */
    public function getTrafficByDays(int $days = 7): array
    {
        return $this->db->fetchAll("
            SELECT 
                DATE(recorded_at) as date,
                SUM(bytes_received) as received,
                SUM(bytes_sent) as sent,
                SUM(total_bytes) as total
            FROM traffic_logs
            WHERE recorded_at >= datetime('now', '-$days days')
            GROUP BY DATE(recorded_at)
            ORDER BY date ASC
        ");
    }
    
    /**
     * Трафик по клиентам
     */
    public function getTrafficByClients(int $limit = 10): array
    {
        return $this->db->fetchAll("
            SELECT 
                c.id,
                c.name,
                c.ip_address,
                COALESCE(SUM(t.total_bytes), 0) as total_traffic,
                COUNT(DISTINCT DATE(t.recorded_at)) as active_days
            FROM clients c
            LEFT JOIN traffic_logs t ON c.id = t.client_id
            GROUP BY c.id
            ORDER BY total_traffic DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Трафик конкретного клиента
     */
    public function getClientTraffic(int $clientId, int $days = 30): array
    {
        return $this->db->fetchAll("
            SELECT 
                DATE(recorded_at) as date,
                bytes_received,
                bytes_sent,
                total_bytes
            FROM traffic_logs
            WHERE client_id = ? 
            AND recorded_at >= datetime('now', '-$days days')
            ORDER BY recorded_at ASC
        ", [$clientId]);
    }
    
    /**
     * Запись трафика клиента
     */
    public function logTraffic(int $clientId, int $received, int $sent): void
    {
        $this->db->insert('traffic_logs', [
            'client_id' => $clientId,
            'bytes_received' => $received,
            'bytes_sent' => $sent,
            'total_bytes' => $received + $sent,
        ]);
        
        // Обновляем общий трафик клиента
        $this->db->query("
            UPDATE clients 
            SET traffic_used = traffic_used + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [($received + $sent), $clientId]);
    }
    
    /**
     * Количество подключений сегодня
     */
    public function getTodayConnections(): int
    {
        return (int) $this->db->fetchColumn("
            SELECT COUNT(*) FROM connection_logs 
            WHERE DATE(created_at) = DATE('now')
            AND event_type = 'connect'
        ");
    }
    
    /**
     * Последние подключения
     */
    public function getRecentConnections(int $limit = 20): array
    {
        return $this->db->fetchAll("
            SELECT 
                cl.*,
                c.name as client_name,
                s.name as server_name
            FROM connection_logs cl
            LEFT JOIN clients c ON cl.client_id = c.id
            LEFT JOIN servers s ON cl.server_id = s.id
            ORDER BY cl.created_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Логирование события подключения
     */
    public function logConnection(int $clientId, int $serverId, string $eventType, string $ip = null, string $details = null): void
    {
        $this->db->insert('connection_logs', [
            'client_id' => $clientId,
            'server_id' => $serverId,
            'event_type' => $eventType,
            'ip_address' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'details' => $details,
        ]);
    }
    
    /**
     * Логирование действий пользователя
     */
    public function logActivity(int $userId, string $action, string $description = null): void
    {
        $this->db->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
    
    /**
     * Получить активность пользователей
     */
    public function getRecentActivity(int $limit = 50): array
    {
        return $this->db->fetchAll("
            SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Создать уведомление
     */
    public function createNotification(string $type, string $title, string $message): void
    {
        $this->db->insert('notifications', [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }
    
    /**
     * Получить непрочитанные уведомления
     */
    public function getUnreadNotifications(int $limit = 10): array
    {
        return $this->db->fetchAll("
            SELECT * FROM notifications 
            WHERE is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Отметить уведомление как прочитанное
     */
    public function markNotificationRead(int $id): void
    {
        $this->db->update('notifications', ['is_read' => 1], ['id' => $id]);
    }
    
    /**
     * Очистить старые логи
     */
    public function cleanOldLogs(int $daysToKeep = 90): void
    {
        $this->db->query("
            DELETE FROM traffic_logs 
            WHERE recorded_at < datetime('now', '-$daysToKeep days')
        ");
        
        $this->db->query("
            DELETE FROM connection_logs 
            WHERE created_at < datetime('now', '-$daysToKeep days')
        ");
        
        $this->db->query("
            DELETE FROM activity_log 
            WHERE created_at < datetime('now', '-$daysToKeep days')
        ");
        
        $this->db->query("VACUUM");
    }
    
    /**
     * Форматирование байтов в читаемый вид
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
