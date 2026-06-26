<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Panel - Дашборд</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f7fafc}
        .header{background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:16px 32px;display:flex;justify-content:space-between;align-items:center}
        .header a{color:rgba(255,255,255,.9);text-decoration:none;margin-left:16px;font-size:14px}
        .container{max-width:1200px;margin:32px auto;padding:0 24px}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:24px}
        .stat-card{background:white;padding:20px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);display:flex;align-items:center;gap:15px}
        .stat-icon{font-size:28px;width:56px;height:56px;background:#f0f0ff;border-radius:12px;display:flex;align-items:center;justify-content:center}
        .stat-value{font-size:24px;font-weight:700;color:#2d3748}
        .stat-label{color:#718096;font-size:13px;margin-top:2px}
        .card{background:white;padding:24px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        table{width:100%;border-collapse:collapse}
        th{text-align:left;padding:12px;background:#f7fafc;border-bottom:2px solid #e2e8f0;font-size:13px;color:#4a5568}
        td{padding:12px;border-bottom:1px solid #e2e8f0;font-size:14px}
        .btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;color:white;font-size:13px;margin-right:4px}
        .btn-primary{background:#667eea}.btn-primary:hover{background:#5a67d8}
        .btn-danger{background:#fc8181}.btn-danger:hover{background:#f56565}
        .btn-info{background:#4299e1}.btn-info:hover{background:#3182ce}
        .btn-success{background:#48bb78}
        .btn-sm{padding:6px 12px;font-size:12px}
        code{background:#edf2f7;padding:2px 6px;border-radius:4px;font-size:12px}
        .badge{padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600}
        .badge-success{background:#c6f6d5;color:#22543d}
        .badge-warning{background:#fefcbf;color:#744210}
        .badge-danger{background:#fed7d7;color:#742a2a}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:1000}
        .modal.active{display:flex}
        .modal-content{background:white;padding:32px;border-radius:12px;max-width:500px;width:90%}
        .modal-content h2{margin-bottom:20px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:4px;font-weight:500;color:#4a5568;font-size:14px}
        .form-group input{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
        .btn-cancel{background:#e2e8f0;color:#4a5568}
        .empty{text-align:center;padding:60px 20px;color:#a0aec0}
        .empty-icon{font-size:48px;margin-bottom:10px}
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-size:20px;">🔐 VPN Master Panel</h1>
        <div>
            <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
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
                    <p style="margin-top:8px;">Нажмите "Добавить сервер"</p>
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
                        <?php foreach ($servers as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($s['ip_address']) ?></code></td>
                            <td><?= $s['ssh_port'] ?></td>
                            <td>
                                <span class="badge <?= $s['status']==='active'?'badge-success':($s['status']==='error'?'badge-danger':'badge-warning') ?>">
                                    <?= htmlspecialchars($s['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y', strtotime($s['created_at'])) ?></td>
                            <td>
                                <a href="/server/<?= $s['id'] ?>/clients" class="btn btn-info btn-sm">👥 Клиенты</a>
                                <button class="btn btn-success btn-sm" onclick="testServer(<?= $s['id'] ?>)">🔍 Тест</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteServer(<?= $s['id'] ?>)">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Модальное окно -->
    <div class="modal" id="addServerModal">
        <div class="modal-content">
            <h2>Добавить сервер</h2>
            <form id="addServerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group">
                    <label>Название</label>
                    <input type="text" name="name" required placeholder="Германия-1">
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
        const csrf = '<?= $_SESSION['csrf_token'] ?>';
        
        function openAddServerModal() { document.getElementById('addServerModal').classList.add('active'); }
        function closeAddServerModal() { document.getElementById('addServerModal').classList.remove('active'); }
        
        document.getElementById('addServerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            
            try {
                const r = await fetch('/api/add-server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) location.reload();
                else alert('Ошибка: ' + d.message);
            } catch(err) { alert('Ошибка соединения'); }
        });
        
        async function testServer(id) {
            const btn = event.target;
            btn.textContent = '⏳';
            btn.disabled = true;
            
            const fd = new FormData();
            fd.append('id', id);
            
            try {
                const r = await fetch('/api/test-server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) {
                    alert('✅ Сервер доступен!\n\n' + d.info);
                    location.reload();
                } else {
                    alert('❌ ' + d.message);
                }
            } catch(err) { alert('Ошибка'); }
            
            btn.textContent = '🔍 Тест';
            btn.disabled = false;
        }
        
        async function deleteServer(id) {
            if (!confirm('Удалить сервер? Все клиенты будут удалены!')) return;
            
            const fd = new FormData();
            fd.append('id', id);
            fd.append('csrf_token', csrf);
            
            try {
                const r = await fetch('/api/delete-server', { method:'POST', body:fd });
                const d = await r.json();
                if (d.success) location.reload();
                else alert(d.message);
            } catch(err) { alert('Ошибка'); }
        }
    </script>
</body>
</html>
