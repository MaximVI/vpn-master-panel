<?php
namespace App\Services;

use App\Core\Database;

class SSHService
{
    private $connection = null;
    private array $server;
    
    public function __construct(array $server)
    {
        $this->server = $server;
    }
    
    /**
     * Подключение к серверу по SSH
     */
    public function connect(): bool
    {
        if (!function_exists('ssh2_connect')) {
            throw new \RuntimeException('PHP SSH2 extension не установлен. Установите: apt install php8.3-ssh2');
        }
        
        $this->connection = @ssh2_connect(
            $this->server['ip_address'],
            (int)$this->server['ssh_port'],
            ['hostkey' => 'ssh-rsa,ssh-ed25519']
        );
        
        if (!$this->connection) {
            throw new \RuntimeException("Не удалось подключиться к {$this->server['ip_address']}:{$this->server['ssh_port']}");
        }
        
        // Аутентификация
        $authValue = $this->decrypt($this->server['auth_value']);
        
        if ($this->server['auth_type'] === 'password') {
            if (!@ssh2_auth_password($this->connection, $this->server['ssh_username'], $authValue)) {
                throw new \RuntimeException("Неверный пароль для {$this->server['ssh_username']}@{$this->server['ip_address']}");
            }
        } elseif ($this->server['auth_type'] === 'key') {
            $tempFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($tempFile, $authValue);
            chmod($tempFile, 0600);
            
            if (!@ssh2_auth_pubkey_file($this->connection, $this->server['ssh_username'], "$tempFile.pub", $tempFile)) {
                unlink($tempFile);
                throw new \RuntimeException("Неверный SSH ключ");
            }
            unlink($tempFile);
        }
        
        return true;
    }
    
    /**
     * Выполнение команды на сервере
     */
    public function exec(string $command): string
    {
        if (!$this->connection) {
            $this->connect();
        }
        
        $stream = ssh2_exec($this->connection, $command);
        if (!$stream) {
            throw new \RuntimeException("Не удалось выполнить команду: $command");
        }
        
        stream_set_blocking($stream, true);
        $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
        $stream_err = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        
        $output = stream_get_contents($stream_out);
        $error = stream_get_contents($stream_err);
        
        fclose($stream);
        
        if (!empty($error)) {
            throw new \RuntimeException("Ошибка выполнения: $error");
        }
        
        return trim($output);
    }
    
    /**
     * Проверка соединения
     */
    public function testConnection(): array
    {
        try {
            $this->connect();
            $osInfo = $this->exec('cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2');
            $wgInstalled = $this->exec('which wg 2>/dev/null || echo "not_installed"');
            $uptime = $this->exec('uptime -p');
            
            return [
                'success' => true,
                'os' => trim($osInfo, '"'),
                'wireguard' => $wgInstalled !== 'not_installed',
                'uptime' => $uptime,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Установка WireGuard на сервер
     */
    public function installWireGuard(): bool
    {
        $commands = [
            'apt-get update -qq',
            'apt-get install -y -qq wireguard-tools qrencode',
            'echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf',
            'sysctl -p',
            'mkdir -p /etc/wireguard',
        ];
        
        foreach ($commands as $cmd) {
            $this->exec($cmd);
        }
        
        // Генерация ключей сервера если их нет
        $hasKey = $this->exec('[ -f /etc/wireguard/server_private.key ] && echo "yes" || echo "no"');
        
        if ($hasKey === 'no') {
            $privateKey = $this->exec('wg genkey');
            $publicKey = $this->exec("echo '$privateKey' | wg pubkey");
            
            $this->exec("echo '$privateKey' > /etc/wireguard/server_private.key");
            $this->exec("echo '$publicKey' > /etc/wireguard/server_public.key");
            $this->exec('chmod 600 /etc/wireguard/server_private.key');
        }
        
        // Создание конфигурации сервера если её нет
        $hasConfig = $this->exec('[ -f /etc/wireguard/wg0.conf ] && echo "yes" || echo "no"');
        
        if ($hasConfig === 'no') {
            $privateKey = $this->exec('cat /etc/wireguard/server_private.key');
            
            $config = "[Interface]\n";
            $config .= "PrivateKey = $privateKey\n";
            $config .= "Address = 10.8.0.1/24\n";
            $config .= "ListenPort = 443\n";
            $config .= "SaveConfig = true\n";
            $config .= "PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE\n";
            $config .= "PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";
            
            $this->exec("echo '$config' > /etc/wireguard/wg0.conf");
        }
        
        // Запуск службы
        $this->exec('systemctl enable wg-quick@wg0');
        $this->exec('systemctl start wg-quick@wg0 2>/dev/null || systemctl restart wg-quick@wg0');
        
        return true;
    }
    
    /**
     * Создание клиента на сервере
     */
    public function createClient(string $clientName): array
    {
        // Генерация ключей клиента
        $privateKey = $this->exec('wg genkey');
        $publicKey = $this->exec("echo '$privateKey' | wg pubkey");
        
        // Получение следующего IP
        $nextIP = $this->getNextIp();
        
        // Добавление клиента в конфиг
        $peerConfig = "\n[Peer]\nPublicKey = $publicKey\nAllowedIPs = $nextIP/32\n";
        $this->exec("echo '$peerConfig' >> /etc/wireguard/wg0.conf");
        
        // Перезапуск WireGuard
        $this->exec('systemctl restart wg-quick@wg0');
        
        // Получение ключей сервера
        $serverPublicKey = $this->exec('cat /etc/wireguard/server_public.key');
        $serverEndpoint = $this->exec('curl -s ifconfig.me 2>/dev/null || hostname -I | awk "{print \$1}"');
        
        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'ip_address' => $nextIP,
            'server_public_key' => $serverPublicKey,
            'server_endpoint' => trim($serverEndpoint) . ':443',
        ];
    }
    
    /**
     * Удаление клиента с сервера
     */
    public function deleteClient(string $publicKey): bool
    {
        // Удаляем блок с пиром
        $this->exec("sed -i '/PublicKey = $publicKey/,+1d' /etc/wireguard/wg0.conf");
        $this->exec('systemctl restart wg-quick@wg0');
        
        return true;
    }
    
    /**
     * Получение статуса WireGuard
     */
    public function getStatus(): array
    {
        try {
            $status = $this->exec('wg show wg0 2>/dev/null || echo "not_running"');
            
            if ($status === 'not_running') {
                return ['running' => false];
            }
            
            return [
                'running' => true,
                'output' => $status,
            ];
        } catch (\Exception $e) {
            return ['running' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Получение следующего свободного IP
     */
    private function getNextIp(): string
    {
        // Получаем список занятых IP из конфига
        $config = $this->exec('cat /etc/wireguard/wg0.conf 2>/dev/null || echo ""');
        preg_match_all('/AllowedIPs\s*=\s*(\d+\.\d+\.\d+\.\d+)/', $config, $matches);
        $usedIPs = $matches[1] ?? [];
        
        for ($i = 10; $i <= 254; $i++) {
            $ip = "10.8.0.$i";
            if (!in_array($ip, $usedIPs)) {
                return $ip;
            }
        }
        
        throw new \RuntimeException('Нет свободных IP адресов');
    }
    
    /**
     * Дешифрование пароля
     */
    private function decrypt(string $data): string
    {
        $key = hash('sha256', 'vpn-master-panel-secret', true);
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
    
    public function __destruct()
    {
        if ($this->connection) {
            unset($this->connection);
        }
    }
}
