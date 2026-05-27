<?php
/**
 * План выпуска заказов - отображение всех заказов с информацией о производстве
 * Показывает текущий этап, потребность в материалах, статус выполнения
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

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Получение всех заказов с производственной информацией
$sql = "
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.status as order_status,
        o.total_amount,
        c.name as contractor_name,
        u.full_name as responsible_name,
        
        -- Информация о производстве
        COUNT(DISTINCT pt.id) as total_tasks,
        SUM(CASE WHEN pt.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN pt.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        
        -- Прогресс по заказу (в процентах)
        CASE 
            WHEN COUNT(pt.id) = 0 THEN 0
            ELSE ROUND(SUM(CASE WHEN pt.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(pt.id))
        END as progress_percent,
        
        -- Общая информация о продукции
        SUM(oi.quantity) as total_quantity,
        
        -- Потребность в материалах
        COALESCE(mat_req.total_materials, 0) as total_materials_needed,
        COALESCE(mat_req.shortage_count, 0) as shortage_count
        
    FROM orders o
    JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN production_tasks pt ON o.id = pt.order_id
    LEFT JOIN (
        SELECT 
            omr.order_id,
            COUNT(DISTINCT omr.material_id) as total_materials,
            SUM(CASE WHEN omr.status = 'shortage' THEN 1 ELSE 0 END) as shortage_count
        FROM order_material_requirements omr
        GROUP BY omr.order_id
    ) mat_req ON o.id = mat_req.order_id
    
    WHERE o.order_date BETWEEN ? AND ?
";

$params = [$dateFrom, $dateTo];

if ($statusFilter) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY o.id, o.order_number, o.order_date, o.status, o.total_amount, 
                c.name, u.full_name
         ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Статусы заказов для фильтра
$statuses = [
    ['id' => 'new', 'name' => 'Новый', 'color' => '#3498db'],
    ['id' => 'processing', 'name' => 'В работе', 'color' => '#f39c12'],
    ['id' => 'ready', 'name' => 'Готов', 'color' => '#27ae60'],
    ['id' => 'shipped', 'name' => 'Отгружен', 'color' => '#9b59b6'],
    ['id' => 'cancelled', 'name' => 'Отменен', 'color' => '#e74c3c'],
];

// KPI статистика
$totalOrders = count($orders);
$inProgressOrders = 0;
$completedOrders = 0;
$totalShortages = 0;

foreach ($orders as $order) {
    if ($order['order_status'] === 'processing') {
        $inProgressOrders++;
    }
    if ($order['order_status'] === 'ready' || $order['order_status'] === 'shipped') {
        $completedOrders++;
    }
    $totalShortages += $order['shortage_count'];
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
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }
        .kpi-label {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transition: width 0.3s;
        }
        .stage-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }
        .stage-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e5e7eb;
        }
        .stage-dot.active {
            background: #3b82f6;
        }
        .stage-dot.completed {
            background: #10b981;
        }
        .shortage-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #fee2e2;
            color: #dc2626;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
            margin-bottom: 24px;
        }
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
                            <h2>📋 План выпуска заказов</h2>
                            <p>Мониторинг выполнения всех заказов</p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-outline" onclick="window.print()">🖨️ Печать</button>
                            <button class="btn btn-primary" onclick="exportToCSV()">📥 Экспорт</button>
                        </div>
                    </div>

                    <!-- KPI карточки -->
                    <div class="kpi-row">
                        <div class="kpi-card">
                            <div class="kpi-value"><?= $totalOrders ?></div>
                            <div class="kpi-label">Всего заказов</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-value" style="color: #f39c12;"><?= $inProgressOrders ?></div>
                            <div class="kpi-label">В производстве</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-value" style="color: #27ae60;"><?= $completedOrders ?></div>
                            <div class="kpi-label">Выполнено</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-value" style="color: #e74c3c;"><?= $totalShortages ?></div>
                            <div class="kpi-label">Дефицит материалов</div>
                        </div>
                    </div>

                    <!-- Фильтры -->
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="filter-form">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Статус заказа</label>
                                    <select name="status" class="form-control">
                                        <option value="">Все статусы</option>
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $statusFilter == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">С даты</label>
                                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">По дату</label>
                                    <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn btn-primary">Фильтр</button>
                                    <a href="" class="btn btn-secondary">Сброс</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Таблица заказов -->
                    <div class="card">
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table" id="releasePlanTable">
                                    <thead>
                                        <tr>
                                            <th>Заказ</th>
                                            <th>Дата</th>
                                            <th>Заказчик</th>
                                            <th>Продукция</th>
                                            <th>Прогресс</th>
                                            <th>Этап производства</th>
                                            <th>Материалы</th>
                                            <th>Статус</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                                Заказы не найдены
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($orders as $order): ?>
                                            <?php
                                            // Определение текущего этапа
                                            $currentStage = 'Не начато';
                                            $stageStatus = 'pending';
                                            if ($order['total_tasks'] > 0) {
                                                if ($order['completed_tasks'] == $order['total_tasks']) {
                                                    $currentStage = 'Завершено';
                                                    $stageStatus = 'completed';
                                                } elseif ($order['in_progress_tasks'] > 0) {
                                                    $currentStage = 'В производстве';
                                                    $stageStatus = 'in_progress';
                                                } else {
                                                    $currentStage = 'Запланировано';
                                                    $stageStatus = 'planned';
                                                }
                                            }
                                            
                                            // Статус материалов
                                            $materialStatus = '';
                                            if ($order['shortage_count'] > 0) {
                                                $materialStatus = '<span class="shortage-badge">Дефицит: ' . $order['shortage_count'] . '</span>';
                                            } else {
                                                $materialStatus = '<span class="badge badge-success">✓ Доступно</span>';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><a href="view_order.php?id=<?= $order['id'] ?>" style="color: var(--primary-color);"><?= e($order['order_number']) ?></a></strong>
                                                </td>
                                                <td><?= formatDate($order['order_date']) ?></td>
                                                <td><?= e($order['contractor_name']) ?></td>
                                                <td><?= number_format($order['total_quantity'], 0, ',', ' ') ?> шт.</td>
                                                <td style="width: 150px;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <span style="font-size: 12px; font-weight: 600;"><?= $order['progress_percent'] ?>%</span>
                                                        <div class="progress-bar">
                                                            <div class="progress-fill" style="width: <?= $order['progress_percent'] ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="stage-indicator">
                                                        <div class="stage-dot <?= $stageStatus === 'completed' ? 'completed' : ($stageStatus === 'in_progress' ? 'active' : '') ?>"></div>
                                                        <span><?= $currentStage ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= $materialStatus ?>
                                                    <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
                                                        Всего: <?= $order['total_materials_needed'] ?> поз.
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'new' => '#3498db',
                                                        'processing' => '#f39c12',
                                                        'ready' => '#27ae60',
                                                        'shipped' => '#9b59b6',
                                                        'cancelled' => '#e74c3c',
                                                    ];
                                                    $statusNames = [
                                                        'new' => 'Новый',
                                                        'processing' => 'В работе',
                                                        'ready' => 'Готов',
                                                        'shipped' => 'Отгружен',
                                                        'cancelled' => 'Отменен',
                                                    ];
                                                    $color = $statusColors[$order['order_status']] ?? '#95a5a6';
                                                    $name = $statusNames[$order['order_status']] ?? $order['order_status'];
                                                    ?>
                                                    <span class="badge" style="background: <?= $color ?>20; color: <?= $color ?>">
                                                        <?= e($name) ?>
                                                    </span>
                                                </td>
                                                <td class="table-actions">
                                                    <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр">👁️</a>
                                                    <a href="route_map.php?order=<?= $order['id'] ?>" class="btn btn-sm btn-info" title="Маршрутная карта">🗺️</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
    <script>
        function exportToCSV() {
            exportTableToCSV('releasePlanTable', 'plan_vypuska.csv');
        }
    </script>
</body>
</html>
