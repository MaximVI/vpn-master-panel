<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Panel - Дашборд</title>
    <style>
        :root {
            --bg: #f7fafc;
            --card-bg: #ffffff;
            --text: #2d3748;
            --text-secondary: #718096;
            --border: #e2e8f0;
            --primary: #667eea;
            --primary-hover: #5a67d8;
            --danger: #fc8181;
            --danger-hover: #f56565;
            --success: #48bb78;
            --info: #4299e1;
            --warning: #ecc94b;
            --header-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --stat-icon-bg: #f0f0ff;
            --code-bg: #edf2f7;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --badge-success-bg: #c6f6d5;
            --badge-success-text: #22543d;
            --badge-warning-bg: #fefcbf;
            --badge-warning-text: #744210;
            --badge-danger-bg: #fed7d7;
            --badge-danger-text: #742a2a;
            --modal-overlay: rgba(0,0,0,0.5);
        }
        
        [data-theme="dark"] {
            --bg: #1a202c;
            --card-bg: #2d3748;
            --text: #e2e8f0;
            --text-secondary: #a0aec0;
            --border: #4a5568;
            --primary: #7f9cf5;
            --primary-hover: #667eea;
            --danger: #fc8181;
            --danger-hover: #f56565;
            --success: #68d391;
            --info: #63b3ed;
            --warning: #f6e05e;
            --header-bg: linear-gradient(135deg, #4c51bf 0%, #6b46c1 100%);
            --stat-icon-bg: #2d3748;
            --code-bg: #1a202c;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --badge-success-bg: #22543d;
            --badge-success-text: #c6f6d5;
            --badge-warning-bg: #744210;
            --badge-warning-text: #fefcbf;
            --badge-danger-bg: #742a2a;
            --badge-danger-text: #fed7d7;
            --modal-overlay: rgba(0,0,0,0.7);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: all 0.3s;
        }
        
        .header {
            background: var(--header-bg);
            color: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background 0.3s;
        }
        
        .header h1 { font-size: 20px; }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
        }
        
        .header a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
        }
        
        .header a:hover { color: white; }
        
        .theme-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .theme-toggle:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 28px;
            width: 56px;
            height: 56px;
            background: var(--stat-icon-bg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 2px;
        }
        
        .card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            transition: all 0.3s;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .card-header h2 {
            color: var(--text);
            font-size: 18px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: var(--bg);
            border-bottom: 2px solid var(--border);
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 600;
            transition: all 0.3s;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
            transition: all 0.3s;
        }
        
        tr:hover td {
            background: var(--bg);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            color: white;
            font-size: 13px;
            margin-right: 4px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-primary { background: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: var(--danger-hover); }
        .btn-info { background: var(--info); }
        .btn-success { background: var(--success); }
        
        code {
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: var(--text);
            transition: all 0.3s;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-success { background: var(--badge-success-bg); color: var(--badge-success-text); }
        .badge-warning { background: var(--badge-warning-bg); color: var(--badge-warning-text); }
        .badge-danger { background: var(--badge-danger-bg); color: var(--badge-danger-text); }
        
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-icon { font-size: 48px; margin-bottom: 10px; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--modal-overlay);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: var(--card-bg);
            padding: 32px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transition: all 0.3s;
        }
        
        .modal-content h2 {
            color: var(--text);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-cancel {
            background: var(--border);
            color: var(--text);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 VPN Master Panel</h1>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()" title="Переключить тему">
                <span id="themeIcon">🌙</span>
            </button>
            <span><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span>
            <a href="/logout">Выйти</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">🖥</div>
                <div>
                    <div class="stat-value"><?= count($servers ?? []) ?></div>
                    <div class="stat-label">Всего серверов</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div>
                    <div class="stat-value"><?= $activeServers ?? 0 ?></div>
                    <div class="stat-label">Активных</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div>
                    <div class="stat-value"><?= $clientsCount ?? 0 ?></div>
                    <div class="stat-label">Клиентов</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📡</div>
                <div>
                    <div class="stat-value">Online</div>
                    <div class="stat-label">Статус панели</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>🖥 Серверы VPN</h2>
                <button class="btn btn-primary" onclick="openAddServerModal()">+ Добавить сервер</button>
            </div>
            
            <?php if (empty($servers)): ?>
                <div class="empty">
                    <div class="empty-icon">🖥</div>
                    <p style="font-size:16px;">Нет добавленных серверов</p>
                    <p style="margin-top:8px;font-size:14px;">Нажмите "Добавить сервер" чтобы начать работу</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>IP-адрес</th>
                            <th>SSH</th>
                            <th>Статус</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($server['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($server['ip_address']) ?></code></td>
                            <td><?= $server['ssh_port'] ?></td>
                            <td>
                                <span class="badge <?= $server['status'] === 'active' ? 'badge-success' : ($server['status'] === 'error' ? 'badge-danger' : 'badge-warning') ?>">
                                    <?= htmlspecialchars($server['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y', strtotime($server['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-info" onclick="testServer(<?= $server['id'] ?>)">🔍 Тест</button>
                                <button class="btn btn-danger" onclick="deleteServer(<?= $server['id'] ?>)">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Модальное окно добавления сервера -->
    <div class="modal" id="addServerModal">
        <div class="modal-content">
            <h2>Добавить сервер</h2>
            <form id="addServerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group">
                    <label>Название</label>
                    <input type="text" name="name" required placeholder="Например: Германия-1">
                </div>
                <div class="form-group">
                    <label>IP-адрес</label>
                    <input type="text" name="ip_address" required placeholder="123.45.67.89">
                </div>
                <div class="form-group">
                    <label>SSH порт</label>
                    <input type="number" name="ssh_port" value="22">
                </div>
                <div class="form-group">
                    <label>Пользователь</label>
                    <input type="text" name="ssh_username" value="root">
                </div>
                <div class="form-group">
                    <label>Пароль root</label>
                    <input type="password" name="auth_value" required placeholder="••••••••">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeAddServerModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Тёмная тема
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon();
        
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon();
        }
        
        function updateThemeIcon() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeIcon').textContent = theme === 'dark' ? '☀️' : '🌙';
        }
        
        // Управление серверами
        const csrf = '<?= $_SESSION['csrf_token'] ?>';
        
        function openAddServerModal() {
            document.getElementById('addServerModal').classList.add('active');
        }
        
        function closeAddServerModal() {
            document.getElementById('addServerModal').classList.remove('active');
        }
        
        document.getElementById('addServerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('action', 'add_server');
            
            try {
                const r = await fetch('/api.php?action=add_server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) location.reload();
                else alert('Ошибка: ' + d.message);
            } catch(err) {
                alert('Ошибка соединения');
            }
        });
        
        async function testServer(id) {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('csrf_token', csrf);
            
            try {
                const r = await fetch('/api.php?action=test_server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) {
                    alert('✅ Сервер доступен!\n\nОС: ' + d.os + '\nWireGuard: ' + (d.wireguard?'✅':'❌'));
                    location.reload();
                } else {
                    alert('❌ ' + d.message);
                }
            } catch(err) {
                alert('Ошибка');
            }
        }
        
        async function deleteServer(id) {
            if (!confirm('Удалить сервер? Все клиенты будут удалены!')) return;
            
            const fd = new FormData();
            fd.append('id', id);
            fd.append('csrf_token', csrf);
            
            try {
                const r = await fetch('/api.php?action=delete_server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) location.reload();
                else alert(d.message);
            } catch(err) {
                alert('Ошибка');
            }
        }
    </script>
</body>
</html>
