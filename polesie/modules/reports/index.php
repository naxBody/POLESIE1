<?php
/**
 * Отчёты и аналитика
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Отчёты';

// Период для отчётов
$period = $_GET['period'] ?? 'month';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

if ($period === 'week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'month') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-t');
} elseif ($period === 'year') {
    $dateFrom = date('Y-01-01');
    $dateTo = date('Y-12-31');
}

// Статистика по заказам
$orderStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = 'in_production' THEN 1 ELSE 0 END) as production_count,
        SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(total_amount) as total_amount
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
");
$orderStats->execute([$dateFrom, $dateTo]);
$orderData = $orderStats->fetch();

// Статистика по производству
$prodStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as planned_count,
        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as completed_count,
        SUM(quantity_planned) as total_quantity
    FROM production_orders 
    WHERE created_at BETWEEN ? AND ?
");
$prodStats->execute([$dateFrom, $dateTo]);
$prodData = $prodStats->fetch();

// Статистика по качеству
$qualityStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM quality_checks 
    WHERE check_date BETWEEN ? AND ?
");
$qualityStats->execute([$dateFrom, $dateTo]);
$qualityData = $qualityStats->fetch();

// Топ продукции
$topProducts = $pdo->prepare("
    SELECT p.name, SUM(oi.quantity) as total_qty
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY total_qty DESC
    LIMIT 5
");
$topProducts->execute([$dateFrom, $dateTo]);
$topProductsList = $topProducts->fetchAll();

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>📊 Отчёты и аналитика</h2>
            <p>Анализ производственных показателей</p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <select name="period" onchange="this.form.submit()" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Неделя</option>
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Месяц</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Год</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Произвольно</option>
                    </select>
                    
                    <?php if ($period === 'custom'): ?>
                        <input type="date" name="date_from" value="<?= $dateFrom ?>" 
                               style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <span style="padding: 0 10px;">—</span>
                        <input type="date" name="date_to" value="<?= $dateTo ?>" 
                               style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <button type="submit" class="btn btn-secondary">Показать</button>
                    <?php endif; ?>
                </div>
            </form>
            
            <p style="margin-top: 15px; color: var(--text-muted); font-size: 13px;">
                Период: <?= date('d.m.Y', strtotime($dateFrom)) ?> — <?= date('d.m.Y', strtotime($dateTo)) ?>
            </p>
        </div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;">📋</div>
            <div class="stat-content">
                <div class="stat-value"><?= $orderData['total'] ?></div>
                <div class="stat-label">Заказов всего</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0;">🏭</div>
            <div class="stat-content">
                <div class="stat-value"><?= $prodData['total_tasks'] ?></div>
                <div class="stat-label">Производств. заданий</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;">✅</div>
            <div class="stat-content">
                <div class="stat-value"><?= $qualityData['total'] ?? 0 ?></div>
                <div class="stat-label">Проверок ОТК</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #f3e5f5;">💰</div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($orderData['total_amount'] ?? 0, 0, ',', ' ') ?></div>
                <div class="stat-label">Сумма (BYN)</div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        <div class="card">
            <div class="card-header">
                <h3>📋 Статусы заказов</h3>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span>Новые</span>
                    <span class="badge badge-warning"><?= $orderData['new_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>В производстве</span>
                    <span class="badge badge-info"><?= $orderData['production_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>Готовы</span>
                    <span class="badge badge-success"><?= $orderData['ready_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>Завершены</span>
                    <span class="badge badge-secondary"><?= $orderData['completed_count'] ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>🏭 Производство</h3>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span>Планируется</span>
                    <span class="badge badge-warning"><?= $prodData['planned_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>В работе</span>
                    <span class="badge badge-info"><?= $prodData['in_progress_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>Завершено</span>
                    <span class="badge badge-success"><?= $prodData['completed_count'] ?></span>
                </div>
                <div class="stat-row">
                    <span>Всего изделий</span>
                    <strong><?= $prodData['total_quantity'] ?? 0 ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>✅ Контроль качества</h3>
            </div>
            <div class="card-body">
                <?php if (($qualityData['total'] ?? 0) > 0): ?>
                    <div class="stat-row">
                        <span>Пройдено</span>
                        <span class="badge badge-success"><?= $qualityData['passed_count'] ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Не пройдено</span>
                        <span class="badge badge-danger"><?= $qualityData['failed_count'] ?></span>
                    </div>
                    <div class="stat-row">
                        <span>% брака</span>
                        <strong><?= round(($qualityData['failed_count'] / $qualityData['total']) * 100, 1) ?>%</strong>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">Нет данных за выбранный период</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>🔥 Топ продукции</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($topProductsList)): ?>
                    <?php foreach ($topProductsList as $idx => $product): ?>
                    <div class="stat-row">
                        <span><?= $idx + 1 ?>. <?= e($product['name']) ?></span>
                        <strong><?= $product['total_qty'] ?> шт.</strong>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-muted);">Нет данных за выбранный период</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
