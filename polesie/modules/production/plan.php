<?php
/**
 * План производства - главная страница модуля
 * Анализ спроса, мощности, материалы, график, себестоимость
 */

require_once '../../includes/auth.php';
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'План производства';
$currentPage = 'production_plan';

// Получение данных для анализа
$stmt = $pdo->prepare("SELECT 
    p.id as product_id,
    p.name as product_name,
    p.sale_price,
    da.forecast_value,
    da.historical_avg,
    da.trend_coefficient,
    da.variance_percent,
    pp.planned_quantity,
    pp.status as plan_status
FROM products p
LEFT JOIN demand_analysis da ON p.id = da.product_id AND da.analysis_date = CURDATE()
LEFT JOIN production_plans pp ON p.id = pp.product_id AND pp.plan_date = CURDATE()
WHERE p.type = 'finished_good' OR p.type = 'product'
ORDER BY p.name");
$stmt->execute();
$demandData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Планы на текущую неделю
$stmt = $pdo->prepare("SELECT 
    pp.id as plan_id,
    pp.plan_date,
    pp.planned_quantity,
    pp.actual_quantity,
    pp.demand_forecast,
    pp.status,
    pp.priority,
    pp.notes,
    p.name as product_name,
    pc.total_cost,
    pc.cost_per_unit,
    (SELECT COUNT(*) FROM production_schedules ps WHERE ps.plan_id = pp.id) as schedule_count,
    (SELECT SUM(required_quantity) FROM production_material_requirements pmr WHERE pmr.plan_id = pp.id) as total_materials
FROM production_plans pp
JOIN products p ON pp.product_id = p.id
LEFT JOIN production_costing pc ON pp.id = pc.plan_id
WHERE pp.plan_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY pp.priority ASC, pp.plan_date ASC");
$stmt->execute();
$weeklyPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Загрузка рабочих центров
$stmt = $pdo->prepare("SELECT 
    ps.schedule_date,
    ps.shift_type,
    ps.planned_hours,
    ps.actual_hours,
    ps.workers_count,
    ps.efficiency_percent,
    ps.status,
    wc.name as work_center_name,
    p.name as product_name
FROM production_schedules ps
LEFT JOIN work_centers wc ON ps.work_center_id = wc.id
JOIN production_plans pp ON ps.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
WHERE ps.schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY ps.schedule_date, ps.start_time");
$stmt->execute();
$scheduleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Потребность в материалах с дефицитом
$stmt = $pdo->prepare("SELECT 
    pmr.id,
    pmr.required_quantity,
    pmr.reserved_quantity,
    pmr.actual_quantity,
    pmr.status,
    pmr.total_cost,
    m.name as material_name,
    m.unit,
    m.current_stock,
    p.name as product_name,
    pp.plan_date,
    CASE 
        WHEN m.current_stock < pmr.required_quantity THEN 'DEFCIT'
        ELSE 'OK'
    END as stock_status
FROM production_material_requirements pmr
JOIN materials m ON pmr.material_id = m.id
JOIN production_plans pp ON pmr.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
WHERE pmr.status IN ('pending', 'shortage') OR m.current_stock < pmr.required_quantity
ORDER BY 
    CASE WHEN m.current_stock < pmr.required_quantity THEN 0 ELSE 1 END,
    pp.plan_date ASC");
$stmt->execute();
$materialRequirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Сводная себестоимость
$stmt = $pdo->prepare("SELECT 
    SUM(pc.material_cost) as total_material,
    SUM(pc.labor_cost) as total_labor,
    SUM(pc.overhead_cost) as total_overhead,
    SUM(pc.total_cost) as grand_total,
    AVG(pc.cost_per_unit) as avg_cost_per_unit,
    COUNT(pc.id) as plans_count
FROM production_costing pc
JOIN production_plans pp ON pc.plan_id = pp.id
WHERE pp.plan_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$costSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Статистика
$stats = [
    'total_plans' => count($weeklyPlans),
    'in_progress' => count(array_filter($weeklyPlans, fn($p) => $p['status'] === 'in_progress')),
    'planned' => count(array_filter($weeklyPlans, fn($p) => $p['status'] === 'planned')),
    'material_shortages' => count(array_filter($materialRequirements, fn($m) => $m['stock_status'] === 'DEFCIT')),
];

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-alt"></i> План производства</h2>
            <p class="text-muted">Комплексное планирование: спрос, мощности, материалы, себестоимость</p>
        </div>
    </div>

    <!-- KPI Карточки -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Планы на неделю</h5>
                    <h2><?= $stats['total_plans'] ?></h2>
                    <small>В работе: <?= $stats['in_progress'] ?> | В плане: <?= $stats['planned'] ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Дефицит материалов</h5>
                    <h2><?= $stats['material_shortages'] ?></h2>
                    <small>Требуют внимания</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Общая себестоимость</h5>
                    <h2><?= number_format($costSummary['grand_total'] ?? 0, 0, '.', ' ') ?> ₽</h2>
                    <small>Средняя за ед.: <?= number_format($costSummary['avg_cost_per_unit'] ?? 0, 2, '.', ' ') ?> ₽</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Загрузка мощностей</h5>
                    <h2><?= count($scheduleData) ?> смен</h2>
                    <small>На 7 дней</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Анализ спроса -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Анализ спроса и прогнозы</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Продукт</th>
                                <th>Истор. среднее</th>
                                <th>Прогноз</th>
                                <th>Тренд</th>
                                <th>План на сегодня</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandData as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                                <td><?= number_format($item['historical_avg'] ?? 0, 0) ?></td>
                                <td><?= number_format($item['forecast_value'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if ($item['trend_coefficient'] ?? 1 > 1): ?>
                                        <span class="badge bg-success">↑ <?= number_format(($item['trend_coefficient'] - 1) * 100, 1) ?>%</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">↓ <?= number_format((1 - $item['trend_coefficient']) * 100, 1) ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['planned_quantity'] ?? '-' ?></td>
                                <td>
                                    <?php if ($item['plan_status']): ?>
                                        <span class="badge bg-<?= $item['plan_status'] === 'in_progress' ? 'warning' : 'secondary' ?>">
                                            <?= $item['plan_status'] === 'in_progress' ? 'В работе' : 'Запланировано' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Нет плана</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Планы производства -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks"></i> Планы на неделю</h5>
                    <button class="btn btn-sm btn-primary" onclick="alert('Функция создания плана')">
                        <i class="fas fa-plus"></i> Новый план
                    </button>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Дата</th>
                                <th>Продукт</th>
                                <th>План/Прогноз</th>
                                <th>Материалы</th>
                                <th>Себестоимость</th>
                                <th>Приоритет</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weeklyPlans as $plan): ?>
                            <tr>
                                <td><?= date('d.m', strtotime($plan['plan_date'])) ?></td>
                                <td><strong><?= htmlspecialchars($plan['product_name']) ?></strong></td>
                                <td>
                                    <?= $plan['planned_quantity'] ?> / 
                                    <small class="text-muted"><?= $plan['demand_forecast'] ?></small>
                                </td>
                                <td><?= number_format($plan['total_materials'] ?? 0, 1) ?></td>
                                <td>
                                    <div><?= number_format($plan['total_cost'] ?? 0, 0, '.', ' ') ?> ₽</div>
                                    <small class="text-muted">за ед: <?= number_format($plan['cost_per_unit'] ?? 0, 0) ?> ₽</small>
                                </td>
                                <td>
                                    <?php for ($i = 1; $i <= 3; $i++): ?>
                                        <i class="fas fa-flag text-<?= $i <= $plan['priority'] ? 'danger' : 'light' ?>"></i>
                                    <?php endfor; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'planned' => ['secondary', 'Запланировано'],
                                        'in_progress' => ['warning', 'В работе'],
                                        'completed' => ['success', 'Готово'],
                                        'cancelled' => ['danger', 'Отменено']
                                    ];
                                    $style = $statusLabels[$plan['status']] ?? ['secondary', $plan['status']];
                                    ?>
                                    <span class="badge bg-<?= $style[0] ?>"><?= $style[1] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Дефицит материалов -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Потребность в материалах</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Материал</th>
                                    <th>Нужно</th>
                                    <th>Есть</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materialRequirements as $mat): ?>
                                <tr class="<?= $mat['stock_status'] === 'DEFCIT' ? 'table-danger' : '' ?>">
                                    <td>
                                        <small><?= htmlspecialchars($mat['material_name']) ?></small>
                                        <br>
                                        <span class="text-muted" style="font-size: 0.75em;"><?= htmlspecialchars($mat['product_name']) ?></span>
                                    </td>
                                    <td><?= number_format($mat['required_quantity'], 1) ?> <?= $mat['unit'] ?></td>
                                    <td><?= number_format($mat['current_stock'] ?? 0, 1) ?></td>
                                    <td>
                                        <?php if ($mat['stock_status'] === 'DEFCIT'): ?>
                                            <span class="badge bg-danger">ДЕФИЦИТ</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Рабочий график -->
        <div class="col-lg-7 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Рабочий график (смены)</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Дата</th>
                                <th>Цех</th>
                                <th>Смена</th>
                                <th>Время</th>
                                <th>Рабочих</th>
                                <th>Эффективность</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $shiftLabels = [
                                'morning' => ['☀️ Утро', 'bg-warning'],
                                'afternoon' => ['🌆 День', 'bg-info'],
                                'night' => ['🌙 Ночь', 'bg-dark']
                            ];
                            foreach ($scheduleData as $sched): 
                            ?>
                            <tr>
                                <td><?= date('d.m D', strtotime($sched['schedule_date'])) ?></td>
                                <td><?= htmlspecialchars($sched['work_center_name'] ?? 'Не указан') ?></td>
                                <td>
                                    <?php $shift = $shiftLabels[$sched['shift_type']] ?? [$sched['shift_type'], '']; ?>
                                    <span class="badge <?= $shift[1] ?>"><?= $shift[0] ?></span>
                                </td>
                                <td>
                                    <?= date('H:i', strtotime($sched['start_time'])) ?> - 
                                    <?= date('H:i', strtotime($sched['end_time'])) ?>
                                </td>
                                <td><?= $sched['workers_count'] ?> чел.</td>
                                <td>
                                    <div class="progress" style="height: 6px; width: 80px;">
                                        <div class="progress-bar bg-<?= $sched['efficiency_percent'] >= 90 ? 'success' : ($sched['efficiency_percent'] >= 70 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $sched['efficiency_percent'] ?>%"></div>
                                    </div>
                                    <small><?= number_format($sched['efficiency_percent'], 0) ?>%</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $sched['status'] === 'in_progress' ? 'primary' : 'secondary' ?>">
                                        <?= $sched['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Структура себестоимости -->
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Расчет себестоимости</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">Структура затрат на неделю</h6>
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="p-3 bg-light rounded">
                                    <h4 class="text-primary"><?= number_format($costSummary['total_material'] ?? 0, 0, '.', ' ') ?></h4>
                                    <small>Материалы (₽)</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded">
                                    <h4 class="text-success"><?= number_format($costSummary['total_labor'] ?? 0, 0, '.', ' ') ?></h4>
                                    <small>Работы (₽)</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded">
                                    <h4 class="text-warning"><?= number_format($costSummary['total_overhead'] ?? 0, 0, '.', ' ') ?></h4>
                                    <small>Накладные (₽)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Диаграмма структуры -->
                        <div class="progress mb-3" style="height: 30px;">
                            <?php 
                            $total = $costSummary['grand_total'] ?? 1;
                            $matPct = (($costSummary['total_material'] ?? 0) / $total) * 100;
                            $labPct = (($costSummary['total_labor'] ?? 0) / $total) * 100;
                            $ovhPct = (($costSummary['total_overhead'] ?? 0) / $total) * 100;
                            ?>
                            <div class="progress-bar bg-primary" style="width: <?= $matPct ?>%" title="Материалы"></div>
                            <div class="progress-bar bg-success" style="width: <?= $labPct ?>%" title="Работы"></div>
                            <div class="progress-bar bg-warning" style="width: <?= $ovhPct ?>%" title="Накладные"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span><span class="badge bg-primary"></span> Материалы: <?= number_format($matPct, 1) ?>%</span>
                            <span><span class="badge bg-success"></span> Работы: <?= number_format($labPct, 1) ?>%</span>
                            <span><span class="badge bg-warning"></span> Накладные: <?= number_format($ovhPct, 1) ?>%</span>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Детализация по планам</h6>
                        <ul class="mb-0 small">
                            <?php foreach ($weeklyPlans as $plan): ?>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span><?= htmlspecialchars($plan['product_name']) ?> (<?= $plan['planned_quantity'] ?> шт)</span>
                                <strong><?= number_format($plan['cost_per_unit'] ?? 0, 0, '.', ' ') ?> ₽/ед</strong>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="text-center">
                        <button class="btn btn-outline-primary btn-sm" onclick="alert('Экспорт калькуляции')">
                            <i class="fas fa-download"></i> Экспорт калькуляции
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
.table th { font-weight: 600; font-size: 0.875rem; }
.badge { font-weight: 500; }
.progress { border-radius: 4px; }
</style>

<?php include '../../includes/footer.php'; ?>
