<?php
// Эта страница вставляется в index.php
use App\Services\StatsService;

$stats = new StatsService();
$dashboardStats = $stats->getDashboardStats();
$recentActivity = $stats->getRecentActivity(10);
$notifications = $stats->getUnreadNotifications(5);
$trafficByDays = $stats->getTrafficByDays(7);
$topClients = $stats->getTrafficByClients(5);

// Подготовка данных для графика
$chartLabels = [];
$chartData = [];
foreach ($trafficByDays as $day) {
    $chartLabels[] = date('d.m', strtotime($day['date']));
    $chartData[] = round($day['total'] / 1024 / 1024, 2); // В МБ
}
?>

<div class="container">
    <!-- Статистические карточки -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🖥</div>
            <div class="stat-info">
                <div class="stat-label">Серверов</div>
                <div class="stat-value"><?= $dashboardStats['active_servers'] ?>/<?= $dashboardStats['total_servers'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <div class="stat-label">Клиентов</div>
                <div class="stat-value"><?= $dashboardStats['active_clients'] ?>/<?= $dashboardStats['total_clients'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <div class="stat-label">Трафик всего</div>
                <div class="stat-value"><?= StatsService::formatBytes($dashboardStats['total_traffic']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🔌</div>
            <div class="stat-info">
                <div class="stat-label">Подключений сегодня</div>
                <div class="stat-value"><?= $dashboardStats['connections_today'] ?></div>
            </div>
        </div>
    </div>
    
    <!-- График трафика -->
    <div class="card">
        <div class="card-header">
            <h2>📈 Трафик за 7 дней</h2>
        </div>
        <div class="chart-container">
            <canvas id="trafficChart"></canvas>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
        <!-- Топ клиентов по трафику -->
        <div class="card">
            <div class="card-header">
                <h2>🏆 Топ клиентов</h2>
            </div>
            <?php if (empty($topClients)): ?>
                <p style="color:#a0aec0; text-align:center; padding:20px;">Нет данных</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Клиент</th>
                            <th>IP</th>
                            <th>Трафик</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topClients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['name']) ?></td>
                            <td><code><?= htmlspecialchars($client['ip_address']) ?></code></td>
                            <td><?= StatsService::formatBytes($client['total_traffic']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Последняя активность -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Последняя активность</h2>
            </div>
            <?php if (empty($recentActivity)): ?>
                <p style="color:#a0aec0; text-align:center; padding:20px;">Нет записей</p>
            <?php else: ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px;">
                        <strong><?= htmlspecialchars($activity['user_name'] ?? 'Система') ?></strong>
                        — <?= htmlspecialchars($activity['action']) ?>
                        <br>
                        <small style="color:#a0aec0;">
                            <?= date('d.m.Y H:i', strtotime($activity['created_at'])) ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .stat-icon {
        font-size: 32px;
        width: 60px;
        height: 60px;
        background: #f0f0ff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .stat-label {
        font-size: 14px;
        color: #718096;
        margin-bottom: 4px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #2d3748;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        padding: 20px;
    }
    
    canvas {
        width: 100% !important;
        height: 100% !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // График трафика
    const ctx = document.getElementById('trafficChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Трафик (MB)',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#667eea',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f0f0f0'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Автообновление каждые 30 секунд
    setTimeout(() => location.reload(), 30000);
</script>
