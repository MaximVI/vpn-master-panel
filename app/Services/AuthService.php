<?php
namespace App\Services;

use App\Core\Database;

class AuthService
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function login(string $email, string $password): array
    {
        $user = $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Неверный email или пароль'];
        }
        
        if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            $this->db->update('users', ['password' => $newHash], ['id' => $user['id']]);
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            ['id' => $user['id']]
        );
        
        return ['success' => true, 'user' => $user];
    }
    
    public function logout(): void
    {
        session_destroy();
    }
    
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
    
    public function changePassword(int $userId, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('Пароль должен быть не менее 8 символов');
        }
        
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        return $this->db->update('users', ['password' => $hash], ['id' => $userId]) > 0;
    }
}
