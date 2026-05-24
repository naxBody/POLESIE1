<?php
/**
 * Справочник документов и расшифровка аббревиатур материалов
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

// Устанавливаем кодировку для корректной работы с многобайтовыми строками (кириллица)
mb_internal_encoding('UTF-8');

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$pageTitle = 'Документы и справочники';

// Получение данных из JSON
$docsPath = BASE_PATH . '/list_materials_docs.json';
$docsData = [];

if (file_exists($docsPath)) {
    $jsonData = file_get_contents($docsPath);
    $docsData = json_decode($jsonData, true);
}

$abbreviations = $docsData['abbreviation_decodings'] ?? [];
$gostStandards = $docsData['gost_standards'] ?? [];

// Фильтруем зарезервированные категории (начинаются с _CATEGORY_)
$gostStandards = array_filter($gostStandards, function($gost) {
    return !isset($gost['gost_number']) || strpos($gost['gost_number'], '_CATEGORY_') !== 0;
});
$gostStandards = array_values($gostStandards); // Переиндексируем массив

$allAbbreviations = $materialsData['all_abbreviations'] ?? [];

// Загружаем полную структуру кодов из list_materials.json
$materialsPath = BASE_PATH . '/list_materials.json';
$codeStructures = [];
if (file_exists($materialsPath)) {
    $materialsData = json_decode(file_get_contents($materialsPath), true);
    $categories = $materialsData['categories'] ?? [];
    
    foreach ($categories as $category) {
        if (isset($category['subcategories'])) {
            foreach ($category['subcategories'] as $subcategory) {
                $combos = $subcategory['available_combinations'] ?? [];
                if (isset($combos['_code_format']) && isset($combos['_code_format_ru'])) {
                    $codeStructures[$subcategory['id']] = [
                        'category_ru' => $category['name_ru'],
                        'subcategory_ru' => $subcategory['name_ru'],
                        'pattern' => $combos['_code_format_ru'],
                        'description_ru' => $combos['_code_format_ru'],
                        'examples' => $combos['examples'] ?? [],
                        'example_detailed_decoding' => $combos['example_detailed_decoding'] ?? []
                    ];
                }
            }
        }
    }
}

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
    .docs-page {
        padding: 24px;
    }
    
    .docs-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 0;
    }
    
    .doc-tab {
        padding: 12px 24px;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        transition: all var(--transition-fast);
    }
    
    .doc-tab:hover {
        color: var(--primary-color);
    }
    
    .doc-tab.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }
    
    .doc-section {
        display: none;
    }
    
    .doc-section.active {
        display: block;
    }
    
    .standards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 16px;
    }
    
    .standard-card-wrapper {
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .standard-card-link {
        display: block;
        flex-grow: 1;
    }
    
    .standard-card {
        background: var(--bg-primary);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow);
        border-left: 4px solid var(--primary-color);
        height: 100%;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }
        .standard-card-link:hover .standard-card {
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }
    
    .standard-card-footer {
        margin-top: auto;
        padding-top: 12px;
        display: flex;
        justify-content: flex-end;
    }
    
    .standard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .standard-number {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary-color);
        font-family: 'Courier New', monospace;
    }
    
    .standard-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
    }
    
    .standard-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
        line-height: 1.4;
    }
    
    .standard-category {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .abbreviations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
    }
    
    .abbreviation-card {
        background: var(--bg-primary);
        border-radius: var(--border-radius-lg);
        padding: 16px;
        box-shadow: var(--shadow);
    }
    
    .abbr-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }
    
    .abbr-code {
        font-size: 20px;
        font-weight: 700;
        color: var(--primary-color);
        font-family: 'Courier New', monospace;
        background: rgba(37, 99, 235, 0.1);
        padding: 8px 12px;
        border-radius: 8px;
    }
    
    .abbr-full-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .abbr-description {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 8px;
    }
    
    .abbr-category {
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .code-structure-section {
        margin-bottom: 24px;
    }
    
    .code-structure-section h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .code-structure-card {
        background: var(--bg-primary);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow);
        margin-bottom: 16px;
        transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    }
    
    .code-structure-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .code-pattern {
        font-family: 'Courier New', monospace;
        font-size: 14px;
        background: var(--gray-100);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 12px;
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .code-description {
        color: var(--text-secondary);
        line-height: 1.6;
    }
    
    .code-example {
        font-family: 'Courier New', monospace;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        background: rgba(37, 99, 235, 0.1);
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 8px;
    }
    
    .example-decoding {
        font-size: 12px;
        color: var(--text-secondary);
        padding-left: 8px;
        margin-top: 4px;
        line-height: 1.4;
    }
    
    .examples-section {
        margin-top: 16px;
        border-top: 1px solid var(--border-color);
        padding-top: 16px;
    }
    
    .examples-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
    }
    
    .explanation-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .explanation-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        font-size: 13px;
    }
    
    .explanation-table td:first-child {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: var(--primary-color);
        width: 150px;
    }
    
    .search-box {
        margin-bottom: 24px;
    }
    
    .search-input {
        width: 100%;
        max-width: 500px;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        font-size: 14px;
        background: var(--bg-primary);
        color: var(--text-primary);
        transition: all var(--transition-fast);
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .no-results-message {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
        font-size: 14px;
    }
    
    /* Стили модального окна загрузки */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-window {
        background: var(--bg-primary);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        width: 100%;
        max-width: 420px;
        box-shadow: var(--shadow-xl);
        position: relative;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: color var(--transition-fast);
    }
    
    .modal-close:hover {
        color: var(--text-primary);
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 6px;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        font-size: 13px;
        background: var(--bg-primary);
        color: var(--text-primary);
        transition: all var(--transition-fast);
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .form-input::file-selector-button {
        padding: 8px 14px;
        border-radius: var(--border-radius-lg);
        border: none;
        background: var(--primary-color);
        color: white;
        cursor: pointer;
        margin-right: 12px;
        transition: background var(--transition-fast);
        font-weight: 500;
        font-size: 12px;
    }
    
    .form-input::file-selector-button:hover {
        background: var(--primary-hover);
    }
    
    /* Кастомный стиль для области загрузки файла */
    .file-upload-wrapper {
        position: relative;
        border: 2px dashed var(--border-color);
        border-radius: var(--border-radius-lg);
        padding: 16px;
        text-align: center;
        transition: all var(--transition-fast);
        background: var(--bg-primary);
    }
    
    .file-upload-wrapper:hover {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }
    
    .file-upload-wrapper.has-file {
        border-color: #22c55e;
        background: rgba(34, 197, 94, 0.05);
    }
    
    .file-upload-icon {
        font-size: 36px;
        margin-bottom: 8px;
        color: var(--text-secondary);
    }
    
    .file-upload-text {
        font-size: 12px;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }
    
    .file-upload-hint {
        font-size: 11px;
        color: var(--text-muted);
    }
    
    .file-name-display {
        font-size: 12px;
        color: var(--primary-color);
        font-weight: 500;
        margin-top: 6px;
        word-break: break-all;
    }
    
    .upload-status {
        padding: 10px 14px;
        border-radius: var(--border-radius-lg);
        margin-top: 14px;
        font-size: 13px;
        display: none;
    }
    
    .upload-status.success {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .upload-status.error {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: var(--border-radius-lg);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-fast);
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-hover);
    }
    
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .btn-secondary {
        background: var(--gray-200);
        color: var(--text-primary);
    }
    
    .btn-secondary:hover {
        background: var(--gray-300);
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }
    
    .help-text {
        font-size: 11px;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="docs-page">
                    <h1 style="margin-bottom: 24px;">📚 Документы и справочники материалов</h1>
                    
                    <!-- Вкладки -->
                    <div class="docs-tabs">
                        <button class="doc-tab active" onclick="switchTab('gost')">📋 ГОСТы и стандарты</button>
                        <button class="doc-tab" onclick="switchTab('abbreviations')">🔤 Расшифровка аббревиатур</button>
                        <button class="doc-tab" onclick="switchTab('structures')">📝 Структура кодов материалов</button>
                    </div>
                    
                    <!-- Секция: ГОСТы -->
                    <div id="gost-section" class="doc-section active">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                            <div class="search-box" style="margin-bottom: 0;">
                                <input type="text" class="search-input" placeholder="🔍 Поиск ГОСТа..." onkeyup="filterStandards(this.value)">
                            </div>
                            <button class="btn btn-primary" onclick="openUploadModal()" style="padding: 12px 24px; border-radius: var(--border-radius-lg); font-weight: 500;">
                                📤 Загрузить ГОСТ
                            </button>
                        </div>
                        
                        <div class="standards-grid" id="standardsGrid">
                            <?php foreach ($gostStandards as $index => $gost): ?>
                            <?php 
                                // Генерируем ссылку на ГОСТ (локальный файл)
                                // Приоритет: если есть file_name в JSON, используем его, иначе генерируем из номера
                                if (isset($gost['file_name']) && !empty($gost['file_name'])) {
                                    $gostFileName = $gost['file_name'];
                                } else {
                                    $gostNumberFull = preg_replace('/ГОСТ\s*([0-9.]+(?:-[0-9]+)?).*/i', '$1', $gost['gost_number']);
                                    $gostFileName = 'gost_' . str_replace('.', '-', $gostNumberFull) . '.pdf';
                                }
                                $gostLink = asset('assets/gosts/' . $gostFileName);
                            ?>
                            <div class="standard-card-wrapper" style="position: relative;">
                                <a href="<?= $gostLink ?>" target="_blank" class="standard-card-link" style="text-decoration: none; color: inherit; display: block;">
                                    <div class="standard-card" 
                                         data-gost="<?= e(mb_strtolower($gost['gost_number'], 'UTF-8')) ?>" 
                                         data-title="<?= e(mb_strtolower($gost['title'], 'UTF-8')) ?>"
                                         data-category="<?= e(mb_strtolower($gost['category'], 'UTF-8')) ?>">
                                        <div class="standard-header">
                                            <span class="standard-number"><?= e($gost['gost_number']) ?></span>
                                            <span class="standard-status status-active"><?= e($gost['status']) ?></span>
                                        </div>
                                        <div class="standard-title"><?= e($gost['title']) ?></div>
                                        <div class="standard-category">📁 <?= e($gost['category']) ?></div>
                                        <div class="standard-card-footer"></div>
                                    </div>
                                </a>
                                <button class="btn-icon edit-gost-btn" 
                                        onclick="openEditModal(<?= $index ?>);" 
                                        title="Редактировать"
                                        style="position: absolute; bottom: 12px; right: 12px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px; padding: 6px 10px; cursor: pointer; transition: all var(--transition-fast); z-index: 10;"
                                        onmouseover="this.style.background='var(--primary-color)'; this.style.color='white'"
                                        onmouseout="this.style.background='var(--bg-primary)'; this.style.color='var(--text-primary)'">
                                    ✏️
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Секция: Аббревиатуры -->
                    <div id="abbreviations-section" class="doc-section">
                        <div class="search-box">
                            <input type="text" class="search-input" placeholder="🔍 Поиск аббревиатуры..." onkeyup="filterAbbreviations(this.value)">
                        </div>
                        
                        <div class="abbreviations-grid" id="abbreviationsGrid">
                            <?php foreach ($abbreviations as $code => $info): ?>
                            <div class="abbreviation-card" data-code="<?= e(mb_strtolower($code, 'UTF-8')) ?>" data-name="<?= e(mb_strtolower($info['full_name'], 'UTF-8')) ?>">
                                <div class="abbr-header">
                                    <span class="abbr-code"><?= e($code) ?></span>
                                    <span class="abbr-full-name"><?= e($info['full_name']) ?></span>
                                </div>
                                <div class="abbr-description"><?= e($info['description']) ?></div>
                                <div class="abbr-category">📁 <?= e($info['category']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Секция: Структура кодов -->
                    <div id="structures-section" class="doc-section">
                        <div class="search-box">
                            <input type="text" class="search-input" placeholder="🔍 Поиск по структуре кода..." onkeyup="filterStructures(this.value)">
                        </div>
                        
                        <!-- Справочник всех аббревиатур -->
                        <?php if (!empty($allAbbreviations)): ?>
                        <div style="margin-bottom: 32px; background: var(--bg-primary); border-radius: var(--border-radius-lg); padding: 20px; box-shadow: var(--shadow);">
                            <h3 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">
                                📖 Полный справочник аббревиатур
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                                <?php foreach ($allAbbreviations as $abbrCode => $abbrInfo): ?>
                                <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: var(--gray-50); border-radius: 8px; border: 1px solid var(--border-color);">
                                    <span style="font-family: 'Courier New', monospace; font-weight: 700; color: var(--primary-color); font-size: 14px; min-width: 70px;"><?= e($abbrCode) ?></span>
                                    <span style="font-size: 13px; color: var(--text-secondary);"><?= e($abbrInfo['desc']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div id="structuresGrid">
                        <?php foreach ($codeStructures as $id => $structure): ?>
                        <div class="code-structure-section structure-item" 
                             data-category="<?= e(mb_strtolower($structure['category_ru'], 'UTF-8')) ?>" 
                             data-subcategory="<?= e(mb_strtolower($structure['subcategory_ru'], 'UTF-8')) ?>"
                             data-pattern="<?= e(mb_strtolower($structure['pattern'], 'UTF-8')) ?>"
                             data-description="<?= e(mb_strtolower($structure['description_ru'], 'UTF-8')) ?>"
                             data-examples="<?= e(mb_strtolower(implode(' ', $structure['examples'] ?? []), 'UTF-8')) ?>">
                            <h3 style="margin-bottom: 16px;">
                                <?= e($structure['category_ru']) ?> → <?= e($structure['subcategory_ru']) ?>
                            </h3>
                            <div class="code-structure-card">
                                <div class="code-pattern">
                                    📐 Шаблон: <?= e($structure['pattern']) ?>
                                </div>
                                
                                <?php if (!empty($structure['examples'])): ?>
                                <div class="examples-section" style="margin-top: 16px; border-top: none; padding-top: 0;">
                                    <?php foreach ($structure['examples'] as $example): ?>
                                    <div style="margin-bottom: 12px;">
                                        <div class="code-example" style="font-size: 16px; margin-bottom: 12px;">
                                            🔹 <?= e($example) ?>
                                        </div>
                                        <?php if (!empty($structure['example_detailed_decoding'])): ?>
                                        <table class="explanation-table" style="width: 100%; margin-left: 12px;">
                                            <?php foreach ($structure['example_detailed_decoding'] as $item): ?>
                                            <tr>
                                                <td><?= e($item['code']) ?></td>
                                                <td><?= e($item['desc']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно загрузки ГОСТ -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">📤 Загрузка ГОСТа</h3>
                <button class="modal-close" onclick="closeUploadModal()">&times;</button>
            </div>
            
            <form id="uploadForm" onsubmit="submitGostUpload(event)" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="gost_number">Номер ГОСТ *</label>
                    <input type="text" class="form-input" id="gost_number" name="gost_number" placeholder="ГОСТ 7798-70" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="title">Название *</label>
                    <input type="text" class="form-input" id="title" name="title" placeholder="Болты с шестигранной головкой" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="category">Категория *</label>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <select class="form-select" id="category" name="category" required style="flex: 1;">
                            <option value="">Выберите категорию</option>
                            <option value="Крепёжные изделия">Крепёжные изделия</option>
                            <option value="Прокат">Прокат</option>
                            <option value="Трубы">Трубы</option>
                            <option value="Листовой металл">Листовой металл</option>
                            <option value="Электротехнические материалы">Электротехнические материалы</option>
                            <option value="Изоляционные материалы">Изоляционные материалы</option>
                            <option value="Лакокрасочные материалы">Лакокрасочные материалы</option>
                            <option value="Металлопрокат">Металлопрокат</option>
                            <option value="Металлы">Металлы</option>
                            <option value="Подшипники">Подшипники</option>
                            <option value="Сварочные материалы">Сварочные материалы</option>
                            <option value="Масла и смазки">Масла и смазки</option>
                            <option value="Инструмент">Инструмент</option>
                            <option value="Другое">Другое</option>
                        </select>
                        <button type="button" class="btn btn-secondary" onclick="addNewCategory()" style="padding: 10px 14px; border-radius: var(--border-radius-lg);" title="Добавить новую категорию">
                            ➕
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="deleteCategory()" style="padding: 10px 14px; border-radius: var(--border-radius-lg);" title="Удалить выбранную категорию">
                            🗑️
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="status">Статус</label>
                    <select class="form-select" id="status" name="status">
                        <option value="Действующий">Действующий</option>
                        <option value="Заменён">Заменён</option>
                        <option value="Отменён">Отменён</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="gost_file">Файл PDF *</label>
                    <div class="file-upload-wrapper" id="fileUploadWrapper" onclick="document.getElementById('gost_file').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Перетащите файл или кликните</div>
                        <div class="file-upload-hint">PDF, макс. 50 MB</div>
                        <div class="file-name-display" id="fileNameDisplay"></div>
                        <input type="file" id="gost_file" name="gost_file" accept=".pdf,application/pdf" required style="display: none;">
                    </div>
                </div>
                
                <div id="uploadStatus" class="upload-status"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">📤 Загрузить</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно редактирования ГОСТ -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Редактирование ГОСТа</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form id="editForm" onsubmit="submitGostEdit(event)" enctype="multipart/form-data">
                <input type="hidden" id="edit_index" name="index">
                
                <div class="form-group">
                    <label class="form-label" for="edit_gost_number">Номер ГОСТ *</label>
                    <input type="text" class="form-input" id="edit_gost_number" name="gost_number" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_title">Название *</label>
                    <input type="text" class="form-input" id="edit_title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_category">Категория *</label>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <select class="form-select" id="edit_category" name="category" required style="flex: 1;">
                            <option value="">Выберите категорию</option>
                            <option value="Крепёжные изделия">Крепёжные изделия</option>
                            <option value="Прокат">Прокат</option>
                            <option value="Трубы">Трубы</option>
                            <option value="Листовой металл">Листовой металл</option>
                            <option value="Электротехнические материалы">Электротехнические материалы</option>
                            <option value="Изоляционные материалы">Изоляционные материалы</option>
                            <option value="Лакокрасочные материалы">Лакокрасочные материалы</option>
                            <option value="Металлопрокат">Металлопрокат</option>
                            <option value="Металлы">Металлы</option>
                            <option value="Подшипники">Подшипники</option>
                            <option value="Сварочные материалы">Сварочные материалы</option>
                            <option value="Масла и смазки">Масла и смазки</option>
                            <option value="Инструмент">Инструмент</option>
                            <option value="Другое">Другое</option>
                        </select>
                        <button type="button" class="btn btn-secondary" onclick="addNewCategoryEdit()" style="padding: 10px 14px; border-radius: var(--border-radius-lg);" title="Добавить новую категорию">
                            ➕
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="deleteCategoryEdit()" style="padding: 10px 14px; border-radius: var(--border-radius-lg);" title="Удалить выбранную категорию">
                            🗑️
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_status">Статус</label>
                    <select class="form-select" id="edit_status" name="status">
                        <option value="Действующий">Действующий</option>
                        <option value="Заменён">Заменён</option>
                        <option value="Отменён">Отменён</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_gost_file">Новый файл PDF (необязательно)</label>
                    <div class="file-upload-wrapper" id="editFileUploadWrapper" onclick="document.getElementById('edit_gost_file').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Перетащите файл или кликните</div>
                        <div class="file-upload-hint">PDF, макс. 50 MB (оставьте пустым, чтобы сохранить текущий файл)</div>
                        <div class="file-name-display" id="editFileNameDisplay"></div>
                        <input type="file" id="edit_gost_file" name="gost_file" accept=".pdf,application/pdf" style="display: none;">
                    </div>
                    <div class="help-text" id="currentFileDisplay" style="margin-top: 8px; font-size: 11px; color: var(--text-secondary);"></div>
                </div>
                
                <div id="editUploadStatus" class="upload-status"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">💾 Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function switchTab(tabName) {
        // Убираем активный класс со всех вкладок
        document.querySelectorAll('.doc-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.doc-section').forEach(section => section.classList.remove('active'));
        
        // Добавляем активный класс выбранной вкладке
        event.target.classList.add('active');
        document.getElementById(tabName + '-section').classList.add('active');
    }
    
    function filterStandards(query) {
        const wrappers = document.querySelectorAll('#standardsGrid .standard-card-wrapper');
        query = query.toLowerCase();
        wrappers.forEach(wrapper => {
            const card = wrapper.querySelector('.standard-card');
            if (!card) return;
            
            const gost = card.dataset.gost || '';
            const title = card.dataset.title || '';
            const category = card.dataset.category || '';
            
            if (gost.includes(query) || title.includes(query) || category.includes(query)) {
                wrapper.style.display = 'flex';
            } else {
                wrapper.style.display = 'none';
            }
        });
    }
    
    function filterAbbreviations(query) {
        const cards = document.querySelectorAll('#abbreviationsGrid .abbreviation-card');
        cards.forEach(card => {
            const code = card.dataset.code;
            const name = card.dataset.name;
            if (code.includes(query.toLowerCase()) || name.includes(query.toLowerCase())) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    function filterStructures(query) {
        const items = document.querySelectorAll('#structuresGrid .structure-item');
        query = query.toLowerCase();
        items.forEach(item => {
            const category = item.dataset.category;
            const subcategory = item.dataset.subcategory;
            const pattern = item.dataset.pattern;
            const description = item.dataset.description;
            const examples = item.dataset.examples || '';
            
            if (category.includes(query) || subcategory.includes(query) || 
                pattern.includes(query) || description.includes(query) || examples.includes(query)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Модальное окно загрузки ГОСТ
    function openUploadModal() {
        const modal = document.getElementById('uploadModal');
        modal.style.display = 'flex';
        // Небольшая задержка чтобы браузер применил display перед добавлением класса для анимации
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }
    
    function closeUploadModal() {
        const modal = document.getElementById('uploadModal');
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.getElementById('uploadForm').reset();
            clearUploadStatus();
            clearFileDisplay();
        }, 300); // Ждем завершения анимации
    }
    
    function addNewCategory() {
        const categoryName = prompt('Введите название новой категории:');
        if (categoryName && categoryName.trim()) {
            const trimmedName = categoryName.trim();
            const select = document.getElementById('category');
            
            // Проверяем, существует ли уже такая категория
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.toLowerCase() === trimmedName.toLowerCase()) {
                    exists = true;
                    break;
                }
            }
            
            if (!exists) {
                const newOption = document.createElement('option');
                newOption.value = trimmedName;
                newOption.textContent = trimmedName;
                select.appendChild(newOption);
                select.value = trimmedName;
                
                // Сохраняем категорию в JSON файл
                fetch('upload_gost.php?action=add_category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ category: trimmedName })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ Категория "' + trimmedName + '" добавлена и сохранена');
                        // Обновляем списки категорий после добавления
                        updateCategorySelects();
                    } else {
                        alert('⚠️ Категория добавлена в список, но не сохранена: ' + result.message);
                        // Всё равно обновляем списки
                        updateCategorySelects();
                    }
                })
                .catch(error => {
                    alert('⚠️ Категория добавлена в список, но ошибка сохранения: ' + error.message);
                    // Всё равно обновляем списки
                    updateCategorySelects();
                });
            } else {
                alert('⚠️ Такая категория уже существует');
                select.value = trimmedName;
            }
        }
    }
    
    function deleteCategory() {
        const select = document.getElementById('category');
        const currentValue = select.value;
        
        if (!currentValue) {
            alert('⚠️ Выберите категорию для удаления');
            return;
        }
        
        // Нельзя удалить пустое значение
        if (currentValue === '') {
            alert('⚠️ Нельзя удалить эту категорию');
            return;
        }
        
        if (confirm('Вы уверены, что хотите удалить категорию "' + currentValue + '"? Это действие нельзя отменить.')) {
            // Удаляем категорию из JSON файла
            fetch('upload_gost.php?action=delete_category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ category: currentValue })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Категория "' + currentValue + '" удалена');
                    // Обновляем списки категорий после удаления
                    updateCategorySelects();
                } else {
                    alert('⚠️ Категория удалена из списка, но ошибка при удалении из файла: ' + result.message);
                    // Всё равно обновляем списки
                    updateCategorySelects();
                }
            })
            .catch(error => {
                alert('⚠️ Категория удалена из списка, но ошибка сети: ' + error.message);
                // Всё равно обновляем списки
                updateCategorySelects();
            });
        }
    }
    
    function clearUploadStatus() {
        const statusEl = document.getElementById('uploadStatus');
        if (statusEl) {
            statusEl.className = 'upload-status';
            statusEl.textContent = '';
        }
    }
    
    function clearFileDisplay() {
        const wrapper = document.getElementById('fileUploadWrapper');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const fileInput = document.getElementById('gost_file');
        
        if (wrapper) wrapper.classList.remove('has-file');
        if (fileNameDisplay) fileNameDisplay.textContent = '';
        if (fileInput) fileInput.value = '';
    }
    
    function clearEditFileDisplay() {
        const wrapper = document.getElementById('editFileUploadWrapper');
        const fileNameDisplay = document.getElementById('editFileNameDisplay');
        const fileInput = document.getElementById('edit_gost_file');
        
        if (wrapper) wrapper.classList.remove('has-file');
        if (fileNameDisplay) fileNameDisplay.textContent = '';
        if (fileInput) fileInput.value = '';
    }
    
    // Массив данных ГОСТ для редактирования
    let gostStandardsData = <?= json_encode($gostStandards, JSON_UNESCAPED_UNICODE) ?>;
    
    // Получение уникальных категорий из ГОСТов
    function getUniqueCategories() {
        const categories = new Set();
        gostStandardsData.forEach(gost => {
            if (gost.category && !gost.gost_number.startsWith('_CATEGORY_')) {
                categories.add(gost.category);
            }
        });
        return Array.from(categories).sort();
    }
    
    // Обновление списков категорий в формах
    function updateCategorySelects() {
        const categories = getUniqueCategories();
        const defaultCategories = [
            "Крепёжные изделия", "Прокат", "Трубы", "Листовой металл",
            "Электротехнические материалы", "Изоляционные материалы",
            "Лакокрасочные материалы", "Металлопрокат", "Металлы",
            "Подшипники", "Сварочные материалы", "Масла и смазки",
            "Инструмент", "Другое"
        ];
        
        // Объединяем стандартные категории с пользовательскими
        const allCategories = [...new Set([...defaultCategories, ...categories])].sort();
        
        // Обновляем select в форме добавления
        const categorySelect = document.getElementById('category');
        if (categorySelect) {
            const currentValue = categorySelect.value;
            categorySelect.innerHTML = '<option value="">Выберите категорию</option>';
            allCategories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat;
                option.textContent = cat;
                categorySelect.appendChild(option);
            });
            if (currentValue) categorySelect.value = currentValue;
        }
        
        // Обновляем select в форме редактирования
        const editCategorySelect = document.getElementById('edit_category');
        if (editCategorySelect) {
            const currentValue = editCategorySelect.value;
            editCategorySelect.innerHTML = '<option value="">Выберите категорию</option>';
            allCategories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat;
                option.textContent = cat;
                editCategorySelect.appendChild(option);
            });
            if (currentValue) editCategorySelect.value = currentValue;
        }
    }
    
    // Вызываем при загрузке страницы
    updateCategorySelects();
    
    // Модальное окно редактирования ГОСТ
    function openEditModal(index) {
        const gost = gostStandardsData[index];
        if (!gost) return;
        
        document.getElementById('edit_index').value = index;
        document.getElementById('edit_gost_number').value = gost.gost_number || '';
        document.getElementById('edit_title').value = gost.title || '';
        document.getElementById('edit_category').value = gost.category || '';
        document.getElementById('edit_status').value = gost.status || 'Действующий';
        
        // Показываем текущий файл
        const currentFileDisplay = document.getElementById('currentFileDisplay');
        if (gost.file_name) {
            currentFileDisplay.textContent = '📄 Текущий файл: ' + gost.file_name;
        } else {
            currentFileDisplay.textContent = '';
        }
        
        clearEditFileDisplay();
        clearEditStatus();
        
        const modal = document.getElementById('editModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }
    
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.getElementById('editForm').reset();
            clearEditStatus();
            clearEditFileDisplay();
        }, 300);
    }
    
    function addNewCategoryEdit() {
        const categoryName = prompt('Введите название новой категории:');
        if (categoryName && categoryName.trim()) {
            const trimmedName = categoryName.trim();
            const select = document.getElementById('edit_category');
            
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.toLowerCase() === trimmedName.toLowerCase()) {
                    exists = true;
                    break;
                }
            }
            
            if (!exists) {
                const newOption = document.createElement('option');
                newOption.value = trimmedName;
                newOption.textContent = trimmedName;
                select.appendChild(newOption);
                select.value = trimmedName;
                
                // Сохраняем категорию в JSON файл
                fetch('upload_gost.php?action=add_category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ category: trimmedName })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ Категория "' + trimmedName + '" добавлена и сохранена');
                        // Обновляем списки категорий после добавления
                        updateCategorySelects();
                    } else {
                        alert('⚠️ Категория добавлена в список, но не сохранена: ' + result.message);
                        // Всё равно обновляем списки
                        updateCategorySelects();
                    }
                })
                .catch(error => {
                    alert('⚠️ Категория добавлена в список, но ошибка сохранения: ' + error.message);
                    // Всё равно обновляем списки
                    updateCategorySelects();
                });
            } else {
                alert('⚠️ Такая категория уже существует');
                select.value = trimmedName;
            }
        }
    }
    
    function deleteCategoryEdit() {
        const select = document.getElementById('edit_category');
        const currentValue = select.value;
        
        if (!currentValue) {
            alert('⚠️ Выберите категорию для удаления');
            return;
        }
        
        // Нельзя удалить пустое значение
        if (currentValue === '') {
            alert('⚠️ Нельзя удалить эту категорию');
            return;
        }
        
        if (confirm('Вы уверены, что хотите удалить категорию "' + currentValue + '"? Это действие нельзя отменить.')) {
            // Удаляем категорию из JSON файла
            fetch('upload_gost.php?action=delete_category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ category: currentValue })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Категория "' + currentValue + '" удалена');
                    // Обновляем списки категорий после удаления
                    updateCategorySelects();
                } else {
                    alert('⚠️ Категория удалена из списка, но ошибка при удалении из файла: ' + result.message);
                    // Всё равно обновляем списки
                    updateCategorySelects();
                }
            })
            .catch(error => {
                alert('⚠️ Категория удалена из списка, но ошибка сети: ' + error.message);
                // Всё равно обновляем списки
                updateCategorySelects();
            });
        }
    }
    
    function clearEditStatus() {
        const statusEl = document.getElementById('editUploadStatus');
        if (statusEl) {
            statusEl.className = 'upload-status';
            statusEl.textContent = '';
        }
    }
    
    function showEditStatus(message, type) {
        const statusEl = document.getElementById('editUploadStatus');
        statusEl.textContent = message;
        statusEl.className = 'upload-status ' + type;
        statusEl.style.display = 'block';
    }
    
    // Обработчик выбора файла
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('gost_file');
        const wrapper = document.getElementById('fileUploadWrapper');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        
        if (fileInput && wrapper && fileNameDisplay) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    wrapper.classList.add('has-file');
                    fileNameDisplay.textContent = '📎 ' + file.name + ' (' + formatFileSize(file.size) + ')';
                } else {
                    wrapper.classList.remove('has-file');
                    fileNameDisplay.textContent = '';
                }
            });
            
            // Drag and drop
            wrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                wrapper.style.borderColor = 'var(--primary-color)';
                wrapper.style.background = 'rgba(37, 99, 235, 0.1)';
            });
            
            wrapper.addEventListener('dragleave', function(e) {
                e.preventDefault();
                wrapper.style.borderColor = '';
                wrapper.style.background = '';
            });
            
            wrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                wrapper.style.borderColor = '';
                wrapper.style.background = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            });
        }
        
        // Обработчик для редактирования
        const editFileInput = document.getElementById('edit_gost_file');
        const editWrapper = document.getElementById('editFileUploadWrapper');
        const editFileNameDisplay = document.getElementById('editFileNameDisplay');
        
        if (editFileInput && editWrapper && editFileNameDisplay) {
            editFileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    editWrapper.classList.add('has-file');
                    editFileNameDisplay.textContent = '📎 ' + file.name + ' (' + formatFileSize(file.size) + ')';
                } else {
                    editWrapper.classList.remove('has-file');
                    editFileNameDisplay.textContent = '';
                }
            });
            
            editWrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                editWrapper.style.borderColor = 'var(--primary-color)';
                editWrapper.style.background = 'rgba(37, 99, 235, 0.1)';
            });
            
            editWrapper.addEventListener('dragleave', function(e) {
                e.preventDefault();
                editWrapper.style.borderColor = '';
                editWrapper.style.background = '';
            });
            
            editWrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                editWrapper.style.borderColor = '';
                editWrapper.style.background = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    editFileInput.files = files;
                    const event = new Event('change');
                    editFileInput.dispatchEvent(event);
                }
            });
        }
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }
    
    function showUploadStatus(message, type) {
        const statusEl = document.getElementById('uploadStatus');
        statusEl.textContent = message;
        statusEl.className = 'upload-status ' + type;
        statusEl.style.display = 'block';
    }
    
    async function submitGostUpload(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const statusEl = document.getElementById('uploadStatus');
        
        // Очищаем предыдущий статус
        clearUploadStatus();
        
        // Валидация на клиенте
        const gostNumber = form.gost_number.value.trim();
        const title = form.title.value.trim();
        const category = form.category.value.trim();
        const file = form.gost_file.files[0];
        
        if (!gostNumber || !title || !category) {
            showUploadStatus('⚠️ Заполните все обязательные поля', 'error');
            return;
        }
        
        if (!file) {
            showUploadStatus('⚠️ Выберите файл для загрузки', 'error');
            return;
        }
        
        if (file.type !== 'application/pdf' && file.type !== 'application/x-pdf') {
            showUploadStatus('⚠️ Разрешены только PDF файлы', 'error');
            return;
        }
        
        if (file.size > 50 * 1024 * 1024) {
            showUploadStatus('⚠️ Файл слишком большой (максимум 50 MB)', 'error');
            return;
        }
        
        // Блокируем кнопку отправки
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Загрузка...';
        
        try {
            const response = await fetch('upload_gost.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('HTTP ошибка: ' + response.status);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showUploadStatus('✅ ' + result.message, 'success');
                setTimeout(() => {
                    closeUploadModal();
                    location.reload(); // Перезагружаем страницу для обновления списка
                }, 1500);
            } else {
                showUploadStatus('❌ ' + result.message, 'error');
            }
        } catch (error) {
            showUploadStatus('❌ Ошибка сети: ' + error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    }
    
    async function submitGostEdit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const statusEl = document.getElementById('editUploadStatus');
        
        clearEditStatus();
        
        const index = form.index.value;
        const gostNumber = form.gost_number.value.trim();
        const title = form.title.value.trim();
        const category = form.category.value.trim();
        const file = form.gost_file.files[0];
        
        if (!gostNumber || !title || !category) {
            showEditStatus('⚠️ Заполните все обязательные поля', 'error');
            return;
        }
        
        if (file && file.type !== 'application/pdf' && file.type !== 'application/x-pdf') {
            showEditStatus('⚠️ Разрешены только PDF файлы', 'error');
            return;
        }
        
        if (file && file.size > 50 * 1024 * 1024) {
            showEditStatus('⚠️ Файл слишком большой (максимум 50 MB)', 'error');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Сохранение...';
        
        try {
            const response = await fetch('upload_gost.php?action=edit', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('HTTP ошибка: ' + response.status);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showEditStatus('✅ ' + result.message, 'success');
                setTimeout(() => {
                    closeEditModal();
                    location.reload();
                }, 1500);
            } else {
                showEditStatus('❌ ' + result.message, 'error');
            }
        } catch (error) {
            showEditStatus('❌ Ошибка сети: ' + error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    }
    
    // Закрытие модального окна по клику вне его
    window.onclick = function(event) {
        const uploadModal = document.getElementById('uploadModal');
        const editModal = document.getElementById('editModal');
        if (event.target === uploadModal) {
            closeUploadModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
    }
    
    // Закрытие по Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const uploadModal = document.getElementById('uploadModal');
            const editModal = document.getElementById('editModal');
            if (uploadModal && (uploadModal.style.display === 'flex' || uploadModal.classList.contains('active'))) {
                closeUploadModal();
            }
            if (editModal && (editModal.style.display === 'flex' || editModal.classList.contains('active'))) {
                closeEditModal();
            }
        }
    });
    </script>
</body>
</html>
