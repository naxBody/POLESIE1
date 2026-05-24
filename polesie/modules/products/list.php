<?php
/**
 * Список продукции
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Продукция';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT p.*, c.name as category_name, u.name as unit_name FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.article LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Получение серийных номеров и документов для каждого продукта
foreach ($products as &$product) {
    // Получение последнего серийного номера для продукта
    $serialStmt = $pdo->prepare("SELECT id, serial_number, manufacture_date, warranty_start, warranty_end, notes 
                                  FROM product_serial_numbers 
                                  WHERE product_id = ? 
                                  ORDER BY created_at DESC LIMIT 1");
    $serialStmt->execute([$product['id']]);
    $serialData = $serialStmt->fetch();
    
    if ($serialData) {
        $product['serial_number'] = $serialData['serial_number'];
        $product['manufacture_date'] = $serialData['manufacture_date'];
        $product['warranty_start'] = $serialData['warranty_start'];
        $product['warranty_end'] = $serialData['warranty_end'];
        $product['notes'] = $serialData['notes'];
        
        // Получение документов для этого серийного номера
        $docsStmt = $pdo->prepare("SELECT document_type, file_name, file_path, file_size, mime_type, description 
                                   FROM product_documents 
                                   WHERE serial_number_id = ? 
                                   ORDER BY uploaded_at DESC");
        $docsStmt->execute([$serialData['id']]);
        $docs = $docsStmt->fetchAll();
        
        $product['documents'] = [];
        foreach ($docs as $doc) {
            $product['documents'][] = [
                'type' => $doc['document_type'],
                'name' => $doc['description'] ?: $doc['file_name'],
                'url' => $doc['file_path'],
                'size' => $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : null
            ];
        }
        
        // Получение пути к руководству
        $product['manual_url'] = $serialData['manual_file_path'] ?? null;
    } else {
        $product['serial_number'] = null;
        $product['manufacture_date'] = null;
        $product['warranty_start'] = null;
        $product['warranty_end'] = null;
        $product['notes'] = null;
        $product['documents'] = [];
        $product['manual_url'] = null;
    }
    
    // Декодирование JSON спецификаций
    if (!empty($product['specifications'])) {
        $decoded = json_decode($product['specifications'], true);
        if (is_array($decoded)) {
            $product['specs_decoded'] = $decoded;
        } else {
            $product['specs_decoded'] = null;
        }
    } else {
        $product['specs_decoded'] = null;
    }
}

// Получение категорий
$catStmt = $pdo->query("SELECT * FROM product_categories ORDER BY name");
$categories = $catStmt->fetchAll();
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
                            <h2>📦 Продукция</h2>
                            <p>Каталог выпускаемой продукции</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('products.create')): ?>
                                <a href="create.php" class="btn btn-primary">+ Добавить</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Поиск по названию, артикулу..." value="<?= e($search) ?>" 
                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <select name="category" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Артикул</th>
                        <th>Наименование</th>
                        <th>Категория</th>
                        <th>Ед. изм.</th>
                        <th>Цена (BYN)</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <?php 
                    // Подготовка данных для безопасной передачи в JS
                    // specs_decoded уже декодирован из JSON в PHP, поэтому передаём как объект
                    $specsDecoded = null;
                    if (!empty($p['specs_decoded']) && is_array($p['specs_decoded'])) {
                        $specsDecoded = $p['specs_decoded'];
                    }
                    
                    $productData = [
                        'id' => $p['id'],
                        'name' => $p['name'] ?? '',
                        'article' => $p['article'] ?? '',
                        'category_name' => $p['category_name'] ?? '',
                        'unit_name' => $p['unit_name'] ?? $p['unit'] ?? '',
                        'base_price' => $p['base_price'] ?? $p['price'] ?? 0,
                        'is_active' => (int)($p['is_active'] ?? 0),
                        'description' => $p['description'] ?? '',
                        'specifications' => $p['specifications'] ?? '',
                        'specs_decoded' => $specsDecoded,
                        'serial_number' => $p['serial_number'] ?? null,
                        'manufacture_date' => $p['manufacture_date'] ?? null,
                        'warranty_start' => $p['warranty_start'] ?? null,
                        'warranty_end' => $p['warranty_end'] ?? null,
                        'documents' => $p['documents'] ?? [],
                        'manual_url' => $p['manual_url'] ?? null,
                        'created_at' => $p['created_at'] ?? '',
                        'updated_at' => $p['updated_at'] ?? ''
                    ];
                    ?>
                    <tr class="table-row-clickable" onclick="openProductModal(<?= json_encode($productData, JSON_UNESCAPED_UNICODE) ?>)">
                        <td><code><?= e($p['article']) ?></code></td>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><?= e($p['category_name'] ?? '—') ?></td>
                        <td><?= e($p['unit_name'] ?? $p['unit'] ?? '—') ?></td>
                        <td><?= number_format($p['base_price'] ?? $p['price'], 2, ',', ' ') ?></td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="badge badge-success">✓ Активно</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">○ Не активно</span>
                            <?php endif; ?>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <?php if (hasPermission('products.edit')): ?>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn-icon" title="Редактировать">✏️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>Продукция не найдена</h3>
                    <p>Добавьте первую позицию продукции</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
    
    <!-- Модальное окно просмотра продукции -->
    <div id="productModalOverlay" class="product-modal-overlay" onclick="closeProductModal(event)">
        <div class="product-modal product-passport-modal">
            <div class="product-modal-header passport-header">
                <div>
                    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">Паспорт изделия</div>
                    <h3 class="product-modal-title" id="modalProductName" style="margin-top: 8px; font-size: 22px;">Название продукции</h3>
                    <p class="product-modal-subtitle" id="modalProductArticle" style="margin-top: 6px; font-size: 14px;">Артикул: —</p>
                </div>
                <button class="product-modal-close" onclick="closeProductModalDirect()">×</button>
            </div>
            <div class="product-modal-body passport-body" id="modalProductBody" style="max-height: calc(85vh - 180px); overflow-y: auto;">
                <!-- Контент будет заполнен через JS -->
            </div>
            <div class="product-modal-footer passport-footer" style="padding: 20px 24px; background: white; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 12px; flex-shrink: 0;">
                <button id="btnPrintProductPassport" onclick="printProductPassport()" style="background: var(--primary-color); border: none; color: white; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 500; min-width: 200px;" title="Распечатать паспорт изделия">
                    🖨️ Распечатать паспорт
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function openProductModal(product) {
            console.log('Opening product:', product);
            
            // Сохраняем текущий продукт для использования в функции печати
            window.currentProduct = product;
            
            document.getElementById('modalProductName').textContent = product.name || 'Без названия';
            document.getElementById('modalProductArticle').textContent = 'Артикул: ' + (product.article || '—');
            
            var statusBadge = '';
            if (product.is_active !== undefined && product.is_active !== null) {
                var statusClass = product.is_active == 1 ? 'product-status-active' : 'product-status-inactive';
                var statusText = product.is_active == 1 ? '✓ Активно' : '○ Не активно';
                statusBadge = '<span class="product-status-badge ' + statusClass + '">' + statusText + '</span>';
            }
            
            var html = '';
            
            // Основная информация в виде списка как в паспорте
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📋 Основная информация</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-row"><div class="spec-label">Артикул</div><div class="spec-value">' + (product.article || '—') + '</div></div>';
            
            // Серийный номер - как обычное свойство в основной информации
            var serialNumber = product.serial_number || null;
            if (!serialNumber) {
                // Генерируем временный серийный номер для отображения
                var date = new Date();
                var year = date.getFullYear().toString().substr(-2);
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                serialNumber = 'SN-' + year + month + '-' + product.id + '-' + randomPart;
            }
            html += '<div class="spec-row"><div class="spec-label">Серийный номер</div><div class="spec-value">' + escapeHtml(serialNumber) + '</div></div>';
            
            html += '<div class="spec-row"><div class="spec-label">Категория</div><div class="spec-value">' + (product.category_name || '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Ед. измерения</div><div class="spec-value">' + (product.unit_name || '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Цена</div><div class="spec-value">' + (product.base_price ? Number(product.base_price).toFixed(2).replace('.', ',') + ' BYN' : '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Статус</div><div class="spec-value">' + statusBadge + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Технические характеристики из JSON (если есть декодированные данные)
            if (product.specs_decoded && typeof product.specs_decoded === 'object') {
                var specsObj = product.specs_decoded;
                if (specsObj && typeof specsObj === 'object' && Object.keys(specsObj).length > 0) {
                    html += '<div class="passport-section">';
                    html += '<div class="passport-section-title">⚙️ Технические характеристики</div>';
                    html += '<div class="specs-list">';
                    for (var key in specsObj) {
                        if (specsObj.hasOwnProperty(key)) {
                            html += '<div class="spec-row">';
                            html += '<div class="spec-label">' + escapeHtml(formatSpecName(key)) + '</div>';
                            html += '<div class="spec-value">' + escapeHtml(String(specsObj[key])) + '</div>';
                            html += '</div>';
                        }
                    }
                    html += '</div>';
                    html += '</div>';
                }
            } else if (product.specifications && product.specifications.trim() !== '') {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">⚙️ Характеристики</div>';
                html += '<div class="spec-row"><div class="spec-value" style="white-space: pre-wrap;">' + escapeHtml(product.specifications) + '</div></div>';
                html += '</div>';
            }
            
            // Даты и гарантия
            var hasDates = product.manufacture_date || product.warranty_start || product.warranty_end || product.warranty_months;
            if (hasDates) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📅 Даты и гарантия</div>';
                html += '<div class="specs-list">';
                if (product.manufacture_date) {
                    html += '<div class="spec-row"><div class="spec-label">Дата выпуска</div><div class="spec-value">' + formatDate(product.manufacture_date) + '</div></div>';
                }
                if (product.warranty_start) {
                    html += '<div class="spec-row"><div class="spec-label">Начало гарантии</div><div class="spec-value">' + formatDate(product.warranty_start) + '</div></div>';
                }
                if (product.warranty_end) {
                    html += '<div class="spec-row"><div class="spec-label">Гарантия до</div><div class="spec-value">' + formatDate(product.warranty_end) + '</div></div>';
                }
                if (product.warranty_months) {
                    html += '<div class="spec-row"><div class="spec-label">Срок гарантии</div><div class="spec-value">' + product.warranty_months + ' мес.</div></div>';
                }
                html += '</div>';
                html += '</div>';
            }
            
            // Полное описание
            if (product.description && product.description.trim() !== '') {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📝 Описание</div>';
                html += '<div class="spec-row"><div class="spec-value" style="white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(product.description) + '</div></div>';
                html += '</div>';
            }
            
            // Документы и паспорт
            if (product.documents && product.documents.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📎 Документы</div>';
                html += '<ul class="document-list">';
                for (var i = 0; i < product.documents.length; i++) {
                    var doc = product.documents[i];
                    var docIcon = getDocumentIcon(doc.type);
                    html += '<li class="document-row">';
                    html += '<div class="document-icon-large">' + docIcon + '</div>';
                    html += '<div class="document-info">';
                    html += '<div class="document-name">' + escapeHtml(doc.name) + '</div>';
                    if (doc.size) {
                        html += '<div class="document-meta">' + doc.size + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="document-actions">';
                    html += '<a href="' + escapeHtml(doc.url) + '" target="_blank" class="btn-icon" title="Просмотреть">👁️</a>';
                    html += '<a href="' + escapeHtml(doc.url) + '" download class="btn-icon" title="Скачать">⬇️</a>';
                    html += '</div>';
                    html += '</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            // Инструкция по эксплуатации
            if (product.manual_url || product.manual_text) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📘 Руководство по эксплуатации</div>';
                if (product.manual_url) {
                    html += '<a href="' + escapeHtml(product.manual_url) + '" target="_blank" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 12px;">📥 Скачать инструкцию</a>';
                }
                if (product.manual_text) {
                    html += '<div class="spec-row"><div class="spec-value" style="white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(product.manual_text) + '</div></div>';
                }
                html += '</div>';
            }
            
            // Дополнительная информация (дата создания, обновления)
            html += '<div class="passport-section" style="border-top: 2px solid var(--border-color); margin-top: 20px; padding-top: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 13px;">ℹ️ Дополнительно</div>';
            html += '<div class="specs-list">';
            if (product.created_at) {
                html += '<div class="spec-row"><div class="spec-label">Создан</div><div class="spec-value">' + escapeHtml(product.created_at) + '</div></div>';
            }
            if (product.updated_at) {
                html += '<div class="spec-row"><div class="spec-label">Обновлён</div><div class="spec-value">' + escapeHtml(product.updated_at) + '</div></div>';
            }
            html += '</div>';
            html += '</div>';
            
            document.getElementById('modalProductBody').innerHTML = html;
            document.getElementById('productModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function formatSpecName(key) {
            var names = {
                'power_kw': 'Мощность, кВт',
                'power_kw_min': 'Мощность мин., кВт',
                'power_kw_max': 'Мощность макс., кВт',
                'rpm': 'Обороты, об/мин',
                'shaft_height_mm': 'Высота оси, мм',
                'voltage_v': 'Напряжение, В',
                'frequency_hz': 'Частота, Гц',
                'climate_versions': 'Климатическое исполнение',
                'mounting_versions': 'Варианты монтажа',
                'protection_class': 'Класс защиты',
                'diameter_mm': 'Диаметр, мм',
                'material': 'Материал',
                'application': 'Применение',
                'type': 'Тип',
                'weight_kg': 'Вес, кг',
                'dimensions': 'Габариты'
            };
            return names[key] || key;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            var date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            return day + '.' + month + '.' + year;
        }
        
        function getDocumentIcon(type) {
            var icons = {
                'passport': '📄',
                'certificate': '📜',
                'manual': '📘',
                'warranty': '🛡️',
                'test_report': '📊',
                'other': '📁'
            };
            return icons[type] || '📁';
        }
        
        function closeProductModal(event) {
            if (event.target === document.getElementById('productModalOverlay')) {
                closeProductModalDirect();
            }
        }
        
        function closeProductModalDirect() {
            document.getElementById('productModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function printProductPassport() {
            // Получаем данные текущего продукта из модального окна
            var productName = document.getElementById('modalProductName').textContent;
            var productArticle = document.getElementById('modalProductArticle').textContent;
            
            // Извлекаем ID продукта из данных, сохранённых в модальном окне
            if (!window.currentProduct) {
                alert('Ошибка: нет данных о продукте. Пожалуйста, откройте продукт заново.');
                return;
            }
            
            var productId = window.currentProduct.id;
            if (!productId) {
                alert('Ошибка: нет ID продукта');
                return;
            }
            
            // Получаем серийный номер из модального окна
            var serialElement = document.querySelector('.serial-number-display');
            var serialNumber = serialElement ? serialElement.textContent.trim() : '';
            
            // Если серийный номер пустой, генерируем его
            if (!serialNumber || serialNumber === 'Не присвоен') {
                var date = new Date();
                var year = date.getFullYear().toString().substr(-2);
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                serialNumber = 'SN-' + year + month + '-' + productId + '-' + randomPart;
                
                // Сохраняем новый серийный номер через AJAX
                saveProductSerialNumber(productId, serialNumber);
                
                // Обновляем отображение
                if (serialElement) {
                    serialElement.textContent = serialNumber;
                }
            }
            
            // Открываем страницу для печати в новом окне
            var printUrl = '<?= asset('modules/warehouse/print_passport.php') ?>?id=' + productId + '&serial=' + encodeURIComponent(serialNumber);
            var printWindow = window.open(printUrl, '_blank');
            
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(function() {
                        printWindow.print();
                    }, 500);
                };
            } else {
                alert('Пожалуйста, разрешите открытие всплывающих окон для печати паспорта');
            }
        }
        
        function saveProductSerialNumber(productId, serialNumber) {
            fetch('<?= asset('modules/warehouse/api_save_serial.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    serial_number: serialNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Серийный номер сохранён:', serialNumber);
                    // Обновляем данные текущего продукта после сохранения
                    if (window.currentProduct) {
                        window.currentProduct.serial_number = serialNumber;
                    }
                } else {
                    console.error('Ошибка сохранения серийного номера:', data.error);
                }
            })
            .catch(error => console.error('Ошибка сохранения серийного номера:', error));
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
                var modal = document.getElementById('productModalOverlay');
                if (modal.classList.contains('active')) {
                    closeProductModalDirect();
                }
            }
        });
    </script>
</body>
</html>
