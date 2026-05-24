<?php
/**
 * Паспорта продуктов - просмотр спецификаций и материалов
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Паспорта продуктов';

// Получение паспортов из JSON
$passportsFile = __DIR__ . '/../../../passports.json';
$passportsData = [];
if (file_exists($passportsFile)) {
    $passportsData = json_decode(file_get_contents($passportsFile), true);
}

$passports = $passportsData['passports'] ?? [];
$totalProducts = $passportsData['total_products'] ?? 0;

// Фильтрация
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

if ($search || $categoryFilter) {
    $passports = array_filter($passports, function($p) use ($search, $categoryFilter) {
        $matchSearch = empty($search) || 
            stripos($p['sku'], $search) !== false ||
            stripos($p['basic_info']['name_full'] ?? '', $search) !== false ||
            stripos($p['basic_info']['name_short'] ?? '', $search) !== false;
        
        $matchCategory = empty($categoryFilter) || 
            ($p['basic_info']['category_code'] ?? '') === $categoryFilter;
        
        return $matchSearch && $matchCategory;
    });
}

// Получение уникальных категорий
$categories = [];
foreach ($passportsData['passports'] ?? [] as $p) {
    $catCode = $p['basic_info']['category_code'] ?? 'OTHER';
    $catName = $p['basic_info']['category'] ?? 'Другое';
    if (!isset($categories[$catCode])) {
        $categories[$catCode] = $catName;
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
        .passport-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .passport-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .passport-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .passport-sku {
            font-family: 'Courier New', monospace;
            background: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .passport-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 8px 0 4px;
        }
        .passport-category {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .passport-card-body {
            padding: 16px 20px;
        }
        .materials-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .material-tag {
            background: var(--bg-tertiary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .material-tag strong {
            color: var(--text-primary);
        }
        .stats-bar {
            display: flex;
            gap: 24px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-item {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 16px 24px;
            border-radius: var(--border-radius);
            min-width: 150px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }
        .passport-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .passport-modal-overlay.active {
            display: flex;
        }
        .passport-modal {
            background: white;
            border-radius: var(--border-radius);
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .passport-modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        .passport-modal-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .passport-modal-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        .passport-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 28px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s;
        }
        .passport-modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        .passport-modal-body {
            padding: 24px;
        }
        .passport-section {
            margin-bottom: 24px;
        }
        .passport-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        .materials-table {
            width: 100%;
            border-collapse: collapse;
        }
        .materials-table th {
            text-align: left;
            padding: 12px;
            background: var(--bg-secondary);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .materials-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .materials-table tr:hover {
            background: var(--bg-tertiary);
        }
        .notes-list, .requirements-list {
            list-style: none;
            padding: 0;
        }
        .notes-list li, .requirements-list li {
            padding: 8px 12px;
            background: var(--bg-tertiary);
            margin-bottom: 8px;
            border-radius: 6px;
            font-size: 14px;
        }
        .notes-list li::before {
            content: '📋 ';
        }
        .requirements-list li::before {
            content: '✓ ';
        }
        .specs-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .spec-item {
            background: var(--bg-tertiary);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .spec-name {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            text-align: right;
        }
        .weight-badge {
            background: var(--success-color);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
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
                            <h2>📄 Паспорта продуктов</h2>
                            <p>Спецификации и материалы для производства</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <!-- Статистика -->
                            <div class="stats-bar">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $totalProducts ?></div>
                                    <div class="stat-label">Всего продуктов</div>
                                </div>
                                <div class="stat-item" style="background: linear-gradient(135deg, #10b981, #059669);">
                                    <div class="stat-value"><?= count($categories) ?></div>
                                    <div class="stat-label">Категорий</div>
                                </div>
                            </div>

                            <!-- Фильтры -->
                            <form method="GET" class="filter-form">
                                <div class="filter-row">
                                    <input type="text" name="search" placeholder="Поиск по SKU или названию..." value="<?= e($search) ?>" 
                                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <select name="category" style="width: 250px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        <option value="">Все категории</option>
                                        <?php foreach ($categories as $code => $name): ?>
                                            <option value="<?= e($code) ?>" <?= $categoryFilter == $code ? 'selected' : '' ?>><?= e($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-secondary">Фильтр</button>
                                    <a href="passports.php" class="btn btn-outline">Сброс</a>
                                </div>
                            </form>

                            <!-- Список паспортов -->
                            <div style="margin-top: 20px;">
                                <?php if (empty($passports)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📄</div>
                                        <h3>Паспорта не найдены</h3>
                                        <p>Запустите генератор паспортов или измените параметры поиска</p>
                                    </div>
                                <?php else: ?>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px;">
                                        <?php foreach ($passports as $index => $passport): ?>
                                            <div class="passport-card" data-passport-index="<?= $index ?>" onclick="openPassportModalByIndex(this)">
                                                <div class="passport-card-header">
                                                    <div>
                                                        <span class="passport-sku"><?= e($passport['sku']) ?></span>
                                                        <div class="passport-title"><?= e($passport['basic_info']['name_short'] ?? 'Без названия') ?></div>
                                                        <div class="passport-category"><?= e($passport['basic_info']['category'] ?? '') ?></div>
                                                    </div>
                                                </div>
                                                <div class="passport-card-body">
                                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                                        <span class="weight-badge">⚖️ <?= number_format($passport['total_weight_kg'], 2, ',', ' ') ?> кг</span>
                                                        <span style="font-size: 13px; color: var(--text-secondary);">
                                                            📦 <?= count($passport['materials']) ?> материалов
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="materials-preview">
                                                        <?php 
                                                        $materials = array_slice($passport['materials'], 0, 3);
                                                        foreach ($materials as $mat): 
                                                        ?>
                                                            <span class="material-tag">
                                                                <strong><?= number_format($mat['quantity'], 1) ?></strong> <?= $mat['unit'] ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($passport['materials']) > 3): ?>
                                                            <span class="material-tag">+ ещё <?= count($passport['materials']) - 3 ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно паспорта -->
    <div id="passportModalOverlay" class="passport-modal-overlay" onclick="closePassportModal(event)">
        <div class="passport-modal">
            <div class="passport-modal-header">
                <div>
                    <div class="passport-modal-title" id="modalPassportTitle">Название продукта</div>
                    <div class="passport-modal-subtitle" id="modalPassportSKU">SKU: —</div>
                </div>
                <button class="passport-modal-close" onclick="closePassportModalDirect()">×</button>
            </div>
            <div class="passport-modal-body" id="modalPassportBody">
                <!-- Контент заполняется через JS -->
            </div>
        </div>
    </div>

    <script>
        // Данные паспортов из PHP - передаем через data-атрибут для отладки
        console.log('=== PASSPORTS DEBUG ===');
        console.log('Passports count:', <?= count($passports) ?>);
        var passportsData = <?= json_encode($passports, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>;
        console.log('Passports data loaded:', passportsData.length, 'items');
        console.log('First passport:', passportsData[0]);
        console.log('======================');
        
        // Проверка после загрузки DOM
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking cards...');
            var cards = document.querySelectorAll('.passport-card');
            console.log('Found', cards.length, 'passport cards');
            cards.forEach(function(card, idx) {
                console.log('Card', idx, 'index:', card.getAttribute('data-passport-index'));
            });
        });
        
        // Примерные этапы производства для продуктов (можно загружать из БД)
        var productionStagesTemplate = {
            'MOTORS_3PHASE': [
                { name: 'Изготовление статора', duration: '4 дня' },
                { name: 'Изготовление ротора', duration: '4 дня' },
                { name: 'Намотка обмотки', duration: '3 дня' },
                { name: 'Сборка', duration: '3 дня' },
                { name: 'Испытания', duration: '2 дня' }
            ],
            'PUMPS': [
                { name: 'Литьё корпуса', duration: '2 дня' },
                { name: 'Механическая обработка', duration: '3 дня' },
                { name: 'Сборка гидравлической части', duration: '2 дня' },
                { name: 'Установка электродвигателя', duration: '1 день' },
                { name: 'Гидравлические испытания', duration: '1 день' }
            ],
            'ELECTRIC_HOB': [
                { name: 'Изготовление нагревательного элемента', duration: '2 дня' },
                { name: 'Литьё корпуса', duration: '2 дня' },
                { name: 'Сборка', duration: '2 дня' },
                { name: 'Электрические испытания', duration: '1 день' }
            ],
            'TRANSFORMERS': [
                { name: 'Изготовление магнитопровода', duration: '3 дня' },
                { name: 'Намотка обмоток', duration: '4 дня' },
                { name: 'Сборка активной части', duration: '2 дня' },
                { name: 'Сушка и пропитка', duration: '3 дня' },
                { name: 'Испытания', duration: '2 дня' }
            ]
        };
        
        function openPassportModalByIndex(element) {
            var index = element.getAttribute('data-passport-index');
            var passport = passportsData[index];
            if (!passport) {
                console.error('Паспорт не найден по индексу:', index);
                return;
            }
            openPassportModal(passport);
        }
        
        function openPassportModal(passport) {
            console.log('Opening passport:', passport.sku);
            console.log('Full passport data:', passport);
            
            document.getElementById('modalPassportTitle').textContent = passport.basic_info.name_full || 'Без названия';
            document.getElementById('modalPassportSKU').textContent = 'SKU: ' + (passport.sku || '—');
            
            var html = '';
            
            // Основная информация
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📊 Основная информация</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-item"><div class="spec-name">Артикул</div><div class="spec-value">' + escapeHtml(passport.sku) + '</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Категория</div><div class="spec-value">' + escapeHtml(passport.basic_info.category || '—') + '</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Общий вес</div><div class="spec-value">' + (passport.total_weight_kg || '0') + ' кг</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Гарантия</div><div class="spec-value">' + (passport.warranty_months || 24) + ' мес.</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Серийный учёт</div><div class="spec-value">' + (passport.is_serial_tracked ? '✅ Да' : '❌ Нет') + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Характеристики
            if (passport.specifications && Object.keys(passport.specifications).length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">⚙️ Технические характеристики</div>';
                html += '<div class="specs-list">';
                for (var key in passport.specifications) {
                    if (passport.specifications.hasOwnProperty(key)) {
                        var value = passport.specifications[key];
                        if (Array.isArray(value)) {
                            value = value.join(', ');
                        }
                        html += '<div class="spec-item">';
                        html += '<div class="spec-name">' + escapeHtml(formatSpecName(key)) + '</div>';
                        html += '<div class="spec-value">' + escapeHtml(String(value)) + '</div>';
                        html += '</div>';
                    }
                }
                html += '</div>';
                html += '</div>';
            }
            
            // Этапы производства
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">🏭 Этапы производства</div>';
            var categoryCode = passport.basic_info.category_code || 'OTHER';
            var stages = productionStagesTemplate[categoryCode] || productionStagesTemplate['MOTORS_3PHASE'];
            html += '<div style="display: flex; flex-direction: column; gap: 10px;">';
            for (var i = 0; i < stages.length; i++) {
                var stage = stages[i];
                html += '<div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">';
                html += '<div style="width: 32px; height: 32px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">' + (i + 1) + '</div>';
                html += '<div style="flex: 1;">';
                html += '<div style="font-weight: 600; color: var(--text-primary);">' + escapeHtml(stage.name) + '</div>';
                html += '</div>';
                html += '<div style="font-size: 13px; color: var(--text-secondary);">⏱️ ' + escapeHtml(stage.duration) + '</div>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
            
            // Материалы
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📦 Материалы для производства (' + Object.keys(passport.materials).length + ' поз.)</div>';
            html += '<table class="materials-table">';
            html += '<thead><tr><th>№</th><th>Материал</th><th>Код</th><th>Количество</th><th>Ед.</th><th>Категория</th></tr></thead>';
            html += '<tbody>';
            var matIndex = 0;
            for (var matKey in passport.materials) {
                if (passport.materials.hasOwnProperty(matKey)) {
                    matIndex++;
                    var mat = passport.materials[matKey];
                    html += '<tr>';
                    html += '<td>' + matIndex + '</td>';
                    html += '<td><strong>' + escapeHtml(mat.name) + '</strong></td>';
                    html += '<td><code>' + escapeHtml(mat.material_code) + '</code></td>';
                    html += '<td>' + Number(mat.quantity).toFixed(2).replace('.', ',') + '</td>';
                    html += '<td>' + escapeHtml(mat.unit) + '</td>';
                    html += '<td>' + escapeHtml(mat.category) + '</td>';
                    html += '</tr>';
                }
            }
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            
            // Примечания к производству
            if (passport.production_notes && passport.production_notes.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📋 Примечания к производству</div>';
                html += '<ul class="notes-list">';
                for (var i = 0; i < passport.production_notes.length; i++) {
                    html += '<li>' + escapeHtml(passport.production_notes[i]) + '</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            // Требования к качеству
            if (passport.quality_requirements && passport.quality_requirements.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">✅ Требования к качеству</div>';
                html += '<ul class="requirements-list">';
                for (var i = 0; i < passport.quality_requirements.length; i++) {
                    html += '<li>' + escapeHtml(passport.quality_requirements[i]) + '</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            document.getElementById('modalPassportBody').innerHTML = html;
            document.getElementById('passportModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function formatSpecName(key) {
            var names = {
                'power_kw_min': 'Мощность мин., кВт',
                'power_kw_max': 'Мощность макс., кВт',
                'rpm': 'Обороты, об/мин',
                'shaft_height_mm': 'Высота оси, мм',
                'voltage_v': 'Напряжение, В',
                'frequency_hz': 'Частота, Гц',
                'climate_versions': 'Климатическое исполнение',
                'mounting_versions': 'Варианты монтажа',
                'protection_class': 'Класс защиты',
                'power_kw': 'Мощность, кВт',
                'diameter_mm': 'Диаметр, мм',
                'material': 'Материал',
                'application': 'Применение',
                'type': 'Тип'
            };
            return names[key] || key;
        }
        
        function closePassportModal(event) {
            if (event.target === document.getElementById('passportModalOverlay')) {
                closePassportModalDirect();
            }
        }
        
        function closePassportModalDirect() {
            document.getElementById('passportModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('passportModalOverlay');
                if (modal.classList.contains('active')) {
                    closePassportModalDirect();
                }
            }
        });
    </script>
</body>
</html>
