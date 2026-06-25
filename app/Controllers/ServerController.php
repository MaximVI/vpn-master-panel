<?php
namespace App\Controllers;

use App\Core\Database;
use App\Services\AuthService;

class ServerController
{
    private Database $db;
    private AuthService $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AuthService();
    }
    
    public function index(): array
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
        
        return $this->db->fetchAll("SELECT * FROM servers ORDER BY created_at DESC");
    }
    
    public function add(array $data): array
    {
        // Валидация
        if (empty($data['name']) || empty($data['ip_address'])) {
            return ['success' => false, 'message' => 'Имя и IP-адрес обязательны'];
        }
        
        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Неверный IP-адрес'];
        }
        
        // Шифруем пароль
        $encrypted = $this->encrypt($data['auth_value']);
        
        try {
            $id = $this->db->insert('servers', [
                'name' => $data['name'],
                'ip_address' => $data['ip_address'],
                'ssh_port' => (int)($data['ssh_port'] ?? 22),
                'ssh_username' => $data['ssh_username'] ?? 'root',
                'auth_type' => $data['auth_type'] ?? 'password',
                'auth_value' => $encrypted,
                'status' => 'pending'
            ]);
            
            return ['success' => true, 'id' => $id, 'message' => 'Сервер добавлен'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }
    
    public function delete(int $id): array
    {
        try {
            $this->db->delete('servers', ['id' => $id]);
            return ['success' => true, 'message' => 'Сервер удалён'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при удалении'];
        }
    }
    
    private function encrypt(string $data): string
    {
        $key = hash('sha256', 'vpn-master-panel-secret', true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}
