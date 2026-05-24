<?php
/**
 * Список производственных заданий
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Производство';

$status = $_GET['status'] ?? '';
$orderId = $_GET['order'] ?? '';

$sql = "SELECT po.*, o.order_number, p.name as product_name, p.article as product_article, 
               p.description as product_description, p.specifications as product_specifications,
               p.base_price as product_price, p.is_active as product_is_active,
               c.name as category_name, u.name as unit_name,
               u2.full_name as responsible_name, u3.full_name as worker_name
        FROM production_orders po
        JOIN orders o ON po.order_id = o.id
        JOIN products p ON po.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN units u ON p.unit_id = u.id
        LEFT JOIN users u2 ON po.responsible_user_id = u2.id
        LEFT JOIN users u3 ON po.worker_id = u3.id
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND po.status_id = ?";
    $params[] = $status;
}

if ($orderId) {
    $sql .= " AND po.order_id = ?";
    $params[] = $orderId;
}

$sql .= " ORDER BY po.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
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
                            <h2>🏭 Производство</h2>
                            <p>Управление производственными заданиями</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('production.create')): ?>
                                <a href="create.php" class="btn btn-primary">+ Задание</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="filter-form">
                                <div class="filter-row">
                                    <select name="status" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        <option value="">Все статусы</option>
                                        <option value="planned" <?= $status === 'planned' ? 'selected' : '' ?>>Планируется</option>
                                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Завершено</option>
                                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Отменено</option>
                                    </select>
                                    <button type="submit" class="btn btn-secondary">Фильтр</button>
                                    <a href="list.php" class="btn btn-outline">Сброс</a>
                                </div>
                            </form>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>№</th>
                                        <th>Продукция</th>
                                        <th>Заказ</th>
                                        <th>Кол-во</th>
                                        <th>План. дата</th>
                                        <th>Статус</th>
                                        <th>Ответственный</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $t): ?>
                                    <?php 
                                    // Подготовка данных о продукте для модального окна
                                    $productData = [
                                        'id' => $t['product_id'],
                                        'name' => $t['product_name'] ?? '',
                                        'article' => $t['product_article'] ?? '',
                                        'category_name' => $t['category_name'] ?? '',
                                        'unit_name' => $t['unit_name'] ?? '',
                                        'base_price' => $t['product_price'] ?? 0,
                                        'is_active' => (int)($t['product_is_active'] ?? 0),
                                        'description' => $t['product_description'] ?? '',
                                        'specifications' => $t['product_specifications'] ?? ''
                                    ];
                                    
                                    // Декодирование спецификаций
                                    $specsDecoded = null;
                                    if (!empty($t['product_specifications'])) {
                                        $decoded = json_decode($t['product_specifications'], true);
                                        if (is_array($decoded)) {
                                            $specsDecoded = $decoded;
                                        }
                                    }
                                    $productData['specs_decoded'] = $specsDecoded;
                                    
                                    // Данные о задании
                                    $taskData = [
                                        'id' => $t['id'],
                                        'order_number' => $t['order_number'] ?? '',
                                        'quantity' => $t['quantity'] ?? 0,
                                        'planned_date' => $t['planned_date'] ?? '',
                                        'status' => $t['status'] ?? '',
                                        'responsible_name' => $t['responsible_name'] ?? ''
                                    ];
                                    ?>
                                    <tr class="table-row-clickable" onclick="openProductionProductModal(<?= json_encode($productData, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($taskData, JSON_UNESCAPED_UNICODE) ?>)">
                                        <td><strong>#<?= $t['id'] ?></strong></td>
                                        <td><strong><?= e($t['product_name']) ?></strong></td>
                                        <td><?= e($t['order_number']) ?></td>
                                        <td><?= $t['quantity'] ?></td>
                                        <td><?= date('d.m.Y', strtotime($t['planned_date'])) ?></td>
                                        <td>
                                            <?php if ($t['status'] === 'planned'): ?>
                                                <span class="badge badge-warning">Планируется</span>
                                            <?php elseif ($t['status'] === 'in_progress'): ?>
                                                <span class="badge badge-info">В работе</span>
                                            <?php elseif ($t['status'] === 'completed'): ?>
                                                <span class="badge badge-success">Завершено</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Отменено</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($t['responsible_name'] ?? '—') ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <a href="view.php?id=<?= $t['id'] ?>" class="btn-icon" title="Просмотр задания">👁️</a>
                                            <?php if (hasPermission('production.edit')): ?>
                                                <a href="edit.php?id=<?= $t['id'] ?>" class="btn-icon" title="Редактировать задание">✏️</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if (empty($tasks)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">🏭</div>
                                    <h3>Заданий нет</h3>
                                    <p>Создайте первое производственное задание</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно просмотра продукции из производства -->
    <div id="productionProductModalOverlay" class="product-modal-overlay" onclick="closeProductionProductModal(event)">
        <div class="product-modal">
            <div class="product-modal-header">
                <div>
                    <h3 class="product-modal-title" id="productionModalProductName">Название продукции</h3>
                    <p class="product-modal-subtitle" id="productionModalProductArticle">Артикул: —</p>
                </div>
                <button class="product-modal-close" onclick="closeProductionProductModalDirect()">×</button>
            </div>
            <div class="product-modal-body" id="productionModalProductBody">
                <!-- Контент будет заполнен через JS -->
            </div>
        </div>
    </div>
    
    <script>
        function openProductionProductModal(product, task) {
            console.log('Opening production product:', product, task);
            
            document.getElementById('productionModalProductName').textContent = product.name || 'Без названия';
            document.getElementById('productionModalProductArticle').textContent = 'Артикул: ' + (product.article || '—');
            
            var statusBadge = '';
            if (product.is_active !== undefined && product.is_active !== null) {
                var statusClass = product.is_active == 1 ? 'product-status-active' : 'product-status-inactive';
                var statusText = product.is_active == 1 ? '✓ Активно' : '○ Не активно';
                statusBadge = '<span class="product-status-badge ' + statusClass + '">' + statusText + '</span>';
            }
            
            var html = '';
            
            // Информация о производственном задании
            html += '<div class="product-detail-section">';
            html += '<div class="product-detail-label">Производственное задание</div>';
            html += '<div class="product-specs-grid">';
            html += '<div class="product-spec-item"><div class="product-spec-name">№ задания</div><div class="product-spec-value">#' + (task.id || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Заказ</div><div class="product-spec-value">' + (task.order_number || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Количество</div><div class="product-spec-value">' + (task.quantity || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">План. дата</div><div class="product-spec-value">' + (task.planned_date || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Статус</div><div class="product-spec-value">' + (task.status || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Ответственный</div><div class="product-spec-value">' + (task.responsible_name || '—') + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Основная информация о продукции в виде сетки
            html += '<div class="product-detail-section">';
            html += '<div class="product-detail-label">Основная информация</div>';
            html += '<div class="product-specs-grid">';
            html += '<div class="product-spec-item"><div class="product-spec-name">Категория</div><div class="product-spec-value">' + (product.category_name || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Ед. измерения</div><div class="product-spec-value">' + (product.unit_name || '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Цена</div><div class="product-spec-value">' + (product.base_price ? Number(product.base_price).toFixed(2).replace('.', ',') + ' BYN' : '—') + '</div></div>';
            html += '<div class="product-spec-item"><div class="product-spec-name">Статус</div><div class="product-spec-value">' + statusBadge + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Полное описание
            if (product.description && product.description.trim() !== '') {
                html += '<div class="product-detail-section">';
                html += '<div class="product-detail-label">Полное описание</div>';
                html += '<div class="product-description-box">';
                html += '<div class="product-description-text">' + escapeHtml(product.description) + '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            // Характеристики из JSON (если есть декодированные данные)
            if (product.specs_decoded && typeof product.specs_decoded === 'object') {
                var specsObj = product.specs_decoded;
                if (specsObj && typeof specsObj === 'object' && Object.keys(specsObj).length > 0) {
                    html += '<div class="product-detail-section">';
                    html += '<div class="product-detail-label">Характеристики</div>';
                    html += '<div class="product-specs-grid">';
                    for (var key in specsObj) {
                        if (specsObj.hasOwnProperty(key)) {
                            html += '<div class="product-spec-item">';
                            html += '<div class="product-spec-name">' + escapeHtml(key) + '</div>';
                            html += '<div class="product-spec-value">' + escapeHtml(String(specsObj[key])) + '</div>';
                            html += '</div>';
                        }
                    }
                    html += '</div>';
                    html += '</div>';
                }
            } else if (product.specifications && product.specifications.trim() !== '') {
                // Старый формат - просто текст
                html += '<div class="product-detail-section">';
                html += '<div class="product-detail-label">Характеристики</div>';
                html += '<div class="product-description-box" style="border-left-color: var(--info-color);">';
                html += '<div class="product-description-text">' + escapeHtml(product.specifications) + '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            document.getElementById('productionModalProductBody').innerHTML = html;
            document.getElementById('productionProductModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeProductionProductModal(event) {
            if (event.target === document.getElementById('productionProductModalOverlay')) {
                closeProductionProductModalDirect();
            }
        }
        
        function closeProductionProductModalDirect() {
            document.getElementById('productionProductModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
        
        // Закрытие по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('productionProductModalOverlay');
                if (modal.classList.contains('active')) {
                    closeProductionProductModalDirect();
                }
            }
        });
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
