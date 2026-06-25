<?php
namespace App\Services;

use App\Core\Database;

class ClientService
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Получить всех клиентов сервера
     */
    public function getClientsByServer(int $serverId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM clients WHERE server_id = ? ORDER BY created_at DESC",
            [$serverId]
        );
    }
    
    /**
     * Создать клиента на сервере
     */
    public function createClient(int $serverId, string $clientName): array
    {
        // Валидация имени
        if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $clientName)) {
            throw new \InvalidArgumentException('Имя клиента: 3-32 символа, только буквы, цифры, - и _');
        }
        
        // Проверка уникальности
        $existing = $this->db->fetch(
            "SELECT COUNT(*) as count FROM clients WHERE server_id = ? AND name = ?",
            [$serverId, $clientName]
        );
        
        if ($existing['count'] > 0) {
            throw new \InvalidArgumentException('Клиент с таким именем уже существует');
        }
        
        // Получаем данные сервера
        $server = $this->db->fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
        if (!$server) {
            throw new \InvalidArgumentException('Сервер не найден');
        }
        
        // Подключаемся к серверу и создаём клиента
        $ssh = new SSHService($server);
        $ssh->installWireGuard();
        $clientData = $ssh->createClient($clientName);
        
        // Сохраняем в БД
        $clientId = $this->db->insert('clients', [
            'server_id' => $serverId,
            'name' => $clientName,
            'public_key' => $clientData['public_key'],
            'private_key' => $clientData['private_key'],
            'ip_address' => $clientData['ip_address'],
            'enabled' => 1,
        ]);
        
        // Обновляем статус сервера
        $this->db->update('servers', 
            ['status' => 'active', 'last_check' => date('Y-m-d H:i:s')],
            ['id' => $serverId]
        );
        
        return [
            'id' => $clientId,
            'name' => $clientName,
            'private_key' => $clientData['private_key'],
            'public_key' => $clientData['public_key'],
            'ip_address' => $clientData['ip_address'],
            'server_endpoint' => $clientData['server_endpoint'],
            'server_public_key' => $clientData['server_public_key'],
        ];
    }
    
    /**
     * Удалить клиента
     */
    public function deleteClient(int $clientId): bool
    {
        $client = $this->db->fetch("SELECT c.*, s.* FROM clients c JOIN servers s ON c.server_id = s.id WHERE c.id = ?", [$clientId]);
        if (!$client) {
            throw new \InvalidArgumentException('Клиент не найден');
        }
        
        // Удаляем с сервера
        try {
            $ssh = new SSHService($client);
            $ssh->deleteClient($client['public_key']);
        } catch (\Exception $e) {
            // Логируем ошибку, но продолжаем удаление из БД
            error_log("Ошибка удаления клиента с сервера: " . $e->getMessage());
        }
        
        // Удаляем из БД
        $this->db->delete('clients', ['id' => $clientId]);
        
        return true;
    }
    
    /**
     * Включить/выключить клиента
     */
    public function toggleClient(int $clientId, bool $enabled): bool
    {
        $client = $this->db->fetch(
            "SELECT c.*, s.* FROM clients c JOIN servers s ON c.server_id = s.id WHERE c.id = ?",
            [$clientId]
        );
        
        if (!$client) {
            throw new \InvalidArgumentException('Клиент не найден');
        }
        
        if ($enabled) {
            // Добавляем клиента обратно в конфиг
            $ssh = new SSHService($client);
            $peerConfig = "\n[Peer]\nPublicKey = {$client['public_key']}\nAllowedIPs = {$client['ip_address']}/32\n";
            $ssh->exec("echo '$peerConfig' >> /etc/wireguard/wg0.conf");
            $ssh->exec('systemctl restart wg-quick@wg0');
        } else {
            // Удаляем из конфига
            $ssh = new SSHService($client);
            $ssh->deleteClient($client['public_key']);
        }
        
        $this->db->update('clients', ['enabled' => (int)$enabled], ['id' => $clientId]);
        
        return true;
    }
    
    /**
     * Генерация конфигурационного файла
     */
    public function generateConfig(int $clientId): string
    {
        $client = $this->db->fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);
        if (!$client) {
            throw new \InvalidArgumentException('Клиент не найден');
        }
        
        $server = $this->db->fetch("SELECT * FROM servers WHERE id = ?", [$client['server_id']]);
        
        // Получаем endpoint сервера
        $ssh = new SSHService($server);
        $endpoint = $ssh->exec('curl -s ifconfig.me 2>/dev/null || echo "' . $server['ip_address'] . '"');
        $serverPubKey = $ssh->exec('cat /etc/wireguard/server_public.key');
        
        $config = "# VPN Client: {$client['name']}\n";
        $config .= "# Created: " . date('Y-m-d H:i:s') . "\n\n";
        $config .= "[Interface]\n";
        $config .= "PrivateKey = {$client['private_key']}\n";
        $config .= "Address = {$client['ip_address']}/32\n";
        $config .= "DNS = 1.1.1.1, 8.8.8.8\n\n";
        $config .= "[Peer]\n";
        $config .= "PublicKey = " . trim($serverPubKey) . "\n";
        $config .= "Endpoint = " . trim($endpoint) . ":443\n";
        $config .= "AllowedIPs = 0.0.0.0/0\n";
        $config .= "PersistentKeepalive = 25\n";
        
        return $config;
    }
}
