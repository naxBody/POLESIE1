<?php
/**
 * Управление серийными номерами продукции
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Серийные номера';

// Обработка действий
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' && hasPermission('production.create')) {
            $productId = (int)$_POST['product_id'];
            $productionOrderId = !empty($_POST['production_order_id']) ? (int)$_POST['production_order_id'] : null;
            $serialNumber = trim($_POST['serial_number']);
            $manufactureDate = $_POST['manufacture_date'];
            $warrantyStart = !empty($_POST['warranty_start']) ? $_POST['warranty_start'] : null;
            $warrantyEnd = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;
            $technicalSpecs = !empty($_POST['technical_specs']) ? json_decode($_POST['technical_specs'], true) : null;
            $notes = trim($_POST['notes'] ?? '');
            
            // Проверка уникальности серийного номера
            $checkStmt = $pdo->prepare("SELECT id FROM product_serial_numbers WHERE serial_number = ?");
            $checkStmt->execute([$serialNumber]);
            if ($checkStmt->fetch()) {
                throw new Exception('Серийный номер уже существует');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO product_serial_numbers 
                (serial_number, product_id, production_order_id, manufacture_date, warranty_start, warranty_end, 
                 technical_specs, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $serialNumber, $productId, $productionOrderId, $manufactureDate, 
                $warrantyStart, $warrantyEnd, 
                $technicalSpecs ? json_encode($technicalSpecs, JSON_UNESCAPED_UNICODE) : null,
                $notes, $user['id']
            ]);
            
            $message = 'Серийный номер успешно создан';
            $messageType = 'success';
            
        } elseif ($action === 'update' && hasPermission('production.edit')) {
            $id = (int)$_POST['id'];
            $manufactureDate = $_POST['manufacture_date'];
            $warrantyStart = !empty($_POST['warranty_start']) ? $_POST['warranty_start'] : null;
            $warrantyEnd = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;
            $status = $_POST['status'];
            $technicalSpecs = !empty($_POST['technical_specs']) ? json_decode($_POST['technical_specs'], true) : null;
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $pdo->prepare("
                UPDATE product_serial_numbers 
                SET manufacture_date = ?, warranty_start = ?, warranty_end = ?, status = ?,
                    technical_specs = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $manufactureDate, $warrantyStart, $warrantyEnd, $status,
                $technicalSpecs ? json_encode($technicalSpecs, JSON_UNESCAPED_UNICODE) : null,
                $notes, $id
            ]);
            
            $message = 'Данные обновлены';
            $messageType = 'success';
            
        } elseif ($action === 'delete' && hasPermission('production.delete')) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM product_serial_numbers WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'Серийный номер удалён';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$productFilter = $_GET['product'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT sn.*, p.name as product_name, p.article as product_article,
               po.production_number,
               u.full_name as created_by_name
        FROM product_serial_numbers sn
        JOIN products p ON sn.product_id = p.id
        LEFT JOIN production_orders po ON sn.production_order_id = po.id
        LEFT JOIN users u ON sn.created_by = u.id
        WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND sn.status = ?";
    $params[] = $statusFilter;
}

if ($productFilter) {
    $sql .= " AND sn.product_id = ?";
    $params[] = $productFilter;
}

if ($search) {
    $sql .= " AND (sn.serial_number LIKE ? OR p.name LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY sn.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$serialNumbers = $stmt->fetchAll();

// Список продуктов для фильтра
$productsStmt = $pdo->query("SELECT id, name, article FROM products WHERE is_active = 1 ORDER BY name");
$products = $productsStmt->fetchAll();
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
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-warranty { background: #dbeafe; color: #1e40af; }
        .status-expired { background: #fef3c7; color: #92400e; }
        .status-returned { background: #fee2e2; color: #991b1b; }
        .status-scrapped { background: #e5e7eb; color: #374151; }
        
        .passport-preview-btn {
            background: var(--primary-color);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
        }
        .passport-preview-btn:hover {
            background: var(--primary-dark);
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
                            <h2>🔢 Серийные номера</h2>
                            <p>Учёт выпущенной продукции с индивидуальными номерами</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('production.create')): ?>
                                <button class="btn btn-primary" onclick="openCreateModal()">+ Добавить</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= e($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="filter-form">
                                <div class="filter-row">
                                    <input type="text" name="search" placeholder="Поиск по серийному номеру..." 
                                           value="<?= e($search) ?>" 
                                           style="width: 250px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <select name="status" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        <option value="">Все статусы</option>
                                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Активен</option>
                                        <option value="warranty" <?= $statusFilter === 'warranty' ? 'selected' : '' ?>>На гарантии</option>
                                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Истёк</option>
                                        <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Возврат</option>
                                        <option value="scrapped" <?= $statusFilter === 'scrapped' ? 'selected' : '' ?>>Списан</option>
                                    </select>
                                    <select name="product" style="width: 250px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        <option value="">Все продукты</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?= $prod['id'] ?>" <?= $productFilter == $prod['id'] ? 'selected' : '' ?>>
                                                <?= e($prod['name']) ?> (<?= e($prod['article']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-secondary">Фильтр</button>
                                    <a href="serial_numbers.php" class="btn btn-outline">Сброс</a>
                                </div>
                            </form>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Серийный №</th>
                                        <th>Продукция</th>
                                        <th>Производство</th>
                                        <th>Дата выпуска</th>
                                        <th>Гарантия</th>
                                        <th>Статус</th>
                                        <th>Документы</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($serialNumbers as $sn): ?>
                                    <?php 
                                    $statusClass = 'status-' . $sn['status'];
                                    $statusLabels = [
                                        'active' => '✓ Активен',
                                        'warranty' => '🛡️ На гарантии',
                                        'expired' => '⏰ Истёк',
                                        'returned' => '↩️ Возврат',
                                        'scrapped' => '❌ Списан'
                                    ];
                                    ?>
                                    <tr>
                                        <td><strong><?= e($sn['serial_number']) ?></strong></td>
                                        <td>
                                            <div><?= e($sn['product_name']) ?></div>
                                            <small style="color: var(--text-secondary);"><?= e($sn['product_article']) ?></small>
                                        </td>
                                        <td><?= $sn['production_number'] ? e($sn['production_number']) : '—' ?></td>
                                        <td><?= date('d.m.Y', strtotime($sn['manufacture_date'])) ?></td>
                                        <td>
                                            <?php if ($sn['warranty_end']): ?>
                                                <small>до <?= date('d.m.Y', strtotime($sn['warranty_end'])) ?></small>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabels[$sn['status']] ?? $sn['status'] ?></span></td>
                                        <td>
                                            <a href="view_passport.php?id=<?= $sn['id'] ?>" class="passport-preview-btn">📄 Паспорт</a>
                                        </td>
                                        <td>
                                            <a href="view_passport.php?id=<?= $sn['id'] ?>" class="btn-icon" title="Просмотр">👁️</a>
                                            <?php if (hasPermission('production.edit')): ?>
                                                <button class="btn-icon" onclick="editSerial(<?= $sn['id'] ?>)" title="Редактировать">✏️</button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('production.delete')): ?>
                                                <button class="btn-icon" onclick="deleteSerial(<?= $sn['id'] ?>)" title="Удалить">🗑️</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if (empty($serialNumbers)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">🔢</div>
                                    <h3>Серийных номеров нет</h3>
                                    <p>Добавьте первый серийный номер продукции</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания/редактирования -->
    <div id="serialModalOverlay" class="product-modal-overlay" style="display: none;">
        <div class="product-modal">
            <div class="product-modal-header">
                <h3 class="product-modal-title" id="modalTitle">Добавить серийный номер</h3>
                <button class="product-modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="product-modal-body">
                <form id="serialForm" method="POST" action="serial_numbers.php">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId">
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Серийный номер *</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="serial_number" id="formSerial" required 
                                   style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            <button type="button" class="btn btn-secondary" onclick="generateSerialNumber()" title="Сгенерировать автоматически">🔄</button>
                        </div>
                        <small id="serialHint" style="color: var(--text-secondary); display: none; margin-top: 4px;">
                            Серийный номер будет сформирован из названия продукта и порядкового номера
                        </small>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Продукция *</label>
                        <select name="product_id" id="formProduct" required 
                                style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            <option value="">Выберите продукцию</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>"><?= e($prod['name']) ?> (<?= e($prod['article']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Дата выпуска *</label>
                        <input type="date" name="manufacture_date" id="formDate" required 
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Начало гарантии</label>
                            <input type="date" name="warranty_start" id="formWarrantyStart" 
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Окончание гарантии</label>
                            <input type="date" name="warranty_end" id="formWarrantyEnd" 
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Статус</label>
                        <select name="status" id="formStatus" 
                                style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            <option value="active">Активен</option>
                            <option value="warranty">На гарантии</option>
                            <option value="expired">Истёк</option>
                            <option value="returned">Возврат</option>
                            <option value="scrapped">Списан</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Технические характеристики (JSON)</label>
                        <textarea name="technical_specs" id="formSpecs" rows="4" 
                                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-family: monospace;"
                                  placeholder='{"power": "3.0 kW", "voltage": "380V"}'></textarea>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Примечания</label>
                        <textarea name="notes" id="formNotes" rows="3" 
                                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="closeModal()" class="btn btn-outline">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('formAction').value = 'create';
            document.getElementById('modalTitle').textContent = 'Добавить серийный номер';
            document.getElementById('serialForm').reset();
            document.getElementById('serialHint').style.display = 'none';
            document.getElementById('serialModalOverlay').style.display = 'flex';
        }
        
        // Генерация серийного номера автоматически
        function generateSerialNumber() {
            const productId = document.getElementById('formProduct').value;
            
            if (!productId) {
                alert('Сначала выберите продукцию!');
                document.getElementById('formProduct').focus();
                return;
            }
            
            const hintElement = document.getElementById('serialHint');
            hintElement.style.display = 'block';
            hintElement.textContent = 'Генерация...';
            
            fetch('api_serial.php?action=generate&product_id=' + productId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    document.getElementById('formSerial').value = data.serial_number;
                    hintElement.style.display = 'block';
                    hintElement.innerHTML = '✓ Сформирован: <strong>' + data.prefix + '</strong> (префикс) + <strong>' + data.number + '</strong> (порядковый номер)';
                    hintElement.style.color = 'var(--success-color, #10b981)';
                })
                .catch(err => {
                    hintElement.style.display = 'block';
                    hintElement.textContent = '✗ Ошибка: ' + err.message;
                    hintElement.style.color = 'var(--error-color, #ef4444)';
                });
        }
        
        // Автогенерация при выборе продукта (если поле серийного номера пустое)
        document.getElementById('formProduct')?.addEventListener('change', function() {
            const serialField = document.getElementById('formSerial');
            if (this.value && !serialField.value && document.getElementById('formAction').value === 'create') {
                // Можно раскомментировать для автогенерации сразу при выборе
                // generateSerialNumber();
                
                // Или просто показать подсказку
                const hintElement = document.getElementById('serialHint');
                hintElement.style.display = 'block';
                hintElement.textContent = 'Нажмите 🔄 для автоматической генерации серийного номера';
                hintElement.style.color = 'var(--text-secondary)';
            }
        });
        
        function editSerial(id) {
            // Получение данных через AJAX
            fetch('api_serial.php?action=get&id=' + id)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('formId').value = data.id;
                    document.getElementById('formSerial').value = data.serial_number;
                    document.getElementById('formProduct').value = data.product_id;
                    document.getElementById('formDate').value = data.manufacture_date;
                    document.getElementById('formWarrantyStart').value = data.warranty_start || '';
                    document.getElementById('formWarrantyEnd').value = data.warranty_end || '';
                    document.getElementById('formStatus').value = data.status;
                    document.getElementById('formSpecs').value = data.technical_specs || '';
                    document.getElementById('formNotes').value = data.notes || '';
                    document.getElementById('modalTitle').textContent = 'Редактировать: ' + data.serial_number;
                    document.getElementById('serialHint').style.display = 'none';
                    document.getElementById('serialModalOverlay').style.display = 'flex';
                });
        }
        
        function closeModal() {
            document.getElementById('serialModalOverlay').style.display = 'none';
        }
        
        function deleteSerial(id) {
            if (confirm('Вы уверены? Это действие нельзя отменить.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'serial_numbers.php';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
