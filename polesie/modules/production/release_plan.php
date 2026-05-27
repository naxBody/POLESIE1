<?php
/**
 * План выпуска - все заказы и производственные задания с материалами
 * Отображение полной информации по производству
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'План выпуска';

// Получение всех заказов с информацией о производстве
$orders = $pdo->query("
    SELECT o.*, c.name as contractor_name, u.full_name as responsible_name,
           COUNT(oi.id) as items_count,
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
    JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.order_date DESC, o.created_at DESC
")->fetchAll();

// Получение производственных заданий (если таблица production_orders существует)
$productionOrders = [];
try {
    $productionOrders = $pdo->query("
        SELECT po.*, p.name as product_name, p.article,
               o.order_number, c.name as customer_name,
               u.full_name as responsible_name,
               CASE po.status
                   WHEN 'planned' THEN 'Запланировано'
                   WHEN 'in_progress' THEN 'В работе'
                   WHEN 'on_hold' THEN 'Приостановлено'
                   WHEN 'completed' THEN 'Завершено'
                   WHEN 'cancelled' THEN 'Отменено'
                   ELSE po.status
               END as status_name,
               CASE po.status
                   WHEN 'planned' THEN '#3498db'
                   WHEN 'in_progress' THEN '#f39c12'
                   WHEN 'on_hold' THEN '#e74c3c'
                   WHEN 'completed' THEN '#27ae60'
                   WHEN 'cancelled' THEN '#95a5a6'
                   ELSE '#95a5a6'
               END as status_color,
               (SELECT COUNT(*) FROM production_order_stages pos 
                WHERE pos.production_order_id = po.id AND pos.status = 'completed') as completed_stages,
               (SELECT COUNT(*) FROM production_order_stages pos 
                WHERE pos.production_order_id = po.id) as total_stages
        FROM production_orders po
        JOIN products p ON po.product_id = p.id
        LEFT JOIN orders o ON po.order_id = o.id
        LEFT JOIN contractors c ON o.customer_id = c.id
        LEFT JOIN users u ON po.responsible_id = u.id
        ORDER BY po.deadline ASC, po.priority ASC, po.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Таблица еще не создана
}

// Общая потребность в материалах по всем заказам
$totalMaterials = [];
try {
    $totalMaterials = $pdo->query("
        SELECT pom.*, m.name_full as material_name, m.code, m.current_stock,
               CASE 
                   WHEN pom.required_quantity > m.current_stock THEN 'shortage'
                   WHEN pom.required_quantity > pom.reserved_quantity THEN 'partial'
                   ELSE 'sufficient'
               END as stock_status,
               SUM(pom.required_quantity) OVER (PARTITION BY pom.material_id) as total_required_by_material
        FROM production_order_materials pom
        JOIN materials m ON pom.material_id = m.id
        ORDER BY m.name_full
    ")->fetchAll();
} catch (Exception $e) {
    // Таблица еще не создана
}

// Агрегированная потребность по материалам
$aggregatedMaterials = [];
try {
    $aggregatedMaterials = $pdo->query("
        SELECT m.id, m.code, m.name_full, m.current_stock, m.base_unit_id,
               SUM(pom.required_quantity) as total_required,
               SUM(pom.reserved_quantity) as total_reserved,
               SUM(pom.issued_quantity) as total_issued,
               SUM(pom.total_cost) as total_cost,
               CASE 
                   WHEN SUM(pom.required_quantity) > m.current_stock THEN 'shortage'
                   WHEN SUM(pom.required_quantity) > SUM(pom.reserved_quantity) THEN 'partial'
                   ELSE 'sufficient'
               END as stock_status
        FROM production_order_materials pom
        JOIN materials m ON pom.material_id = m.id
        GROUP BY m.id, m.code, m.name_full, m.current_stock, m.base_unit_id
        ORDER BY total_required DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Таблица еще не создана
}

// Статистика
$stats = [
    'total_orders' => count($orders),
    'orders_in_work' => 0,
    'production_orders' => count($productionOrders),
    'production_in_progress' => 0,
    'materials_shortage' => 0
];

foreach ($orders as $order) {
    if ($order['status'] === 'processing') {
        $stats['orders_in_work']++;
    }
}

foreach ($productionOrders as $po) {
    if ($po['status'] === 'in_progress') {
        $stats['production_in_progress']++;
    }
}

foreach ($aggregatedMaterials as $mat) {
    if ($mat['stock_status'] === 'shortage') {
        $stats['materials_shortage']++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .nav-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }
        .nav-tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.2s;
            margin-bottom: -2px;
        }
        .nav-tab:hover {
            color: var(--primary-color);
        }
        .nav-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .stage-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .stage-pending { background: #f3f4f6; color: #6b7280; }
        .stage-in-progress { background: #fef3c7; color: #d97706; }
        .stage-completed { background: #d1fae5; color: #059669; }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transition: width 0.3s;
        }
        .material-shortage {
            background: #fef2f2 !important;
        }
        .stock-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-sufficient { background: #d1fae5; color: #059669; }
        .status-partial { background: #fef3c7; color: #d97706; }
        .status-shortage { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>📋 План выпуска</h2>
                            <p>Все заказы и производственные задания</p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-outline" onclick="window.print()">🖨️ Печать</button>
                            <button class="btn btn-secondary" onclick="exportToCSV()">📥 Экспорт</button>
                        </div>
                    </div>

                    <!-- KPI карточки -->
                    <div class="stats-grid" style="margin-bottom: 24px;">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-card-value"><?= $stats['total_orders'] ?></div>
                                    <div class="stat-card-label">Всего заказов</div>
                                </div>
                                <div class="stat-card-icon primary">📦</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-card-value"><?= $stats['orders_in_work'] ?></div>
                                    <div class="stat-card-label">В работе</div>
                                </div>
                                <div class="stat-card-icon warning">⚙️</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-card-value"><?= $stats['production_orders'] ?></div>
                                    <div class="stat-card-label">Производств. заданий</div>
                                </div>
                                <div class="stat-card-icon info">🔧</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-card-value"><?= $stats['production_in_progress'] ?></div>
                                    <div class="stat-card-label">В производстве</div>
                                </div>
                                <div class="stat-card-icon success">✅</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-card-value" style="color: #dc2626;"><?= $stats['materials_shortage'] ?></div>
                                    <div class="stat-card-label">Дефицит материалов</div>
                                </div>
                                <div class="stat-card-icon danger">⚠️</div>
                            </div>
                        </div>
                    </div>

                    <!-- Вкладки навигации -->
                    <div class="nav-tabs">
                        <button class="nav-tab active" onclick="showTab('orders')">📦 Заказы</button>
                        <button class="nav-tab" onclick="showTab('production')">⚙️ Производственные задания</button>
                        <button class="nav-tab" onclick="showTab('materials')">📦 Материалы</button>
                    </div>

                    <!-- Вкладка: Заказы -->
                    <div id="tab-orders" class="tab-content active">
                        <div class="card">
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table" id="ordersTable">
                                        <thead>
                                            <tr>
                                                <th>Номер заказа</th>
                                                <th>Дата</th>
                                                <th>Заказчик</th>
                                                <th>Позиций</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                                <th>Ответственный</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><strong><?= e($order['order_number']) ?></strong></td>
                                                <td><?= formatDate($order['order_date']) ?></td>
                                                <td><?= e($order['contractor_name']) ?></td>
                                                <td><?= $order['items_count'] ?> шт.</td>
                                                <td><?= formatMoney($order['total_amount']) ?></td>
                                                <td>
                                                    <span class="badge" style="background: <?= e($order['status_color']) ?>20; color: <?= e($order['status_color']) ?>">
                                                        <?= e($order['status_name']) ?>
                                                    </span>
                                                </td>
                                                <td><?= e($order['responsible_name'] ?? '—') ?></td>
                                                <td class="table-actions">
                                                    <a href="../orders/view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр">👁️</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Вкладка: Производственные задания -->
                    <div id="tab-production" class="tab-content">
                        <?php if (empty($productionOrders)): ?>
                        <div class="card">
                            <div class="card-body">
                                <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                    Производственные задания пока не созданы. Создайте план производства.
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table" id="productionTable">
                                        <thead>
                                            <tr>
                                                <th>№ Производства</th>
                                                <th>Продукция</th>
                                                <th>Кол-во</th>
                                                <th>Срок</th>
                                                <th>Прогресс</th>
                                                <th>Статус</th>
                                                <th>Этапы</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productionOrders as $po): ?>
                                            <?php 
                                                $progress = $po['total_stages'] > 0 
                                                    ? round(($po['completed_stages'] / $po['total_stages']) * 100) 
                                                    : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?= e($po['production_number']) ?></strong></td>
                                                <td>
                                                    <?= e($po['product_name']) ?><br>
                                                    <small style="color: var(--text-secondary)"><?= e($po['article']) ?></small>
                                                </td>
                                                <td><?= $po['quantity'] ?></td>
                                                <td><?= formatDate($po['deadline']) ?></td>
                                                <td style="width: 15%;">
                                                    <div class="progress-bar-container">
                                                        <div class="progress-bar-fill" style="width: <?= $progress ?>%"></div>
                                                    </div>
                                                    <small><?= $progress ?>%</small>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: <?= e($po['status_color']) ?>20; color: <?= e($po['status_color']) ?>">
                                                        <?= e($po['status_name']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    try {
                                                        $stages = $pdo->prepare("
                                                            SELECT pos.status, pos.operation_name
                                                            FROM production_order_stages pos
                                                            WHERE pos.production_order_id = ?
                                                            ORDER BY pos.sequence_number
                                                        ");
                                                        $stages->execute([$po['id']]);
                                                        $stageList = $stages->fetchAll();
                                                        
                                                        foreach ($stageList as $stage) {
                                                            $class = 'stage-' . str_replace('_', '-', $stage['status']);
                                                            echo '<span class="stage-badge ' . $class . '">' . e($stage['operation_name']) . '</span>';
                                                        }
                                                    } catch (Exception $e) {}
                                                    ?>
                                                </td>
                                                <td class="table-actions">
                                                    <a href="production_view.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-secondary" title="Подробнее">👁️</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Вкладка: Материалы -->
                    <div id="tab-materials" class="tab-content">
                        <?php if (empty($aggregatedMaterials)): ?>
                        <div class="card">
                            <div class="card-body">
                                <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                    Информация о материалах для производственных заказов пока не доступна.
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table" id="materialsTable">
                                        <thead>
                                            <tr>
                                                <th>Материал</th>
                                                <th>Код</th>
                                                <th>Требуется всего</th>
                                                <th>Зарезервировано</th>
                                                <th>Выдано</th>
                                                <th>На складе</th>
                                                <th>Статус</th>
                                                <th>Стоимость</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aggregatedMaterials as $mat): ?>
                                            <tr class="<?= $mat['stock_status'] === 'shortage' ? 'material-shortage' : '' ?>">
                                                <td><strong><?= e($mat['name_full']) ?></strong></td>
                                                <td><?= e($mat['code']) ?></td>
                                                <td><?= number_format($mat['total_required'], 3, ',', ' ') ?></td>
                                                <td><?= number_format($mat['total_reserved'], 3, ',', ' ') ?></td>
                                                <td><?= number_format($mat['total_issued'], 3, ',', ' ') ?></td>
                                                <td><?= number_format($mat['current_stock'], 3, ',', ' ') ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = 'status-' . $mat['stock_status'];
                                                    $statusText = [
                                                        'sufficient' => '✓ Достаточно',
                                                        'partial' => '⚠ Частично',
                                                        'shortage' => '✗ Дефицит'
                                                    ][$mat['stock_status']];
                                                    ?>
                                                    <span class="stock-status <?= $statusClass ?>">
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td><?= formatMoney($mat['total_cost']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        function showTab(tabName) {
            // Убираем активный класс со всех вкладок
            document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Добавляем активный класс выбранной вкладке
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }
        
        function exportToCSV() {
            alert('Функция экспорта будет реализована');
        }
    </script>
</body>
</html>
