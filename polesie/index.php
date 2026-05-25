<?php
/**
 * Главный файл входа в систему (Dashboard)
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start();

// Проверка авторизации
if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение статистики
$stats = [];

// Количество заказов
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$stats['total_orders'] = $stmt->fetchColumn();

// Заказы в работе
$stmt = $pdo->query("
    SELECT COUNT(*) FROM orders 
    WHERE status IN ('processing', 'ready')
");
$stats['orders_in_progress'] = $stmt->fetchColumn();

// Производственные задания
$stmt = $pdo->query("SELECT COUNT(*) FROM production_tasks");
$stats['production_orders'] = $stmt->fetchColumn();

// Задания в работе
$stmt = $pdo->query("
    SELECT COUNT(*) FROM production_tasks 
    WHERE status = 'in_progress'
");
$stats['production_active'] = $stmt->fetchColumn();

// Продукция на складе (сумма по всем продуктам)
$stmt = $pdo->query("SELECT COUNT(*) FROM product_serial_numbers WHERE status = 'active'");
$stats['warehouse_products'] = $stmt->fetchColumn() ?? 0;

// Последние заказы
$recentOrders = $pdo->query("
    SELECT o.*, c.name as contractor_name, u.full_name as responsible_name,
        CASE o.status
            WHEN 'new' THEN 'Новый'
            WHEN 'processing' THEN 'В работе'
            WHEN 'ready' THEN 'Готов'
            WHEN 'shipped' THEN 'Отгружен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE o.status
        END as status_name,
        CASE o.status
            WHEN 'new' THEN '#3498db'
            WHEN 'processing' THEN '#f39c12'
            WHEN 'ready' THEN '#27ae60'
            WHEN 'shipped' THEN '#9b59b6'
            WHEN 'cancelled' THEN '#e74c3c'
            ELSE '#95a5a6'
        END as status_color
    FROM orders o
    LEFT JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    ORDER BY o.order_date DESC
    LIMIT 5
")->fetchAll();

// Активные производственные задания
$activeProduction = $pdo->query("
    SELECT pt.*, p.name as product_name, u.full_name as responsible_name,
        CASE pt.status
            WHEN 'planned' THEN 'Запланировано'
            WHEN 'in_progress' THEN 'В работе'
            WHEN 'completed' THEN 'Завершено'
            WHEN 'cancelled' THEN 'Отменено'
            ELSE pt.status
        END as status_name,
        CASE pt.status
            WHEN 'planned' THEN '#3498db'
            WHEN 'in_progress' THEN '#f39c12'
            WHEN 'completed' THEN '#27ae60'
            WHEN 'cancelled' THEN '#e74c3c'
            ELSE '#95a5a6'
        END as status_color
    FROM production_tasks pt
    JOIN products p ON pt.product_id = p.id
    LEFT JOIN users u ON pt.responsible_id = u.id
    WHERE pt.status = 'in_progress'
    ORDER BY pt.start_date DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Панель управления';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
    <div class="app-container">
        <!-- Боковая панель -->
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Верхняя панель -->
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <!-- Контентная область -->
            <div class="content-area">
                <!-- Статистика -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-value"><?= $stats['total_orders'] ?></div>
                                <div class="stat-card-label">Всего заказов</div>
                            </div>
                            <div class="stat-card-icon primary">📦</div>
                        </div>
                        <div class="stat-card-change positive">↑ 12% за месяц</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-value"><?= $stats['orders_in_progress'] ?></div>
                                <div class="stat-card-label">В производстве</div>
                            </div>
                            <div class="stat-card-icon warning">⚙️</div>
                        </div>
                        <div class="stat-card-change positive">Активные заказы</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-value"><?= $stats['production_active'] ?></div>
                                <div class="stat-card-label">Заданий в работе</div>
                            </div>
                            <div class="stat-card-icon info">🔧</div>
                        </div>
                        <div class="stat-card-change positive">Производство</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-value"><?= number_format($stats['warehouse_products'], 0, ',', ' ') ?></div>
                                <div class="stat-card-label">Продукции на складе</div>
                            </div>
                            <div class="stat-card-icon success">📦</div>
                        </div>
                        <div class="stat-card-change negative">↓ 5% за неделю</div>
                    </div>
                </div>
                
                <!-- Две колонки -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Последние заказы -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Последние заказы</h3>
                            <a href="<?= pageUrl('modules/orders/list.php') ?>" class="btn btn-sm btn-secondary">Все заказы</a>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Номер</th>
                                            <th>Заказчик</th>
                                            <th>Статус</th>
                                            <th>Сумма</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><strong><?= e($order['order_number']) ?></strong></td>
                                            <td><?= e($order['contractor_name']) ?></td>
                                            <td>
                                                <span class="badge badge-primary" style="background: <?= e($order['status_color']) ?>20; color: <?= e($order['status_color']) ?>">
                                                    <?= e($order['status_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatMoney($order['total_amount']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Производство -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Производственные задания</h3>
                            <a href="<?= pageUrl('modules/production/list.php') ?>" class="btn btn-sm btn-secondary">Все задания</a>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Номер</th>
                                            <th>Продукция</th>
                                            <th>Статус</th>
                                            <th>Ответственный</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeProduction as $po): ?>
                                        <tr>
                                            <td><strong><?= e($po['production_number']) ?></strong></td>
                                            <td><?= e($po['product_name']) ?></td>
                                            <td>
                                                <span class="badge badge-primary" style="background: <?= e($po['status_color']) ?>20; color: <?= e($po['status_color']) ?>">
                                                    <?= e($po['status_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= e($po['responsible_name'] ?? 'Не назначен') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
