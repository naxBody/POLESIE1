<?php
/**
 * Список заказов
 * Модуль управления заказами
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение фильтров
$search = $_GET['search'] ?? '';
$statusId = $_GET['status'] ?? '';
$contractorId = $_GET['contractor'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Построение запроса
$sql = "
    SELECT o.*, c.name as contractor_name, os.name as status_name, os.color as status_color,
           u.full_name as responsible_name,
           COUNT(oi.id) as items_count
    FROM orders o
    JOIN contractors c ON o.contractor_id = c.id
    JOIN order_statuses os ON o.status_id = os.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusId) {
    $sql .= " AND o.status_id = ?";
    $params[] = $statusId;
}

if ($contractorId) {
    $sql .= " AND o.contractor_id = ?";
    $params[] = $contractorId;
}

if ($dateFrom) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $dateTo;
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Пагинация
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Общее количество
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o 
             JOIN contractors c ON o.contractor_id = c.id 
             WHERE 1=1" . 
             ($search ? " AND (o.order_number LIKE ? OR c.name LIKE ?)" : "") .
             ($statusId ? " AND o.status_id = ?" : "") .
             ($contractorId ? " AND o.contractor_id = ?" : "") .
             ($dateFrom ? " AND o.order_date >= ?" : "") .
             ($dateTo ? " AND o.order_date <= ?" : "");

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Получение данных
$sql .= " LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Получение статусов для фильтра
$statuses = $pdo->query("SELECT * FROM order_statuses WHERE is_active = TRUE ORDER BY sort_order")->fetchAll();

// Получение контрагентов для фильтра
$contractors = $pdo->query("SELECT id, name FROM contractors WHERE is_active = TRUE ORDER BY name LIMIT 100")->fetchAll();

$pageTitle = 'Заказы';
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
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <!-- Фильтры -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Поиск</label>
                                <input type="text" name="search" class="form-control" placeholder="№ заказа или заказчик" value="<?= e($search) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Статус</label>
                                <select name="status" class="form-control">
                                    <option value="">Все статусы</option>
                                    <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $statusId == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Заказчик</label>
                                <select name="contractor" class="form-control">
                                    <option value="">Все заказчики</option>
                                    <?php foreach ($contractors as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $contractorId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
                
                <!-- Заголовок и действия -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: 600;">Заказы (<?= $totalRecords ?>)</h2>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-secondary" onclick="exportTableToCSV('ordersTable', 'orders.csv')">
                            📥 Экспорт CSV
                        </button>
                        <a href="create.php" class="btn btn-primary">➕ Новый заказ</a>
                    </div>
                </div>
                
                <!-- Таблица заказов -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="table" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Номер</th>
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
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                            Заказы не найдены
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><a href="view.php?id=<?= $order['id'] ?>" style="color: var(--primary-color);"><?= e($order['order_number']) ?></a></strong>
                                            </td>
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
                                                <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр">👁️</a>
                                                <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Редактировать">✏️</a>
                                                <a href="print.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Печать" target="_blank">🖨️</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 13px; color: var(--text-secondary);">
                                Показано <?= $offset + 1 ?> - <?= min($offset + $perPage, $totalRecords) ?> из <?= $totalRecords ?>
                            </span>
                            <div style="display: flex; gap: 4px;">
                                <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-sm btn-secondary">← Назад</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                   class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?>">
                                    <?= $i ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-sm btn-secondary">Вперед →</a>
                                <?php endif; ?>
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
