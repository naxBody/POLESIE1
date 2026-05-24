<?php
/**
 * Список сотрудников
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Сотрудники';

$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';

$sql = "SELECT e.*, u.username, r.name as role_name 
        FROM employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN user_roles r ON u.role_id = r.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (e.full_name LIKE ? OR e.position LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

if ($department) {
    $sql .= " AND e.department = ?";
    $params[] = $department;
}

$sql .= " ORDER BY e.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>👥 Сотрудники</h2>
            <p>Управление персоналом предприятия</p>
        </div>
        <div class="page-header-actions">
            <?php if (hasPermission('employees.create')): ?>
                <a href="create.php" class="btn btn-primary">+ Добавить</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Поиск по ФИО, должности..." value="<?= e($search) ?>" 
                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <select name="department" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="">Все отделы</option>
                        <option value="production" <?= $department === 'production' ? 'selected' : '' ?>>Производство</option>
                        <option value="quality" <?= $department === 'quality' ? 'selected' : '' ?>>ОТК</option>
                        <option value="warehouse" <?= $department === 'warehouse' ? 'selected' : '' ?>>Склад</option>
                        <option value="management" <?= $department === 'management' ? 'selected' : '' ?>>Руководство</option>
                        <option value="sales" <?= $department === 'sales' ? 'selected' : '' ?>>Отдел продаж</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Должность</th>
                        <th>Отдел</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Роль в системе</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><strong><?= e($e['full_name']) ?></strong></td>
                        <td><?= e($e['position']) ?></td>
                        <td>
                            <?php if ($e['department'] === 'production'): ?>
                                <span class="badge badge-info">Производство</span>
                            <?php elseif ($e['department'] === 'quality'): ?>
                                <span class="badge badge-success">ОТК</span>
                            <?php elseif ($e['department'] === 'warehouse'): ?>
                                <span class="badge badge-warning">Склад</span>
                            <?php elseif ($e['department'] === 'management'): ?>
                                <span class="badge badge-purple">Руководство</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?= e($e['department']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($e['phone'] ?? '—') ?></td>
                        <td><?= e($e['email'] ?? '—') ?></td>
                        <td><?= e($e['role_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($e['is_active']): ?>
                                <span class="badge badge-success">Работает</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Уволен</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (hasPermission('employees.edit')): ?>
                                <a href="edit.php?id=<?= $e['id'] ?>" class="btn-icon" title="Редактировать">✏️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($employees)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <h3>Сотрудники не найдены</h3>
                    <p>Добавьте первого сотрудника</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
