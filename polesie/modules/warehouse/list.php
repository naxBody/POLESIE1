<?php
/**
 * Склад - учёт материалов и готовой продукции
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Склад';

$type = $_GET['type'] ?? 'materials';
$search = $_GET['search'] ?? '';

if ($type === 'products') {
    $sql = "SELECT wi.*, p.name as item_name, p.article 
            FROM warehouse_items wi 
            JOIN products p ON wi.item_id = p.id AND p.id IS NOT NULL
            WHERE wi.item_type = 'product'";
    $params = [];
    
    if ($search) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%$search%";
    }
} else {
    $sql = "SELECT wi.*, m.name as item_name, m.unit 
            FROM warehouse_items wi 
            JOIN materials m ON wi.item_id = m.id AND m.id IS NOT NULL
            WHERE wi.item_type = 'material'";
    $params = [];
    
    if ($search) {
        $sql .= " AND m.name LIKE ?";
        $params[] = "%$search%";
    }
}

$sql .= " ORDER BY wi.quantity ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
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
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>📦 Склад</h2>
                            <p>Учёт материалов и готовой продукции</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('warehouse.move')): ?>
                                <a href="move.php" class="btn btn-primary">± Перемещение</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="tabs" style="margin-bottom: 20px;">
                                <a href="?type=materials" class="tab <?= $type === 'materials' ? 'active' : '' ?>">Материалы</a>
                                <a href="?type=products" class="tab <?= $type === 'products' ? 'active' : '' ?>">Готовая продукция</a>
                            </div>

                            <form method="GET" class="filter-form">
                                <input type="hidden" name="type" value="<?= $type ?>">
                                <div class="filter-row">
                                    <input type="text" name="search" placeholder="Поиск..." value="<?= e($search) ?>" 
                                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <button type="submit" class="btn btn-secondary">Найти</button>
                                    <a href="?type=<?= $type ?>" class="btn btn-outline">Сброс</a>
                                </div>
                            </form>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Наименование</th>
                                        <th>Артикул/Код</th>
                                        <th>Остаток</th>
                                        <th>Ед. изм.</th>
                                        <th>Место хранения</th>
                                        <th>Последнее обновление</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['item_name']) ?></strong></td>
                                        <td><code><?= e($item['article'] ?? '—') ?></code></td>
                                        <td><strong><?= number_format($item['quantity'], 2, ',', ' ') ?></strong></td>
                                        <td><?= e($item['unit'] ?? 'шт.') ?></td>
                                        <td><?= e($item['location'] ?? '—') ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($item['updated_at'])) ?></td>
                                        <td>
                                            <?php if ($item['quantity'] <= 10): ?>
                                                <span class="badge badge-danger">Мало</span>
                                            <?php elseif ($item['quantity'] <= 50): ?>
                                                <span class="badge badge-warning">Заканчивается</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">В наличии</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if (empty($items)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📦</div>
                                    <h3>Нет данных</h3>
                                    <p>Добавьте товары на склад</p>
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
