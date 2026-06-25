<?php
namespace App\Core;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;
    
    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';
        $dbPath = $config['database'];
        
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        
        $this->pdo = new \PDO(
            "sqlite:$dbPath",
            null,
            null,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
    
    public function initTables(): void
    {
        // Основные таблицы
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                name TEXT,
                role TEXT DEFAULT 'admin',
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                ssh_port INTEGER DEFAULT 22,
                ssh_username TEXT DEFAULT 'root',
                auth_type TEXT DEFAULT 'password',
                auth_value TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                last_check DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                public_key TEXT NOT NULL,
                private_key TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                enabled INTEGER DEFAULT 1,
                traffic_used INTEGER DEFAULT 0,
                traffic_limit INTEGER DEFAULT 0,
                last_handshake DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- НОВЫЕ ТАБЛИЦЫ ДЛЯ СТАТИСТИКИ
            
            CREATE TABLE IF NOT EXISTS traffic_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                bytes_received INTEGER DEFAULT 0,
                bytes_sent INTEGER DEFAULT 0,
                total_bytes INTEGER DEFAULT 0,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS connection_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                server_id INTEGER,
                event_type TEXT CHECK(event_type IN ('connect', 'disconnect', 'handshake', 'error')),
                ip_address TEXT,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                description TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );
            
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT CHECK(type IN ('info', 'warning', 'error', 'success')),
                title TEXT NOT NULL,
                message TEXT,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Индексы для быстрых запросов
            CREATE INDEX IF NOT EXISTS idx_traffic_client ON traffic_logs(client_id, recorded_at);
            CREATE INDEX IF NOT EXISTS idx_traffic_date ON traffic_logs(recorded_at);
            CREATE INDEX IF NOT EXISTS idx_connections_client ON connection_logs(client_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_connections_server ON connection_logs(server_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_log(user_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_notifications_unread ON notifications(is_read, created_at);
        ");
        
        // Создаём админа по умолчанию
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $password = password_hash('admin123', PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $this->pdo->prepare(
                "INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)"
            )->execute(['admin@vpn.local', $password, 'Administrator', 'admin']);
        }
    }
    
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }
    
    public function fetchColumn(string $sql, array $params = [], int $column = 0)
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }
    
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, array $where): int
    {
        $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
        $whereCond = implode(' = ? AND ', array_keys($where)) . ' = ?';
        $params = array_merge(array_values($data), array_values($where));
        
        $sql = "UPDATE $table SET $sets WHERE $whereCond";
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete(string $table, array $where): int
    {
        $whereCond = implode(' = ? AND ', array_keys($where)) . ' = ?';
        $sql = "DELETE FROM $table WHERE $whereCond";
        return $this->query($sql, array_values($where))->rowCount();
    }
    
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
