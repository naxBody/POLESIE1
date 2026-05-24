<?php
/**
 * Создание нового заказа
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!hasPermission('orders.create')) {
    die('Доступ запрещен');
}

$user = getCurrentUser();
$pdo = getDbConnection();

$errors = [];
$success = false;

// Получение справочников
$contractors = $pdo->query("SELECT * FROM contractors WHERE is_active = TRUE ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT p.*, pc.name as category_name, u.short_name as unit_name 
                         FROM products p 
                         LEFT JOIN product_categories pc ON p.category_id = pc.id 
                         LEFT JOIN units u ON p.unit_id = u.id 
                         WHERE p.is_active = TRUE ORDER BY p.name")->fetchAll();
$statuses = $pdo->query("SELECT * FROM order_statuses WHERE is_active = TRUE ORDER BY sort_order")->fetchAll();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $contractorId = (int)($_POST['contractor_id'] ?? 0);
        $statusId = (int)($_POST['status_id'] ?? 1);
        $orderDate = $_POST['order_date'] ?? date('Y-m-d');
        $deliveryDate = $_POST['delivery_date'] ?: null;
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $paymentTerms = trim($_POST['payment_terms'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $contractNumber = trim($_POST['contract_number'] ?? '');
        $contractDate = $_POST['contract_date'] ?: null;
        $responsibleUserId = $_POST['responsible_user_id'] ?: null;
        
        // Валидация
        if (!$contractorId) {
            $errors[] = 'Выберите заказчика';
        }
        
        $items = $_POST['items'] ?? [];
        if (empty($items)) {
            $errors[] = 'Добавьте хотя бы одну позицию';
        }
        
        if (empty($errors)) {
            // Генерация номера заказа
            $orderNumber = generateUniqueNumber('ORD', 'orders', 'order_number');
            
            // Создание заказа
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_number, contractor_id, status_id, order_date, delivery_date, 
                                   delivery_address, payment_terms, notes, contract_number, contract_date,
                                   responsible_user_id, created_by, total_amount, currency)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'BYN')
            ");
            
            $stmt->execute([
                $orderNumber, $contractorId, $statusId, $orderDate, $deliveryDate,
                $deliveryAddress, $paymentTerms, $notes, $contractNumber, $contractDate,
                $responsibleUserId, $user['id']
            ]);
            
            $orderId = $pdo->lastInsertId();
            $totalAmount = 0;
            
            // Добавление позиций
            foreach ($items as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }
                
                $productId = (int)$item['product_id'];
                $quantity = (float)$item['quantity'];
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                
                $totalPrice = $quantity * $unitPrice * (1 - $discount / 100);
                $totalAmount += $totalPrice;
                
                $itemStmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount, total_price, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $itemStmt->execute([
                    $orderId, $productId, $quantity, $unitPrice, $discount, $totalPrice,
                    $item['notes'] ?? ''
                ]);
            }
            
            // Обновление общей суммы
            $updateStmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $updateStmt->execute([$totalAmount, $orderId]);
            
            logActivity('order_create', 'order', $orderId, null, ['order_number' => $orderNumber]);
            
            $pdo->commit();
            $success = true;
            
            // Перенаправление на просмотр
            header("Location: view.php?id=$orderId&success=1");
            exit;
        }
        
        $pdo->rollBack();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$pageTitle = 'Новый заказ';
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
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Создание нового заказа</h3>
                        <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                    </div>
                    
                    <form method="POST" action="" id="orderForm">
                        <div class="card-body">
                            <!-- Основная информация -->
                            <h4 style="margin-bottom: 16px; color: var(--text-primary);">Основная информация</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Заказчик *</label>
                                    <select name="contractor_id" class="form-control" required>
                                        <option value="">Выберите заказчика</option>
                                        <?php foreach ($contractors as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (($_POST['contractor_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (ИНН: <?= e($c['inn']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Статус</label>
                                    <select name="status_id" class="form-control">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= (($_POST['status_id'] ?? 1) == $s['id']) ? 'selected' : '' ?>>
                                            <?= e($s['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Дата заказа *</label>
                                    <input type="date" name="order_date" class="form-control" 
                                           value="<?= $_POST['order_date'] ?? date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата доставки</label>
                                    <input type="date" name="delivery_date" class="form-control" 
                                           value="<?= $_POST['delivery_date'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Адрес доставки</label>
                                <textarea name="delivery_address" class="form-control" rows="2"><?= e($_POST['delivery_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Номер договора</label>
                                    <input type="text" name="contract_number" class="form-control" 
                                           value="<?= e($_POST['contract_number'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата договора</label>
                                    <input type="date" name="contract_date" class="form-control" 
                                           value="<?= $_POST['contract_date'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Условия оплаты</label>
                                <textarea name="payment_terms" class="form-control" rows="2"><?= e($_POST['payment_terms'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Ответственный</label>
                                <select name="responsible_user_id" class="form-control">
                                    <option value="">Не назначен</option>
                                    <?php
                                    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = TRUE ORDER BY full_name")->fetchAll();
                                    foreach ($users as $u):
                                    ?>
                                    <option value="<?= $u['id'] ?>" <?= (($_POST['responsible_user_id'] ?? 0) == $u['id']) ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Позиции заказа -->
                            <h4 style="margin: 32px 0 16px; color: var(--text-primary);">Позиции заказа</h4>
                            
                            <div id="orderItems">
                                <div class="order-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Продукция</label>
                                        <select name="items[0][product_id]" class="form-control product-select" required>
                                            <option value="">Выберите продукцию</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                                                <?= e($p['article']) ?> - <?= e($p['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Количество</label>
                                        <input type="number" name="items[0][quantity]" class="form-control quantity" step="0.001" min="0" required>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Цена (BYN)</label>
                                        <input type="number" name="items[0][unit_price]" class="form-control unit-price" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Скидка (%)</label>
                                        <input type="number" name="items[0][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0">
                                    </div>
                                    
                                    <div style="padding-top: 28px;">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" disabled>🗑️</button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-secondary" onclick="addItem()">+ Добавить позицию</button>
                            
                            <div class="form-group" style="margin-top: 24px;">
                                <label class="form-label">Примечание</label>
                                <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary">💾 Создать заказ</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        let itemCount = 1;
        
        function addItem() {
            const container = document.getElementById('orderItems');
            const newItem = document.createElement('div');
            newItem.className = 'order-item';
            newItem.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;';
            newItem.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Продукция</label>
                    <select name="items[${itemCount}][product_id]" class="form-control product-select" required>
                        <option value="">Выберите продукцию</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                            <?= e($p['article']) ?> - <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Количество</label>
                    <input type="number" name="items[${itemCount}][quantity]" class="form-control quantity" step="0.001" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Цена (BYN)</label>
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-control unit-price" step="0.01" min="0">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Скидка (%)</label>
                    <input type="number" name="items[${itemCount}][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0">
                </div>
                <div style="padding-top: 28px;">
                    <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove()">🗑️</button>
                </div>
            `;
            container.appendChild(newItem);
            itemCount++;
        }
        
        // Автозаполнение цены при выборе продукции
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const option = e.target.options[e.target.selectedIndex];
                const price = option.dataset.price;
                if (price && price > 0) {
                    const row = e.target.closest('.order-item');
                    const priceInput = row.querySelector('.unit-price');
                    if (priceInput && !priceInput.value) {
                        priceInput.value = price;
                    }
                }
            }
        });
    </script>
</body>
</html>
