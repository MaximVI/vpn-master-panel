<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Клиенты - <?= htmlspecialchars($server['name']) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f7fafc}
        .header{background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:16px 32px;display:flex;justify-content:space-between;align-items:center}
        .header a{color:rgba(255,255,255,.9);text-decoration:none;margin-left:16px;font-size:14px}
        .header a:hover{color:white}
        .container{max-width:1200px;margin:32px auto;padding:0 24px}
        .breadcrumb{margin-bottom:20px;font-size:14px}
        .breadcrumb a{color:#667eea;text-decoration:none}
        .card{background:white;padding:24px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);margin-bottom:24px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        table{width:100%;border-collapse:collapse}
        th{text-align:left;padding:12px;background:#f7fafc;border-bottom:2px solid #e2e8f0;font-size:13px;color:#4a5568}
        td{padding:12px;border-bottom:1px solid #e2e8f0;font-size:14px}
        .btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-weight:500;text-decoration:none;display:inline-block;color:white;font-size:13px;margin-right:4px}
        .btn-primary{background:#667eea}.btn-primary:hover{background:#5a67d8}
        .btn-danger{background:#fc8181}.btn-danger:hover{background:#f56565}
        .btn-success{background:#48bb78}
        .btn-info{background:#4299e1}
        .btn-sm{padding:6px 12px;font-size:12px}
        code{background:#edf2f7;padding:2px 6px;border-radius:4px;font-size:12px}
        .badge{padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600}
        .badge-success{background:#c6f6d5;color:#22543d}
        .badge-danger{background:#fed7d7;color:#742a2a}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:1000}
        .modal.active{display:flex}
        .modal-content{background:white;padding:32px;border-radius:12px;max-width:450px;width:90%}
        .modal-content h2{margin-bottom:20px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:4px;font-weight:500;color:#4a5568;font-size:14px}
        .form-group input{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px}
        .form-group small{color:#a0aec0;font-size:12px}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
        .btn-cancel{background:#e2e8f0;color:#4a5568}
        .empty{text-align:center;padding:60px 20px;color:#a0aec0}
        .empty-icon{font-size:48px;margin-bottom:10px}
        .server-info{padding:12px;background:#f7fafc;border-radius:8px;margin-bottom:20px;font-size:14px}
        .server-info code{font-size:13px}
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-size:20px;">🔐 <?= htmlspecialchars($server['name']) ?></h1>
        <div>
            <a href="/dashboard">← К серверам</a>
            <a href="/logout">Выйти</a>
        </div>
    </div>
    
    <div class="container">
        <div class="server-info">
            <strong>IP:</strong> <code><?= htmlspecialchars($server['ip_address']) ?></code>
            <strong style="margin-left:20px;">SSH:</strong> <?= $server['ssh_port'] ?>
            <strong style="margin-left:20px;">Статус:</strong>
            <span class="badge <?= $server['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                <?= htmlspecialchars($server['status']) ?>
            </span>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>👥 Клиенты VPN</h2>
                <button class="btn btn-primary" onclick="openAddClientModal()">+ Добавить клиента</button>
            </div>
            
            <?php if (empty($clients)): ?>
                <div class="empty">
                    <div class="empty-icon">👤</div>
                    <p style="font-size:16px;">Нет клиентов</p>
                    <p style="margin-top:8px;">Создайте первого клиента</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>IP-адрес</th>
                            <th>Публичный ключ</th>
                            <th>Статус</th>
                            <th>Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($client['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($client['ip_address']) ?></code></td>
                            <td><code><?= substr($client['public_key'], 0, 16) ?>...</code></td>
                            <td>
                                <span class="badge <?= $client['enabled'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $client['enabled'] ? 'Активен' : 'Отключен' ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y', strtotime($client['created_at'])) ?></td>
                            <td>
                                <a href="/client/<?= $client['id'] ?>/config" class="btn btn-info btn-sm">📥 Конфиг</a>
                                <a href="/client/<?= $client['id'] ?>/qr" class="btn btn-info btn-sm" target="_blank">📱 QR</a>
                                <button onclick="deleteClient(<?= $client['id'] ?>)" class="btn btn-danger btn-sm">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Модальное окно добавления клиента -->
    <div class="modal" id="addClientModal">
        <div class="modal-content">
            <h2>Новый клиент</h2>
            <form id="addClientForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="server_id" value="<?= $serverId ?>">
                
                <div class="form-group">
                    <label>Имя клиента</label>
                    <input type="text" name="client_name" required 
                           pattern="[a-zA-Z0-9_-]{3,32}"
                           placeholder="my-phone">
                    <small>3-32 символа: буквы, цифры, -, _</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeAddClientModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddClientModal() {
            document.getElementById('addClientModal').classList.add('active');
        }
        function closeAddClientModal() {
            document.getElementById('addClientModal').classList.remove('active');
        }
        
        document.getElementById('addClientForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            
            try {
                const r = await fetch('/api/create-client', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) location.reload();
                else alert('Ошибка: ' + d.message);
            } catch(err) {
                alert('Ошибка соединения');
            }
        });
        
        async function deleteClient(id) {
            if (!confirm('Удалить клиента?')) return;
            // Заглушка — добавим API позже
            alert('Удаление будет добавлено позже');
        }
    </script>
</body>
</html>
