<?php
namespace App\Services;

use App\Core\Database;

class CronService
{
    private Database $db;
    private StatsService $stats;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->stats = new StatsService();
    }
    
    /**
     * Сбор статистики со всех серверов
     */
    public function collectAllStats(): array
    {
        $results = [];
        $servers = $this->db->fetchAll("SELECT * FROM servers WHERE status = 'active'");
        
        foreach ($servers as $server) {
            try {
                $result = $this->collectServerStats($server);
                $results[$server['name']] = $result;
                
                // Логируем результат
                $this->stats->createNotification(
                    'info',
                    'Статистика собрана',
                    "Сервер {$server['name']}: {$result['clients_checked']} клиентов проверено"
                );
            } catch (\Exception $e) {
                $results[$server['name']] = ['error' => $e->getMessage()];
                
                $this->stats->createNotification(
                    'error',
                    'Ошибка сбора статистики',
                    "Сервер {$server['name']}: {$e->getMessage()}"
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Сбор статистики с конкретного сервера
     */
    private function collectServerStats(array $server): array
    {
        $ssh = new SSHService($server);
        
        // Получаем статус WireGuard
        $wgOutput = $ssh->exec('wg show wg0 transfer 2>/dev/null || echo ""');
        
        $clientsChecked = 0;
        $lines = explode("\n", trim($wgOutput));
        
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 3) {
                $publicKey = $parts[0];
                $received = (int)$parts[1];
                $sent = (int)$parts[2];
                
                // Находим клиента в БД по публичному ключу
                $client = $this->db->fetch(
                    "SELECT * FROM clients WHERE public_key = ? AND server_id = ?",
                    [$publicKey, $server['id']]
                );
                
                if ($client) {
                    $this->stats->logTraffic($client['id'], $received, $sent);
                    $this->stats->logConnection(
                        $client['id'],
                        $server['id'],
                        'handshake',
                        $server['ip_address'],
                        "Traffic: rx={$received} tx={$sent}"
                    );
                    $clientsChecked++;
                }
            }
        }
        
        // Обновляем статус сервера
        $this->db->update('servers', 
            ['last_check' => date('Y-m-d H:i:s')],
            ['id' => $server['id']]
        );
        
        return [
            'success' => true,
            'clients_checked' => $clientsChecked,
        ];
    }
    
    /**
     * Очистка старых данных
     */
    public function cleanup(): void
    {
        $this->stats->cleanOldLogs(90);
        $this->stats->createNotification('info', 'Очистка выполнена', 'Удалены логи старше 90 дней');
    }
}
