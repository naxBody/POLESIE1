<?php
/**
 * План производства - комплексное планирование с анализом спроса, 
 * потребностью в материалах, рабочими графиками и расчетом себестоимости
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'План производства';

// ============================================
// ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ KPI
// ============================================

// Планы на неделю
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("SELECT COUNT(*) as total, 
                              SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                              SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned
                       FROM production_plans 
                       WHERE plan_date BETWEEN ? AND ?");
$stmt->execute([$weekStart, $weekEnd]);
$weekStats = $stmt->fetch();

// Дефицит материалов
$stmt = $pdo->query("SELECT COUNT(DISTINCT pmr.material_id) as shortage_count
                     FROM production_material_requirements pmr
                     JOIN materials m ON pmr.material_id = m.id
                     WHERE pmr.required_quantity > m.current_stock 
                     AND pmr.status != 'consumed'");
$shortageCount = $stmt->fetch()['shortage_count'] ?? 0;

// Общая себестоимость недели
$stmt = $pdo->prepare("SELECT COALESCE(SUM(pc.total_cost), 0) as total_cost
                       FROM production_costing pc
                       JOIN production_plans pp ON pc.plan_id = pp.id
                       WHERE pp.plan_date BETWEEN ? AND ?");
$stmt->execute([$weekStart, $weekEnd]);
$weekCost = $stmt->fetch()['total_cost'] ?? 0;

// Загрузка мощностей (кол-во смен)
$stmt = $pdo->prepare("SELECT COUNT(*) as shifts_count
                       FROM production_schedules
                       WHERE schedule_date BETWEEN ? AND ?");
$stmt->execute([$weekStart, $weekEnd]);
$shiftsCount = $stmt->fetch()['shifts_count'] ?? 0;

// ============================================
// АНАЛИЗ СПРОСА НА СЕГОДНЯ
// ============================================
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT da.*, p.name as product_name, p.article,
                              (SELECT planned_quantity FROM production_plans 
                               WHERE product_id = da.product_id AND plan_date = ? 
                               LIMIT 1) as today_plan
                       FROM demand_analysis da
                       JOIN products p ON da.product_id = p.id
                       WHERE da.analysis_date <= ? 
                       AND da.period_type IN ('daily', 'weekly')
                       ORDER BY da.forecast_value DESC
                       LIMIT 10");
$stmt->execute([$today, $today]);
$demandAnalysis = $stmt->fetchAll();

// ============================================
// ПЛАНЫ НА НЕДЕЛЮ
// ============================================
$stmt = $pdo->prepare("SELECT pp.*, p.name as product_name, p.article, 
                              pc.total_cost, pc.cost_per_unit,
                              u.full_name as responsible_name
                       FROM production_plans pp
                       JOIN products p ON pp.product_id = p.id
                       LEFT JOIN production_costing pc ON pp.id = pc.plan_id
                       LEFT JOIN users u ON pp.responsible_id = u.id
                       WHERE pp.plan_date BETWEEN ? AND ?
                       ORDER BY pp.priority ASC, pp.plan_date ASC");
$stmt->execute([$weekStart, $weekEnd]);
$weekPlans = $stmt->fetchAll();

// ============================================
// ПОТРЕБНОСТЬ В МАТЕРИАЛАХ НА НЕДЕЛЮ
// ============================================
$stmt = $pdo->prepare("SELECT pmr.*, m.name_full as material_name, m.code, m.current_stock,
                              pp.plan_number, p.name as product_name, pp.plan_date,
                              CASE WHEN pmr.required_quantity > m.current_stock THEN 1 ELSE 0 END as is_shortage
                       FROM production_material_requirements pmr
                       JOIN production_plans pp ON pmr.plan_id = pp.id
                       JOIN products p ON pp.product_id = p.id
                       JOIN materials m ON pmr.material_id = m.id
                       WHERE pp.plan_date BETWEEN ? AND ?
                       ORDER BY is_shortage DESC, pp.plan_date ASC, m.name_full ASC");
$stmt->execute([$weekStart, $weekEnd]);
$materialRequirements = $stmt->fetchAll();

// ============================================
// РАБОЧИЙ ГРАФИК НА НЕДЕЛЮ
// ============================================
$stmt = $pdo->prepare("SELECT ps.*, wc.name as work_center_name, wc.type as wc_type,
                              pp.plan_number, p.name as product_name
                       FROM production_schedules ps
                       JOIN work_centers wc ON ps.work_center_id = wc.id
                       JOIN production_plans pp ON ps.plan_id = pp.id
                       JOIN products p ON pp.product_id = p.id
                       WHERE ps.schedule_date BETWEEN ? AND ?
                       ORDER BY ps.schedule_date ASC, ps.start_time ASC, wc.name ASC");
$stmt->execute([$weekStart, $weekEnd]);
$schedules = $stmt->fetchAll();

// ============================================
// РАСЧЕТ СЕБЕСТОИМОСТИ
// ============================================
$stmt = $pdo->prepare("SELECT pc.*, pp.plan_number, p.name as product_name, pp.planned_quantity,
                              pp.plan_date, pp.status
                       FROM production_costing pc
                       JOIN production_plans pp ON pc.plan_id = pp.id
                       JOIN products p ON pp.product_id = p.id
                       WHERE pp.plan_date BETWEEN ? AND ?
                       ORDER BY pp.plan_date ASC, pp.priority ASC");
$stmt->execute([$weekStart, $weekEnd]);
$costingData = $stmt->fetchAll();
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
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
        }
        .kpi-card.warning { border-left-color: #f59e0b; }
        .kpi-card.danger { border-left-color: #ef4444; }
        .kpi-card.success { border-left-color: #10b981; }
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 10px 0;
        }
        .kpi-label {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .section-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trend-up { color: #10b981; font-size: 14px; }
        .trend-down { color: #ef4444; font-size: 14px; }
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-1 { background: #fee2e2; color: #dc2626; }
        .priority-2 { background: #fef3c7; color: #d97706; }
        .priority-3 { background: #dbeafe; color: #2563eb; }
        .shortage-row { background: #fef2f2 !important; }
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
        .cost-breakdown {
            display: flex;
            gap: 4px;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 8px;
        }
        .cost-material { background: #3b82f6; }
        .cost-labor { background: #10b981; }
        .cost-overhead { background: #f59e0b; }
        .tab-container {
            margin-bottom: 20px;
        }
        .tab-buttons {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }
        .tab-btn {
            padding: 12px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
                            <h2>📊 План производства</h2>
                            <p>Комплексное планирование на <?= date('d.m.Y', strtotime($weekStart)) ?> - <?= date('d.m.Y', strtotime($weekEnd)) ?></p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-outline" onclick="window.print()">🖨️ Печать</button>
                            <a href="plan_create.php" class="btn btn-primary">+ Создать план</a>
                        </div>
                    </div>

                    <!-- KPI Карточки -->
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="kpi-label">Планы на неделю</div>
                            <div class="kpi-value"><?= $weekStats['total'] ?? 0 ?></div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                В работе: <strong><?= $weekStats['in_progress'] ?? 0 ?></strong> | 
                                В плане: <strong><?= $weekStats['planned'] ?? 0 ?></strong>
                            </div>
                        </div>
                        <div class="kpi-card warning">
                            <div class="kpi-label">⚠️ Дефицит материалов</div>
                            <div class="kpi-value" style="color: #f59e0b;"><?= $shortageCount ?></div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                Требуют внимания
                            </div>
                        </div>
                        <div class="kpi-card success">
                            <div class="kpi-label">💰 Себестоимость недели</div>
                            <div class="kpi-value"><?= number_format($weekCost, 2, ',', ' ') ?> BYN</div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                Средняя: <?= $weekStats['total'] > 0 ? number_format($weekCost / $weekStats['total'], 2, ',', ' ') : 0 ?> BYN/план
                            </div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">🏭 Загрузка мощностей</div>
                            <div class="kpi-value"><?= $shiftsCount ?></div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                Производственных смен
                            </div>
                        </div>
                    </div>

                    <!-- Вкладки -->
                    <div class="section-card">
                        <div class="tab-container">
                            <div class="tab-buttons">
                                <button class="tab-btn active" onclick="switchTab('demand')">📈 Анализ спроса</button>
                                <button class="tab-btn" onclick="switchTab('plans')">📋 Планы на неделю</button>
                                <button class="tab-btn" onclick="switchTab('materials')">📦 Материалы</button>
                                <button class="tab-btn" onclick="switchTab('schedule')">🕐 График</button>
                                <button class="tab-btn" onclick="switchTab('costing')">💵 Себестоимость</button>
                            </div>
                        </div>

                        <!-- Анализ спроса -->
                        <div id="tab-demand" class="tab-content active">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Продукция</th>
                                        <th>Историческое среднее</th>
                                        <th>Прогноз</th>
                                        <th>Тренд</th>
                                        <th>Сезонность</th>
                                        <th>План на сегодня</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demandAnalysis as $da): ?>
                                    <?php 
                                        $trendClass = $da['trend_coefficient'] >= 1 ? 'trend-up' : 'trend-down';
                                        $trendText = $da['trend_coefficient'] >= 1 ? '↑' : '↓';
                                        $planStatus = '';
                                        if ($da['today_plan']) {
                                            $planPercent = round(($da['today_plan'] / $da['forecast_value']) * 100);
                                            if ($planPercent >= 90) $planStatus = '<span class="badge badge-success">✓ '.$planPercent.'%</span>';
                                            elseif ($planPercent >= 70) $planStatus = '<span class="badge badge-warning">! '.$planPercent.'%</span>';
                                            else $planStatus = '<span class="badge badge-danger">✗ '.$planPercent.'%</span>';
                                        } else {
                                            $planStatus = '<span class="badge badge-secondary">Нет плана</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?= e($da['product_name']) ?></strong><br><small style="color:var(--text-secondary)"><?= e($da['article']) ?></small></td>
                                        <td><?= round($da['historical_avg']) ?></td>
                                        <td><strong><?= round($da['forecast_value']) ?></strong></td>
                                        <td class="<?= $trendClass ?>"><?= $trendText ?> <?= round(($da['trend_coefficient'] - 1) * 100) ?>%</td>
                                        <td><?= round($da['seasonality_factor'], 2) ?></td>
                                        <td><?= $da['today_plan'] ?? '—' ?> <?= $planStatus ?></td>
                                        <td>
                                            <div class="progress-bar" style="width: 100px;">
                                                <div class="progress-fill" style="width: <?= min($da['confidence_level'], 100) ?>%"></div>
                                            </div>
                                            <small><?= round($da['confidence_level']) ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($demandAnalysis)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📈</div>
                                    <h3>Нет данных анализа спроса</h3>
                                    <p>Добавьте прогнозы спроса для продукции</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Планы на неделю -->
                        <div id="tab-plans" class="tab-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>План №</th>
                                        <th>Продукция</th>
                                        <th>Количество</th>
                                        <th>Прогноз спроса</th>
                                        <th>Приоритет</th>
                                        <th>Себестоимость</th>
                                        <th>За ед.</th>
                                        <th>Статус</th>
                                        <th>Ответственный</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weekPlans as $plan): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($plan['plan_date'])) ?></td>
                                        <td><strong><?= e($plan['plan_number']) ?></strong></td>
                                        <td><strong><?= e($plan['product_name']) ?></strong><br><small style="color:var(--text-secondary)"><?= e($plan['article']) ?></small></td>
                                        <td><strong><?= $plan['planned_quantity'] ?></strong></td>
                                        <td><?= $plan['demand_forecast'] ?? '—' ?></td>
                                        <td><span class="priority-badge priority-<?= $plan['priority'] ?>">
                                            <?php if ($plan['priority'] == 1): ?>Высокий<?php elseif ($plan['priority'] == 2): ?>Средний<?php else: ?>Низкий<?php endif; ?>
                                        </span></td>
                                        <td><strong><?= number_format($plan['total_cost'] ?? 0, 2, ',', ' ') ?> BYN</strong></td>
                                        <td><?= number_format($plan['cost_per_unit'] ?? 0, 2, ',', ' ') ?> BYN</td>
                                        <td>
                                            <?php if ($plan['status'] === 'planned'): ?>
                                                <span class="badge badge-warning">План</span>
                                            <?php elseif ($plan['status'] === 'in_progress'): ?>
                                                <span class="badge badge-info">В работе</span>
                                            <?php elseif ($plan['status'] === 'completed'): ?>
                                                <span class="badge badge-success">Завершено</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Отменено</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($plan['responsible_name'] ?? '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($weekPlans)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <h3>Планов на неделю нет</h3>
                                    <p>Создайте производственные планы</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Потребность в материалах -->
                        <div id="tab-materials" class="tab-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Дата плана</th>
                                        <th>Продукция</th>
                                        <th>Материал</th>
                                        <th>Норма расхода</th>
                                        <th>Требуется</th>
                                        <th>На складе</th>
                                        <th>Статус</th>
                                        <th>Стоимость</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materialRequirements as $mr): ?>
                                    <tr class="<?= $mr['is_shortage'] ? 'shortage-row' : '' ?>">
                                        <td><?= date('d.m.Y', strtotime($mr['plan_date'])) ?></td>
                                        <td><strong><?= e($mr['product_name']) ?></strong><br><small><?= e($mr['plan_number']) ?></small></td>
                                        <td><strong><?= e($mr['material_name']) ?></strong><br><small style="color:var(--text-secondary)"><?= e($mr['code']) ?></small></td>
                                        <td><?= $mr['consumption_rate'] ?></td>
                                        <td><strong style="color: <?= $mr['is_shortage'] ? '#ef4444' : 'inherit' ?>;"><?= $mr['required_quantity'] ?></strong></td>
                                        <td><?= $mr['current_stock'] ?></td>
                                        <td>
                                            <?php if ($mr['is_shortage']): ?>
                                                <span class="badge badge-danger">⚠️ Дефицит</span>
                                            <?php elseif ($mr['status'] === 'reserved'): ?>
                                                <span class="badge badge-info">Зарезервировано</span>
                                            <?php elseif ($mr['status'] === 'consumed'): ?>
                                                <span class="badge badge-success">Списано</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">В ожидании</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($mr['total_cost'], 2, ',', ' ') ?> BYN</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($materialRequirements)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📦</div>
                                    <h3>Нет потребности в материалах</h3>
                                    <p>Материалы не рассчитаны для планов</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Рабочий график -->
                        <div id="tab-schedule" class="tab-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Рабочий центр</th>
                                        <th>Смена</th>
                                        <th>Время</th>
                                        <th>План часов</th>
                                        <th>Факт часов</th>
                                        <th>Рабочих</th>
                                        <th>Эффективность</th>
                                        <th>Продукция</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $sch): ?>
                                    <?php 
                                        $shiftLabel = [
                                            'morning' => '☀️ Утренняя',
                                            'afternoon' => '🌤️ Дневная',
                                            'night' => '🌙 Ночная'
                                        ][$sch['shift_type']] ?? $sch['shift_type'];
                                        $efficiency = $sch['efficiency_percent'] ?? 0;
                                    ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($sch['schedule_date'])) ?></td>
                                        <td><strong><?= e($sch['work_center_name']) ?></strong></td>
                                        <td><?= $shiftLabel ?></td>
                                        <td><?= substr($sch['start_time'], 0, 5) ?> - <?= substr($sch['end_time'], 0, 5) ?></td>
                                        <td><?= $sch['planned_hours'] ?></td>
                                        <td><?= $sch['actual_hours'] ?? '—' ?></td>
                                        <td><?= $sch['workers_count'] ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div class="progress-bar" style="width: 100px;">
                                                    <div class="progress-fill" style="width: <?= min($efficiency, 100) ?>%"></div>
                                                </div>
                                                <span><?= round($efficiency) ?>%</span>
                                            </div>
                                        </td>
                                        <td><?= e($sch['product_name']) ?><br><small style="color:var(--text-secondary)"><?= e($sch['plan_number']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($schedules)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">🕐</div>
                                    <h3>График пуст</h3>
                                    <p>Добавьте рабочие смены для планов</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Расчет себестоимости -->
                        <div id="tab-costing" class="tab-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>План №</th>
                                        <th>Продукция</th>
                                        <th>Кол-во</th>
                                        <th>Структура затрат</th>
                                        <th>Материалы</th>
                                        <th>Работа</th>
                                        <th>Накладные</th>
                                        <th>Всего</th>
                                        <th>За ед.</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($costingData as $cost): ?>
                                    <?php 
                                        $total = $cost['total_cost'] ?? 0;
                                        $matPct = $total > 0 ? ($cost['material_cost'] / $total * 100) : 0;
                                        $labPct = $total > 0 ? ($cost['labor_cost'] / $total * 100) : 0;
                                        $ovhPct = $total > 0 ? ($cost['overhead_cost'] / $total * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($cost['plan_date'])) ?></td>
                                        <td><strong><?= e($cost['plan_number']) ?></strong></td>
                                        <td><strong><?= e($cost['product_name']) ?></strong></td>
                                        <td><?= $cost['planned_quantity'] ?></td>
                                        <td>
                                            <div class="cost-breakdown">
                                                <div class="cost-material" style="width: <?= $matPct ?>%"></div>
                                                <div class="cost-labor" style="width: <?= $labPct ?>%"></div>
                                                <div class="cost-overhead" style="width: <?= $ovhPct ?>%"></div>
                                            </div>
                                            <div style="font-size: 11px; margin-top: 4px; color: var(--text-secondary);">
                                                Мат: <?= round($matPct) ?>% | Раб: <?= round($labPct) ?>% | Накл: <?= round($ovhPct) ?>%
                                            </div>
                                        </td>
                                        <td><?= number_format($cost['material_cost'], 2, ',', ' ') ?></td>
                                        <td><?= number_format($cost['labor_cost'], 2, ',', ' ') ?></td>
                                        <td><?= number_format($cost['overhead_cost'], 2, ',', ' ') ?></td>
                                        <td><strong><?= number_format($total, 2, ',', ' ') ?> BYN</strong></td>
                                        <td><?= number_format($cost['cost_per_unit'], 2, ',', ' ') ?> BYN</td>
                                        <td>
                                            <?php if ($cost['status'] === 'planned'): ?>
                                                <span class="badge badge-warning">План</span>
                                            <?php elseif ($cost['status'] === 'in_progress'): ?>
                                                <span class="badge badge-info">В работе</span>
                                            <?php elseif ($cost['status'] === 'completed'): ?>
                                                <span class="badge badge-success">Завершено</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Отменено</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($costingData)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">💵</div>
                                    <h3>Нет данных о себестоимости</h3>
                                    <p>Себестоимость не рассчитана для планов</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Скрыть все контенты
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Показать выбранный
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
