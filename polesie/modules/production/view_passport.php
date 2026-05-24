<?php
/**
 * Просмотр и печать паспорта продукта по серийному номеру
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Паспорт продукта';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('Не указан ID серийного номера');
}

// Получение данных о серийном номере
$stmt = $pdo->prepare("
    SELECT sn.*, p.name as product_name, p.article as product_article, 
           p.description as product_description, p.specifications as product_specifications,
           c.name as category_name, u.name as unit_name,
           po.production_number
    FROM product_serial_numbers sn
    JOIN products p ON sn.product_id = p.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN production_orders po ON sn.production_order_id = po.id
    WHERE sn.id = ?
");
$stmt->execute([$id]);
$serialData = $stmt->fetch();

if (!$serialData) {
    die('Серийный номер не найден');
}

// Получение документов
$docsStmt = $pdo->prepare("SELECT * FROM product_documents WHERE serial_number_id = ? ORDER BY uploaded_at DESC");
$docsStmt->execute([$id]);
$documents = $docsStmt->fetchAll();

// Получение версий паспорта
$versionsStmt = $pdo->prepare("SELECT * FROM product_passport_versions WHERE serial_number_id = ? ORDER BY version_number DESC");
$versionsStmt->execute([$id]);
$versions = $versionsStmt->fetchAll();

// Получение динамических данных паспорта
$dynamicPassportData = null;
$dynStmt = $pdo->prepare("SELECT * FROM passport_dynamic_data WHERE serial_number_id = ?");
$dynStmt->execute([$id]);
$dynamicPassportData = $dynStmt->fetch();

// Декодирование JSON
$technicalSpecs = !empty($serialData['technical_specs']) ? json_decode($serialData['technical_specs'], true) : [];
$productSpecs = !empty($serialData['product_specifications']) ? json_decode($serialData['product_specifications'], true) : [];
$passportData = !empty($serialData['passport_data']) ? json_decode($serialData['passport_data'], true) : [];

// Если есть динамические данные, используем их
if ($dynamicPassportData) {
    // Переопределяем данные если заданы в динамическом паспорте
    if (!empty($dynamicPassportData['warranty_start'])) {
        $serialData['warranty_start'] = $dynamicPassportData['warranty_start'];
    }
    if (!empty($dynamicPassportData['warranty_end'])) {
        $serialData['warranty_end'] = $dynamicPassportData['warranty_end'];
    }
    if (!empty($dynamicPassportData['warranty_months'])) {
        $serialData['warranty_months'] = $dynamicPassportData['warranty_months'];
    }
    if (!empty($dynamicPassportData['manufacture_date'])) {
        $serialData['manufacture_date'] = $dynamicPassportData['manufacture_date'];
    }
}

// Режим печати
$isPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($serialData['serial_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <?php if (!$isPrint): ?>
    <style>
        .passport-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 24px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        .passport-number {
            font-family: 'Courier New', monospace;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .passport-section {
            background: white;
            padding: 16px;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
        }
        .passport-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        .specs-list {
            display: flex;
            flex-direction: column;
        }
        .spec-row {
            display: flex;
            align-items: baseline;
            padding: 8px 0;
            border-bottom: 1px solid #e5e5e5;
            gap: 16px;
        }
        .spec-row:last-child {
            border-bottom: none;
        }
        .spec-label {
            font-size: 13px;
            color: #666;
            min-width: 220px;
            font-weight: 500;
            flex-shrink: 0;
        }
        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        .document-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .document-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e5e5;
            gap: 12px;
        }
        .document-row:last-child {
            border-bottom: none;
        }
        .document-info {
            flex: 1;
        }
        .document-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        .document-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .document-actions {
            display: flex;
            gap: 6px;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-icon:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        .btn-print {
            background: var(--success-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit-passport {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .actions-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            #printableArea, #printableArea * {
                visibility: visible;
            }
            #printableArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .passport-header {
                background: white;
                color: black;
                border: 2px solid black;
                padding: 20px;
            }
            .passport-section {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 12px;
            }
            .passport-section-title {
                border-bottom: 1px solid #333;
            }
            .spec-row {
                background: white;
                border-bottom: 1px solid #eee;
                padding: 6px 0;
            }
            .spec-row:last-child {
                border-bottom: none;
            }
            .spec-label {
                color: #333;
                font-weight: 600;
            }
            .document-row {
                background: white;
                border-bottom: 1px solid #eee;
            }
            .document-row:last-child {
                border-bottom: none;
            }
            .document-name {
                color: #000;
            }
            .btn-icon {
                display: none;
            }
            .actions-bar {
                display: none;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if (!$isPrint): ?>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content">
    <?php endif; ?>
    
    <div id="printableArea">
        <?php if (!$isPrint): ?>
        <div class="page-header">
            <div class="page-header-title">
                <h2>📄 Паспорт продукта</h2>
                <p>Серийный номер: <?= e($serialData['serial_number']) ?></p>
            </div>
            <div class="page-header-actions">
                <div class="actions-bar">
                    <button class="btn-print" onclick="openPrintPreviewModal()">🖨️ Печать</button>
                    <?php if (hasPermission('production.edit')): ?>
                        <button class="btn-edit-passport" onclick="openEditPassportModal()">✏️ Редактировать паспорт</button>
                    <?php endif; ?>
                    <a href="serial_numbers.php" class="btn btn-outline">← Назад</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Шапка паспорта -->
        <div class="passport-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 14px; opacity: 0.9;">ПАСПОРТ ПРОДУКТА</div>
                    <div class="passport-number" style="margin-top: 8px;"><?= e($serialData['serial_number']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 20px; font-weight: 700;"><?= e($serialData['product_name']) ?></div>
                    <div style="opacity: 0.9; margin-top: 4px;">Артикул: <?= e($serialData['product_article']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Основная информация -->
        <div class="passport-section">
            <div class="passport-section-title">📋 Основная информация</div>
            <div class="specs-list">
                <div class="spec-row">
                    <div class="spec-label">Категория</div>
                    <div class="spec-value"><?= e($serialData['category_name'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Ед. измерения</div>
                    <div class="spec-value"><?= e($serialData['unit_name'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Дата выпуска</div>
                    <div class="spec-value"><?= date('d.m.Y', strtotime($serialData['manufacture_date'])) ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Производство</div>
                    <div class="spec-value"><?= e($serialData['production_number'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Статус</div>
                    <div class="spec-value"><?= e($serialData['status']) ?></div>
                </div>
                <?php if ($serialData['warranty_end']): ?>
                <div class="spec-row">
                    <div class="spec-label">Гарантия до</div>
                    <div class="spec-value"><?= date('d.m.Y', strtotime($serialData['warranty_end'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Технические характеристики продукта -->
        <?php if (!empty($productSpecs)): ?>
        <div class="passport-section">
            <div class="passport-section-title">⚙️ Технические характеристики</div>
            <div class="specs-list">
                <?php 
                $specLabels = [
                    'explosion_protection' => 'Вид взрывозащиты',
                    'housing_material' => 'Материал корпуса',
                    'frame_material' => 'Материал корпуса',
                    'shaft_material' => 'Материал вала',
                    'winding_material' => 'Материал обмотки',
                    'power_kw' => 'Мощность',
                    'rpm' => 'Частота вращения',
                    'voltage_v' => 'Напряжение питания',
                    'frequency_hz' => 'Частота тока',
                    'protection_class' => 'Степень защиты',
                    'efficiency_class' => 'Класс энергоэффективности',
                    'climate_versions' => 'Климатическое исполнение',
                    'mounting_versions' => 'Исполнение по монтажу',
                    'weight_kg' => 'Масса',
                    'ip_rating' => 'Степень защиты IP'
                ];
                foreach ($productSpecs as $key => $value): 
                    $label = $specLabels[$key] ?? ucfirst($key);
                ?>
                <div class="spec-row">
                    <div class="spec-label"><?= e($label) ?></div>
                    <div class="spec-value"><?= e(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Индивидуальные технические характеристики -->
        <?php if (!empty($technicalSpecs)): ?>
        <div class="passport-section">
            <div class="passport-section-title">🔧 Индивидуальные параметры</div>
            <div class="specs-list">
                <?php 
                $techSpecLabels = [
                    'explosion_protection' => 'Вид взрывозащиты',
                    'housing_material' => 'Материал корпуса',
                    'frame_material' => 'Материал корпуса',
                    'shaft_material' => 'Материал вала',
                    'winding_material' => 'Материал обмотки',
                    'power_kw' => 'Мощность',
                    'rpm' => 'Частота вращения',
                    'voltage_v' => 'Напряжение питания',
                    'frequency_hz' => 'Частота тока',
                    'protection_class' => 'Степень защиты',
                    'efficiency_class' => 'Класс энергоэффективности',
                    'climate_versions' => 'Климатическое исполнение',
                    'mounting_versions' => 'Исполнение по монтажу',
                    'weight_kg' => 'Масса',
                    'ip_rating' => 'Степень защиты IP'
                ];
                foreach ($technicalSpecs as $key => $value): 
                    $label = $techSpecLabels[$key] ?? ucfirst($key);
                ?>
                <div class="spec-row">
                    <div class="spec-label"><?= e($label) ?></div>
                    <div class="spec-value"><?= e(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Описание -->
        <?php if (!empty($serialData['product_description'])): ?>
        <div class="passport-section">
            <div class="passport-section-title">📝 Описание</div>
            <div style="line-height: 1.6;"><?= nl2br(e($serialData['product_description'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Документы -->
        <div class="passport-section">
            <div class="passport-section-title">📎 Прикреплённые документы</div>
            <?php if (empty($documents)): ?>
                <p style="color: var(--text-secondary);">Документы не прикреплены</p>
            <?php else: ?>
                <ul class="document-list">
                    <?php foreach ($documents as $doc): ?>
                    <?php
                    $typeLabels = [
                        'manual' => '📘 Руководство',
                        'certificate' => '📜 Сертификат',
                        'test_report' => '📊 Отчёт о тестировании',
                        'warranty_card' => '🛡️ Гарантийный талон',
                        'other' => '📄 Другое'
                    ];
                    ?>
                    <li class="document-row">
                        <div class="document-info">
                            <div class="document-name"><?= $typeLabels[$doc['document_type']] ?? '📄 Документ' ?></div>
                            <div class="document-meta">
                                <?= e($doc['file_name']) ?> 
                                <?php if ($doc['file_size']): ?>
                                    (<?= round($doc['file_size'] / 1024, 1) ?> KB)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="document-actions">
                            <a href="<?= e($doc['file_path']) ?>" download class="btn-icon" title="Скачать">⬇️</a>
                            <?php if ($doc['mime_type'] && strpos($doc['mime_type'], 'pdf') !== false): ?>
                                <a href="<?= e($doc['file_path']) ?>" target="_blank" class="btn-icon" title="Открыть">👁️</a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <!-- Примечания -->
        <?php if (!empty($serialData['notes'])): ?>
        <div class="passport-section">
            <div class="passport-section-title">📌 Примечания</div>
            <div style="line-height: 1.6;"><?= nl2br(e($serialData['notes'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Подвал -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--border-color); display: flex; justify-content: space-between;">
            <div>
                <div style="font-size: 12px; color: var(--text-secondary);">Создан</div>
                <div style="font-size: 14px;"><?= date('d.m.Y H:i', strtotime($serialData['created_at'])) ?></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 12px; color: var(--text-secondary);">Последнее обновление</div>
                <div style="font-size: 14px;"><?= date('d.m.Y H:i', strtotime($serialData['updated_at'])) ?></div>
            </div>
        </div>
    </div>
    
    <?php if (!$isPrint): ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно предпросмотра печати с редактированием -->
    <div id="printPreviewModal" class="product-modal-overlay" onclick="closePrintPreviewModal(event)" style="display: none;">
        <div class="product-modal" style="max-width: 900px;" onclick="event.stopPropagation()">
            <div class="product-modal-header">
                <h3 class="product-modal-title">🖨️ Предпросмотр паспорта - <?= e($serialData['serial_number']) ?></h3>
                <button class="product-modal-close" onclick="closePrintPreviewModalDirect()">×</button>
            </div>
            <div class="product-modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form method="POST" action="api_passport.php" id="printPreviewForm">
                    <input type="hidden" name="action" value="update_passport">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <div style="background: #f8f9fa; padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px;">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 14px;">
                            ℹ️ Отредактируйте данные при необходимости, затем нажмите "Печать" для открытия готового документа.
                        </p>
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color);">📅 Даты и гарантия</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Дата изготовления</label>
                            <input type="date" name="manufacture_date" 
                                   value="<?= e($dynamicPassportData['manufacture_date'] ?? $serialData['manufacture_date']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Гарантийный срок (мес.)</label>
                            <input type="number" name="warranty_months" 
                                   value="<?= e($dynamicPassportData['warranty_months'] ?? $serialData['warranty_months'] ?? 12) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Начало гарантии</label>
                            <input type="date" name="warranty_start" 
                                   value="<?= e($dynamicPassportData['warranty_start'] ?? $serialData['warranty_start']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Окончание гарантии</label>
                            <input type="date" name="warranty_end" 
                                   value="<?= e($dynamicPassportData['warranty_end'] ?? $serialData['warranty_end']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <?php
                    // Значения по умолчанию для организации (для printPreviewModal)
                    $printDefaultCompanyName = 'ОАО «Полесьеэлектромаш»';
                    $printDefaultCompanyAddress = '225644, Брестская область, г. Лунинец, ул. Красная, 179';
                    $printDefaultCompanyPhone = '+375 1647 2-78-09';
                    $printDefaultCompanyEmail = 'polesie@polesieelectromash.by';
                    ?>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">🏢 Данные организации</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Название организации</label>
                        <input type="text" name="company_name" 
                               value="<?= e($dynamicPassportData['company_name'] ?? $printDefaultCompanyName) ?>"
                               placeholder="ОАО «Полесьеэлектромаш»"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Адрес</label>
                        <input type="text" name="company_address" 
                               value="<?= e($dynamicPassportData['company_address'] ?? $printDefaultCompanyAddress) ?>"
                               placeholder="225644, Брестская область, г. Лунинец, ул. Красная, 179"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Телефон</label>
                            <input type="text" name="company_phone" 
                                   value="<?= e($dynamicPassportData['company_phone'] ?? $printDefaultCompanyPhone) ?>"
                                   placeholder="+375 1647 2-78-09"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">E-mail</label>
                            <input type="email" name="company_email" 
                                   value="<?= e($dynamicPassportData['company_email'] ?? $printDefaultCompanyEmail) ?>"
                                   placeholder="polesie@polesieelectromash.by"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">📦 Информация об изделии</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Наименование изделия (кастомное)</label>
                        <input type="text" name="product_name_custom" 
                               value="<?= e($dynamicPassportData['product_name_custom'] ?? '') ?>"
                               placeholder="Оставьте пустым для использования стандартного названия"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closePrintPreviewModalDirect()" class="btn btn-outline">Отмена</button>
                        <button type="button" class="btn btn-primary" id="saveAndPrintBtn">💾 Сохранить и печатать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно редактирования паспорта -->
    <div id="editPassportModal" class="product-modal-overlay" onclick="closeEditPassportModal(event)" style="display: none;">
        <div class="product-modal" onclick="event.stopPropagation()">
            <div class="product-modal-header">
                <h3 class="product-modal-title">Редактировать паспорт</h3>
                <button class="product-modal-close" onclick="closeEditPassportModalDirect()">×</button>
            </div>
            <div class="product-modal-body">
                <form method="POST" action="api_passport.php">
                    <input type="hidden" name="action" value="update_passport">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color);">📅 Даты и гарантия</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Дата изготовления</label>
                            <input type="date" name="manufacture_date" 
                                   value="<?= e($dynamicPassportData['manufacture_date'] ?? $serialData['manufacture_date']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Гарантийный срок (мес.)</label>
                            <input type="number" name="warranty_months" 
                                   value="<?= e($dynamicPassportData['warranty_months'] ?? $serialData['warranty_months'] ?? 12) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Начало гарантии</label>
                            <input type="date" name="warranty_start" 
                                   value="<?= e($dynamicPassportData['warranty_start'] ?? $serialData['warranty_start']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Окончание гарантии</label>
                            <input type="date" name="warranty_end" 
                                   value="<?= e($dynamicPassportData['warranty_end'] ?? $serialData['warranty_end']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <?php
                    // Значения по умолчанию для организации (для editPassportModal)
                    $editDefaultCompanyName = 'ОАО «Полесьеэлектромаш»';
                    $editDefaultCompanyAddress = '225644, Брестская область, г. Лунинец, ул. Красная, 179';
                    $editDefaultCompanyPhone = '+375 1647 2-78-09';
                    $editDefaultCompanyEmail = 'polesie@polesieelectromash.by';
                    ?>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">🏢 Данные организации</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Название организации</label>
                        <input type="text" name="company_name" 
                               value="<?= e($dynamicPassportData['company_name'] ?? $editDefaultCompanyName) ?>"
                               placeholder="ОАО «Полесьеэлектромаш»"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Адрес</label>
                        <input type="text" name="company_address" 
                               value="<?= e($dynamicPassportData['company_address'] ?? $editDefaultCompanyAddress) ?>"
                               placeholder="225644, Брестская область, г. Лунинец, ул. Красная, 179"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Телефон</label>
                            <input type="text" name="company_phone" 
                                   value="<?= e($dynamicPassportData['company_phone'] ?? $editDefaultCompanyPhone) ?>"
                                   placeholder="+375 1647 2-78-09"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">E-mail</label>
                            <input type="email" name="company_email" 
                                   value="<?= e($dynamicPassportData['company_email'] ?? $editDefaultCompanyEmail) ?>"
                                   placeholder="polesie@polesieelectromash.by"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">📦 Информация об изделии</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Наименование изделия (кастомное)</label>
                        <input type="text" name="product_name_custom" 
                               value="<?= e($dynamicPassportData['product_name_custom'] ?? '') ?>"
                               placeholder="Оставьте пустым для использования стандартного названия"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">⚙️ Технические характеристики</h4>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Технические характеристики (JSON)</label>
                        <textarea name="technical_specs" rows="6" 
                                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-family: monospace; font-size: 12px;"
                        ><?= e(json_encode($technicalSpecs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Примечания</label>
                        <textarea name="notes" rows="3" 
                                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);"><?= e($serialData['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="closeEditPassportModalDirect()" class="btn btn-outline">Отмена</button>
                        <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
                    </div>
                </form>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border-color);">
                
                <h4 style="margin-bottom: 12px;">Загрузить документ</h4>
                <form method="POST" action="api_passport.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Тип документа</label>
                        <select name="document_type" required 
                                style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            <option value="manual">Руководство по эксплуатации</option>
                            <option value="certificate">Сертификат</option>
                            <option value="test_report">Отчёт о тестировании</option>
                            <option value="warranty_card">Гарантийный талон</option>
                            <option value="other">Другое</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Файл</label>
                        <input type="file" name="document_file" required 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Загрузить</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openEditPassportModal() {
            const modal = document.getElementById('editPassportModal');
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        
        function closeEditPassportModal(event) {
            if (event.target === event.currentTarget) {
                const modal = document.getElementById('editPassportModal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function closeEditPassportModalDirect() {
            const modal = document.getElementById('editPassportModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Функции для модального окна предпросмотра печати
        function openPrintPreviewModal() {
            const modal = document.getElementById('printPreviewModal');
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        
        function closePrintPreviewModal(event) {
            if (event.target === event.currentTarget) {
                const modal = document.getElementById('printPreviewModal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function closePrintPreviewModalDirect() {
            const modal = document.getElementById('printPreviewModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Обработка кнопки "Сохранить и печатать"
        document.addEventListener('DOMContentLoaded', function() {
            const saveAndPrintBtn = document.getElementById('saveAndPrintBtn');
            if (saveAndPrintBtn) {
                saveAndPrintBtn.addEventListener('click', function() {
                    const printForm = document.getElementById('printPreviewForm');
                    if (!printForm) return;
                    
                    const formData = new FormData(printForm);
                    const serialId = formData.get('serial_id');
                    
                    // Сохраняем данные через AJAX
                    fetch('api_passport.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Закрываем модальное окно
                            closePrintPreviewModalDirect();
                            // Открываем печать в новой вкладке
                            window.open('?id=' + serialId + '&print=1', '_blank');
                        } else {
                            alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка: ' + error.message);
                    });
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
