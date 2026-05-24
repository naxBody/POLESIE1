<?php
/**
 * Список контрагентов
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Контрагенты';

// Получение списка контрагентов
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

$sql = "SELECT * FROM contractors WHERE is_active = TRUE";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR unp LIKE ? OR contact_person LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

if ($type) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contractors = $stmt->fetchAll();

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>📋 Контрагенты</h2>
            <p>База заказчиков и поставщиков</p>
        </div>
        <div class="page-header-actions">
            <?php if (hasPermission('contractors.create')): ?>
                <a href="create.php" class="btn btn-primary">+ Добавить</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Поиск по названию, УНП..." value="<?= e($search) ?>" 
                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <select name="type" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="">Все типы</option>
                        <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Заказчик</option>
                        <option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>Поставщик</option>
                        <option value="both" <?= $type === 'both' ? 'selected' : '' ?>>Оба</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Тип</th>
                        <th>УНП</th>
                        <th>Контактное лицо</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contractors as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td>
                            <?php if ($c['type'] === 'customer'): ?>
                                <span class="badge badge-success">Заказчик</span>
                            <?php elseif ($c['type'] === 'supplier'): ?>
                                <span class="badge badge-info">Поставщик</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Оба</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($c['unp']) ?></td>
                        <td><?= e($c['contact_person'] ?? '—') ?></td>
                        <td><?= e($c['phone'] ?? '—') ?></td>
                        <td><?= e($c['email'] ?? '—') ?></td>
                        <td>
                            <?php if (hasPermission('contractors.edit')): ?>
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn-icon" title="Редактировать">✏️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($contractors)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📁</div>
                    <h3>Контрагенты не найдены</h3>
                    <p>Добавьте первого контрагента для начала работы</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
