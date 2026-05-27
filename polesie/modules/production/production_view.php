<?php
/**
 * Просмотр производственного заказа - детальная информация по этапам
 * Отображение текущего статуса выполнения, маршрутной карты и материалов
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Производственный заказ';
$orderId = $_GET['id'] ?? 0;

// Получение информации о производственном заказе
$productionOrder = null;
try {
    $stmt = $pdo->prepare("
        SELECT po.*, p.name as product_name, p.article,
               o.order_number, c.name as customer_name, c.inn as customer_inn,
               u.full_name as responsible_name,
               rm.route_number, rm.version,
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
               END as status_color
        FROM production_orders po
        JOIN products p ON po.product_id = p.id
        LEFT JOIN orders o ON po.order_id = o.id
        LEFT JOIN contractors c ON o.customer_id = c.id
        LEFT JOIN users u ON po.responsible_id = u.id
        LEFT JOIN route_maps rm ON po.route_map_id = rm.id
        WHERE po.id = ?
    ");
    $stmt->execute([$orderId]);
    $productionOrder = $stmt->fetch();
} catch (Exception $e) {
    // Таблица не существует
}

// Если заказ не найден
if (!$productionOrder) {
    die('<div style="padding: 40px; text-align: center;"><h2>Производственный заказ не найден</h2><a href="release_plan.php" class="btn btn-primary">← Назад к плану выпуска</a></div>');
}

// Получение этапов выполнения
$stages = [];
try {
    $stmt = $pdo->prepare("
        SELECT pos.*, rmo.operation_id, rmo.planned_duration_hours,
               to.name as operation_name, wc.name as work_center_name,
               op.full_name as operator_name, insp.full_name as inspector_name,
               CASE pos.status
                   WHEN 'pending' THEN 'Ожидается'
                   WHEN 'in_progress' THEN 'В работе'
                   WHEN 'completed' THEN 'Завершено'
                   WHEN 'skipped' THEN 'Пропущено'
                   WHEN 'rejected' THEN 'Отклонено'
                   ELSE pos.status
               END as status_name,
               CASE pos.status
                   WHEN 'pending' THEN '#6b7280'
                   WHEN 'in_progress' THEN '#f39c12'
                   WHEN 'completed' THEN '#27ae60'
                   WHEN 'skipped' THEN '#95a5a6'
                   WHEN 'rejected' THEN '#e74c3c'
                   ELSE '#95a5a6'
               END as status_color
        FROM production_order_stages pos
        JOIN route_map_operations rmo ON pos.route_map_operation_id = rmo.id
        LEFT JOIN technology_operations to ON rmo.operation_id = to.id
        LEFT JOIN work_centers wc ON pos.work_center_id = wc.id
        LEFT JOIN users op ON pos.operator_id = op.id
        LEFT JOIN users insp ON pos.inspector_id = insp.id
        WHERE pos.production_order_id = ?
        ORDER BY pos.sequence_number
    ");
    $stmt->execute([$orderId]);
    $stages = $stmt->fetchAll();
} catch (Exception $e) {
    // Таблица не существует
}

// Получение материалов для заказа
$materials = [];
try {
    $stmt = $pdo->prepare("
        SELECT pom.*, m.name_full as material_name, m.code, m.current_stock,
               CASE 
                   WHEN pom.required_quantity > m.current_stock THEN 'shortage'
                   WHEN pom.required_quantity > pom.reserved_quantity THEN 'partial'
                   ELSE 'sufficient'
               END as stock_status
        FROM production_order_materials pom
        JOIN materials m ON pom.material_id = m.id
        WHERE pom.production_order_id = ?
        ORDER BY m.name_full
    ");
    $stmt->execute([$orderId]);
    $materials = $stmt->fetchAll();
} catch (Exception $e) {
    // Таблица не существует
}

// Расчет прогресса
$totalStages = count($stages);
$completedStages = 0;
foreach ($stages as $stage) {
    if ($stage['status'] === 'completed') {
        $completedStages++;
    }
}
$progressPercent = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
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
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            position: relative;
        }
        .timeline-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
            z-index: 1;
        }
        .marker-pending { background: #f3f4f6; color: #6b7280; }
        .marker-in-progress { background: #fef3c7; color: #d97706; }
        .marker-completed { background: #d1fae5; color: #059669; }
        .marker-rejected { background: #fee2e2; color: #dc2626; }
        .timeline-content {
            flex: 1;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 16px;
        }
        .timeline-line {
            position: absolute;
            left: 20px;
            top: 60px;
            bottom: -24px;
            width: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        .timeline-item:last-child .timeline-line {
            display: none;
        }
        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .stage-title {
            font-size: 16px;
            font-weight: 600;
        }
        .stage-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
            position: relative;
        }
        .progress-ring-circle {
            fill: none;
            stroke: #e5e7eb;
            stroke-width: 8;
        }
        .progress-ring-fill {
            fill: none;
            stroke: var(--primary-color);
            stroke-width: 8;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 0.5s;
        }
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: 700;
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
                            <h2>📋 <?= e($productionOrder['production_number']) ?></h2>
                            <p><?= e($productionOrder['product_name']) ?> (<?= e($productionOrder['article']) ?>)</p>
                        </div>
                        <div class="page-header-actions">
                            <a href="release_plan.php" class="btn btn-secondary">← Назад</a>
                            <button class="btn btn-outline" onclick="window.print()">🖨️ Печать</button>
                        </div>
                    </div>

                    <!-- Основная информация -->
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
                        <div class="card">
                            <div class="card-body">
                                <h3 style="margin-bottom: 16px;">Информация о заказе</h3>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                    <div>
                                        <div class="meta-label">Номер заказа</div>
                                        <div><strong><?= e($productionOrder['production_number']) ?></strong></div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Статус</div>
                                        <div>
                                            <span class="badge" style="background: <?= e($productionOrder['status_color']) ?>20; color: <?= e($productionOrder['status_color']) ?>">
                                                <?= e($productionOrder['status_name']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Продукция</div>
                                        <div><?= e($productionOrder['product_name']) ?></div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Количество</div>
                                        <div><strong><?= $productionOrder['quantity'] ?></strong></div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Срок</div>
                                        <div><?= formatDate($productionOrder['deadline']) ?></div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Приоритет</div>
                                        <div>
                                            <?php
                                            $priorityBadges = [
                                                1 => '<span class="badge badge-danger">Высокий</span>',
                                                2 => '<span class="badge badge-warning">Средний</span>',
                                                3 => '<span class="badge badge-secondary">Низкий</span>'
                                            ];
                                            echo $priorityBadges[$productionOrder['priority']] ?? '-';
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Заказчик</div>
                                        <div><?= e($productionOrder['customer_name'] ?? '-') ?></div>
                                    </div>
                                    <div>
                                        <div class="meta-label">Ответственный</div>
                                        <div><?= e($productionOrder['responsible_name'] ?? '-') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <h3 style="margin-bottom: 16px;">Прогресс</h3>
                                <div class="progress-ring" style="margin: 0 auto 16px;">
                                    <svg width="120" height="120">
                                        <circle class="progress-ring-circle" cx="60" cy="60" r="52"/>
                                        <circle class="progress-ring-fill" cx="60" cy="60" r="52"
                                                stroke-dasharray="<?= 2 * pi() * 52 ?>"
                                                stroke-dashoffset="<?= 2 * pi() * 52 * (1 - $progressPercent / 100) ?>"/>
                                    </svg>
                                    <div class="progress-ring-text"><?= $progressPercent ?>%</div>
                                </div>
                                <div><?= $completedStages ?> из <?= $totalStages ?> этапов завершено</div>
                            </div>
                        </div>
                    </div>

                    <!-- Этапы выполнения -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h3>🔄 Этапы производства</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stages)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                Этапы производства пока не определены
                            </p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($stages as $index => $stage): ?>
                                <div class="timeline-item">
                                    <div class="timeline-line"></div>
                                    <div class="timeline-marker marker-<?= str_replace('_', '-', $stage['status']) ?>">
                                        <?= $index + 1 ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="stage-header">
                                            <div class="stage-title">
                                                <?= e($stage['operation_name'] ?? 'Этап ' . ($index + 1)) ?>
                                                <span class="badge" style="background: <?= e($stage['status_color']) ?>20; color: <?= e($stage['status_color']) ?>; margin-left: 8px;">
                                                    <?= e($stage['status_name']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($stage['work_center_name']): ?>
                                        <div style="margin-bottom: 12px;">
                                            <strong>🏭</strong> <?= e($stage['work_center_name']) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="stage-meta">
                                            <?php if ($stage['planned_duration_hours']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">План. длительность</div>
                                                <div><?= $stage['planned_duration_hours'] ?> ч.</div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['actual_duration_hours']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Факт. длительность</div>
                                                <div><?= $stage['actual_duration_hours'] ?> ч.</div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['workers_count']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Рабочих</div>
                                                <div><?= $stage['workers_count'] ?> чел.</div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['operator_name']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Оператор</div>
                                                <div><?= e($stage['operator_name']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['quality_status'] !== 'not_checked'): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Контроль качества</div>
                                                <div>
                                                    <?php
                                                    $qcStatuses = [
                                                        'passed' => ['✓ Пройдено', '#27ae60'],
                                                        'failed' => ['✗ Не пройдено', '#e74c3c'],
                                                        'rework' => ['⚠ На доработке', '#f39c12']
                                                    ];
                                                    $qcInfo = $qcStatuses[$stage['quality_status']] ?? ['-'];
                                                    ?>
                                                    <span style="color: <?= $qcInfo[1] ?>"><?= $qcInfo[0] ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['actual_start']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Начало</div>
                                                <div><?= date('d.m.Y H:i', strtotime($stage['actual_start'])) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['actual_end']): ?>
                                            <div class="meta-item">
                                                <div class="meta-label">Окончание</div>
                                                <div><?= date('d.m.Y H:i', strtotime($stage['actual_end'])) ?></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($stage['defect_description']): ?>
                                        <div style="margin-top: 12px; padding: 12px; background: #fef2f2; border-radius: 8px; color: #dc2626;">
                                            <strong>⚠️ Дефект:</strong> <?= nl2br(e($stage['defect_description'])) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($stage['notes']): ?>
                                        <div style="margin-top: 12px; padding: 12px; background: #f9fafb; border-radius: 8px;">
                                            <strong>📝 Примечание:</strong> <?= nl2br(e($stage['notes'])) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Материалы -->
                    <?php if (!empty($materials)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>📦 Материалы</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Материал</th>
                                            <th>Код</th>
                                            <th>Требуется</th>
                                            <th>Зарезервировано</th>
                                            <th>Выдано</th>
                                            <th>На складе</th>
                                            <th>Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $mat): ?>
                                        <tr class="<?= $mat['stock_status'] === 'shortage' ? 'material-shortage' : '' ?>">
                                            <td><strong><?= e($mat['material_name']) ?></strong></td>
                                            <td><?= e($mat['code']) ?></td>
                                            <td><?= number_format($mat['required_quantity'], 3, ',', ' ') ?></td>
                                            <td><?= number_format($mat['reserved_quantity'], 3, ',', ' ') ?></td>
                                            <td><?= number_format($mat['issued_quantity'], 3, ',', ' ') ?></td>
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
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
