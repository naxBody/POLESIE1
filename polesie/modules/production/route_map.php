<?php
/**
 * Маршрутные карты заказов - детальная информация по этапам производства
 * Показывает текущий этап, выполненные операции, материалы
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Маршрутная карта';
$orderId = (int)($_GET['order'] ?? 0);

if (!$orderId) {
    redirect(pageUrl('modules/production/release_plan.php'));
}

// Получение информации о заказе
$orderSql = "
    SELECT 
        o.*,
        c.name as contractor_name,
        u.full_name as responsible_name,
        CASE o.status
            WHEN 'new' THEN 'Новый'
            WHEN 'processing' THEN 'В работе'
            WHEN 'ready' THEN 'Готов'
            WHEN 'shipped' THEN 'Отгружен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE o.status
        END as status_name
    FROM orders o
    JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    WHERE o.id = ?
";
$stmt = $pdo->prepare($orderSql);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect(pageUrl('modules/production/release_plan.php'));
}

// Позиции заказа
$itemsSql = "
    SELECT oi.*, p.name as product_name, p.article,
           pt.id as task_id, pt.status as task_status,
           rm.id as route_map_id, rm.route_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN production_tasks pt ON oi.product_id = pt.product_id AND pt.order_id = o.id
    LEFT JOIN route_maps rm ON p.id = rm.product_id
    WHERE oi.order_id = ?
";
$stmt = $pdo->prepare($itemsSql);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Этапы производства для каждого изделия
$stagesSql = "
    SELECT 
        ps.id as stage_id,
        ps.stage_name,
        ps.stage_code,
        ps.sequence_order,
        rms.duration_hours,
        wc.name as work_center_name,
        psp.status as progress_status,
        psp.started_at,
        psp.completed_at,
        psp.worker_id,
        psp.notes,
        u2.full_name as worker_name,
        pt.id as task_id,
        pt.quantity_plan,
        pt.quantity_fact
    FROM production_stages ps
    JOIN route_map_stages rms ON ps.id = rms.stage_id
    JOIN route_maps rm ON rms.route_map_id = rm.id
    LEFT JOIN production_stage_progress psp ON ps.id = psp.stage_id AND psp.task_id = pt.id
    LEFT JOIN work_centers wc ON rms.work_center_id = wc.id
    LEFT JOIN users u2 ON psp.worker_id = u2.id
    LEFT JOIN production_tasks pt ON rm.id = pt.route_map_id AND pt.order_id = ?
    WHERE rm.product_id = ?
    ORDER BY ps.sequence_order ASC
";

// Материалы для заказа
$materialsSql = "
    SELECT 
        omr.material_id,
        m.name_full as material_name,
        m.code as material_code,
        m.current_stock,
        omr.required_quantity,
        omr.available_quantity,
        omr.shortage_quantity,
        omr.status as material_status,
        mc.name as category_name
    FROM order_material_requirements omr
    JOIN materials m ON omr.material_id = m.id
    LEFT JOIN material_categories mc ON m.category_id = mc.id
    WHERE omr.order_id = ?
    ORDER BY mc.name, m.name_full
";
$stmt = $pdo->prepare($materialsSql);
$stmt->execute([$orderId]);
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($order['order_number']) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .route-map-container {
            display: grid;
            gap: 24px;
        }
        .order-info-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        .stage-timeline {
            position: relative;
            padding: 20px 0;
        }
        .stage-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            position: relative;
        }
        .stage-item:last-child {
            margin-bottom: 0;
        }
        .stage-marker {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 40px;
        }
        .stage-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e5e7eb;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e5e7eb;
            z-index: 1;
        }
        .stage-dot.pending {
            background: #e5e7eb;
            box-shadow: 0 0 0 2px #e5e7eb;
        }
        .stage-dot.in_progress {
            background: #3b82f6;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        .stage-dot.completed {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }
        .stage-line {
            flex: 1;
            width: 2px;
            background: #e5e7eb;
            margin-top: 8px;
        }
        .stage-item:last-child .stage-line {
            display: none;
        }
        .stage-content {
            flex: 1;
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
        }
        .stage-title {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .stage-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .material-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid var(--border-color);
        }
        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .material-name {
            font-weight: 600;
            font-size: 14px;
        }
        .material-code {
            font-size: 11px;
            color: var(--text-secondary);
        }
        .material-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .status-available {
            background: #d1fae5;
            color: #059669;
        }
        .status-shortage {
            background: #fee2e2;
            color: #dc2626;
        }
        .status-partial {
            background: #fef3c7;
            color: #d97706;
        }
        .material-quantities {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }
        .qty-required {
            color: var(--text-secondary);
        }
        .qty-available {
            color: #059669;
            font-weight: 600;
        }
        .qty-shortage {
            color: #dc2626;
            font-weight: 600;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
        }
        .back-btn:hover {
            color: var(--primary-color);
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
                    <a href="release_plan.php" class="back-btn">
                        ← Назад к плану выпуска
                    </a>
                    
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>🗺️ Маршрутная карта</h2>
                            <p><?= e($order['order_number']) ?> от <?= formatDate($order['order_date']) ?></p>
                        </div>
                    </div>

                    <!-- Информация о заказе -->
                    <div class="order-info-card">
                        <div class="order-header">
                            <div>
                                <h3 style="margin-bottom: 8px;"><?= e($order['order_number']) ?></h3>
                                <p style="color: var(--text-secondary);">
                                    Заказчик: <?= e($order['contractor_name']) ?> | 
                                    Ответственный: <?= e($order['responsible_name'] ?? 'Не назначен') ?>
                                </p>
                            </div>
                            <div>
                                <span class="badge badge-primary" style="font-size: 14px;">
                                    <?= e($order['status_name']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <div class="detail-label">Всего позиций</div>
                                <div class="detail-value"><?= count($items) ?> шт.</div>
                            </div>
                            <div>
                                <div class="detail-label">Сумма заказа</div>
                                <div class="detail-value"><?= formatMoney($order['total_amount']) ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Материалов требуется</div>
                                <div class="detail-value"><?= count($materials) ?> поз.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Этапы производства -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">⚙️ Этапы производства</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($items)): ?>
                                <div class="empty-state">
                                    <p>Позиции в заказе не найдены</p>
                                </div>
                            <?php else: ?>
                                <?php
                                // Получаем этапы для первой позиции (для примера)
                                $firstItem = $items[0];
                                if ($firstItem['route_map_id']) {
                                    $stmt = $pdo->prepare($stagesSql);
                                    $stmt->execute([$orderId, $firstItem['product_id']]);
                                    $stages = $stmt->fetchAll();
                                    
                                    if (!empty($stages)):
                                ?>
                                <div class="stage-timeline">
                                    <?php foreach ($stages as $stage): ?>
                                    <div class="stage-item">
                                        <div class="stage-marker">
                                            <div class="stage-dot <?= $stage['progress_status'] ?? 'pending' ?>"></div>
                                            <div class="stage-line"></div>
                                        </div>
                                        <div class="stage-content">
                                            <div class="stage-title">
                                                <?= e($stage['stage_name']) ?>
                                                <?php if ($stage['progress_status'] === 'completed'): ?>
                                                    <span class="badge badge-success" style="font-size: 11px; margin-left: 8px;">✓ Завершено</span>
                                                <?php elseif ($stage['progress_status'] === 'in_progress'): ?>
                                                    <span class="badge badge-info" style="font-size: 11px; margin-left: 8px;">⏳ В работе</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="stage-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">Код этапа</span>
                                                    <span class="detail-value"><?= e($stage['stage_code']) ?></span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">Длительность</span>
                                                    <span class="detail-value"><?= $stage['duration_hours'] ?> ч.</span>
                                                </div>
                                                <?php if ($stage['work_center_name']): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Рабочий центр</span>
                                                    <span class="detail-value"><?= e($stage['work_center_name']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($stage['worker_name']): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Исполнитель</span>
                                                    <span class="detail-value"><?= e($stage['worker_name']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($stage['started_at']): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Начало</span>
                                                    <span class="detail-value"><?= date('d.m.Y H:i', strtotime($stage['started_at'])) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($stage['completed_at']): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Завершение</span>
                                                    <span class="detail-value"><?= date('d.m.Y H:i', strtotime($stage['completed_at'])) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($stage['notes']): ?>
                                            <div style="margin-top: 12px; padding: 12px; background: white; border-radius: 6px; font-size: 13px;">
                                                <strong>Примечание:</strong> <?= e($stage['notes']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="empty-state">
                                    <p>Этапы производства не настроены для данной продукции</p>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="empty-state">
                                    <p>Маршрутная карта не настроена для данной продукции</p>
                                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: 8px;">
                                        Необходимо создать маршрутную карту в разделе "Продукция"
                                    </p>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Материалы -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">📦 Потребность в материалах</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($materials)): ?>
                                <div class="empty-state">
                                    <p>Материалы не рассчитаны для данного заказа</p>
                                </div>
                            <?php else: ?>
                                <div class="materials-grid">
                                    <?php foreach ($materials as $mat): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <div>
                                                <div class="material-name"><?= e($mat['material_name']) ?></div>
                                                <div class="material-code"><?= e($mat['material_code']) ?> | <?= e($mat['category_name']) ?></div>
                                            </div>
                                            <span class="material-status status-<?= $mat['material_status'] ?>">
                                                <?php
                                                $statusNames = [
                                                    'available' => '✓ Доступно',
                                                    'partial' => '! Частично',
                                                    'shortage' => '✗ Дефицит'
                                                ];
                                                echo $statusNames[$mat['material_status']] ?? $mat['material_status'];
                                                ?>
                                            </span>
                                        </div>
                                        <div class="material-quantities">
                                            <span class="qty-required">Требуется: <?= number_format($mat['required_quantity'], 2, ',', ' ') ?></span>
                                            <span class="qty-available">В наличии: <?= number_format($mat['available_quantity'], 2, ',', ' ') ?></span>
                                            <?php if ($mat['shortage_quantity'] > 0): ?>
                                            <span class="qty-shortage">Дефицит: <?= number_format($mat['shortage_quantity'], 2, ',', ' ') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
