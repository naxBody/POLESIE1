<?php
/**
 * Справочник материалов - полный каталог с фильтрацией и выбором свойств
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$pageTitle = 'Материалы';

// Получение данных из JSON
$jsonPath = BASE_PATH . '/list_materials.json';
if (!file_exists($jsonPath)) {
    $jsonPath = dirname(BASE_PATH) . '/list_materials.json';
}
$materialsData = [];
$categories = [];
$allMaterials = [];
$availableCombinations = []; // Доступные комбинации свойств по подкатегориям

// Получение остатков из базы данных
$warehouseQuantities = [];
try {
    $stmt = $pdo->query("SELECT code, current_stock FROM materials");
    while ($row = $stmt->fetch()) {
        $warehouseQuantities[$row['code']] = floatval($row['current_stock']);
    }
} catch (Exception $e) {
    // Если таблица не существует или ошибка - продолжаем без количеств
}

if (file_exists($jsonPath)) {
    $jsonData = file_get_contents($jsonPath);
    $materialsData = json_decode($jsonData, true);
    $categories = $materialsData['categories'] ?? [];
    
    // Сбор всех материалов в плоский список
    foreach ($categories as $category) {
        if (isset($category['subcategories'])) {
            foreach ($category['subcategories'] as $subcategory) {
                if (isset($subcategory['materials'])) {
                    foreach ($subcategory['materials'] as $material) {
                        $material['parent_category'] = $category;
                        $material['subcategory'] = $subcategory;
                        // Добавляем количество из склада: сначала из JSON, если нет - из БД
                        $material['warehouse_quantity'] = $material['warehouse_quantity'] ?? ($warehouseQuantities[$material['code_internal']] ?? null);
                        $allMaterials[] = $material;
                    }
                }
                // Сохраняем доступные комбинации свойств
                if (isset($subcategory['available_combinations'])) {
                    $availableCombinations[$subcategory['id']] = $subcategory['available_combinations'];
                }
            }
        }
    }
}

// Получение уникальных значений для фильтров
$materialGrades = [];
$standards = [];
$productForms = [];
$units = [];
$criticalLevels = ['Все', 'Обычные', 'Ответственные'];

foreach ($allMaterials as $mat) {
    $specs = $mat['specifications'] ?? [];
    
    if (!empty($specs['material_grade']) && !in_array($specs['material_grade'], $materialGrades)) {
        $materialGrades[] = $specs['material_grade'];
    }
    if (!empty($specs['standard_doc']) && !in_array($specs['standard_doc'], $standards)) {
        $standards[] = $specs['standard_doc'];
    }
    if (!empty($specs['product_form']) && !in_array($specs['product_form'], $productForms)) {
        $productForms[] = $specs['product_form'];
    }
    if (!empty($mat['base_unit']) && !in_array($mat['base_unit'], $units)) {
        $units[] = $mat['base_unit'];
    }
}

sort($materialGrades);
sort($standards);
sort($productForms);
sort($units);

// Применение фильтров
$filteredMaterials = $allMaterials;

// Фильтр по категории
$filterCategory = $_GET['category'] ?? '';
if ($filterCategory !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterCategory) {
        return $m['category_id'] == $filterCategory;
    });
}

// Фильтр по поиску
$filterSearch = $_GET['search'] ?? '';
if ($filterSearch !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterSearch) {
        // Поиск по названию (полному и краткому)
        $searchInName = stripos($m['name_full'], $filterSearch) !== false || 
                        stripos($m['name_short'], $filterSearch) !== false;
        
        // Поиск по внутреннему коду
        $searchInCode = stripos($m['code_internal'], $filterSearch) !== false;
        
        // Поиск по категории (родительской и подкатегории)
        $searchInCategory = false;
        if (!empty($m['parent_category']['name_ru'])) {
            $searchInCategory = stripos($m['parent_category']['name_ru'], $filterSearch) !== false;
        }
        if (!$searchInCategory && !empty($m['subcategory']['name_ru'])) {
            $searchInCategory = stripos($m['subcategory']['name_ru'], $filterSearch) !== false;
        }
        
        // Поиск по описанию ГОСТ/стандарта
        $searchInGost = false;
        if (!empty($m['specifications']['standard_doc'])) {
            $searchInGost = stripos($m['specifications']['standard_doc'], $filterSearch) !== false;
        }
        
        return $searchInName || $searchInCode || $searchInCategory || $searchInGost;
    });
}

// Фильтр по марке материала
$filterGrade = $_GET['grade'] ?? '';
if ($filterGrade !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterGrade) {
        return isset($m['specifications']['material_grade']) && 
               $m['specifications']['material_grade'] === $filterGrade;
    });
}

// Фильтр по стандарту
$filterStandard = $_GET['standard'] ?? '';
if ($filterStandard !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterStandard) {
        return isset($m['specifications']['standard_doc']) && 
               $m['specifications']['standard_doc'] === $filterStandard;
    });
}

// Фильтр по форме изделия
$filterForm = $_GET['form'] ?? '';
if ($filterForm !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterForm) {
        return isset($m['specifications']['product_form']) && 
               $m['specifications']['product_form'] === $filterForm;
    });
}

// Фильтр по ответственности
$filterCritical = $_GET['critical'] ?? '';
if ($filterCritical !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterCritical) {
        if ($filterCritical === 'critical') {
            return !empty($m['is_critical']);
        } elseif ($filterCritical === 'non_critical') {
            return empty($m['is_critical']);
        }
        return true;
    });
}

// Фильтр по требованию сертификата
$filterCert = $_GET['cert'] ?? '';
if ($filterCert !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterCert) {
        if ($filterCert === 'required') {
            return !empty($m['requires_cert']);
        } elseif ($filterCert === 'not_required') {
            return empty($m['requires_cert']);
        }
        return true;
    });
}

// Фильтр по диаметру (для болтов, прутков и т.д.)
$filterDiameter = $_GET['diameter'] ?? '';
if ($filterDiameter !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterDiameter) {
        $specs = $m['specifications'] ?? [];
        $diameter = $specs['thread_diameter_mm'] ?? $specs['diameter_mm'] ?? $specs['conductor_diameter_mm'] ?? $specs['nominal_diameter_mm'] ?? null;
        if ($diameter === null) return false;
        // Для числовых значений сравниваем как числа
        if (is_numeric($filterDiameter) && is_numeric($diameter)) {
            return floatval($diameter) == floatval($filterDiameter);
        }
        return $diameter == $filterDiameter;
    });
}

// Фильтр по длине
$filterLength = $_GET['length'] ?? '';
if ($filterLength !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterLength) {
        $specs = $m['specifications'] ?? [];
        $length = $specs['length_mm'] ?? $specs['length_m'] ?? null;
        if ($length === null) return false;
        // length_mm может быть массивом или单个 значением
        if (is_array($length)) {
            return in_array($filterLength, $length);
        }
        if (is_numeric($filterLength) && is_numeric($length)) {
            return floatval($length) == floatval($filterLength);
        }
        return $length == $filterLength;
    });
}

// Фильтр по классу прочности
$filterStrengthClass = $_GET['strength_class'] ?? '';
if ($filterStrengthClass !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterStrengthClass) {
        $specs = $m['specifications'] ?? [];
        return isset($specs['strength_class']) && $specs['strength_class'] === $filterStrengthClass;
    });
}

// Фильтр по покрытию
$filterCoating = $_GET['coating'] ?? '';
if ($filterCoating !== '') {
    $filteredMaterials = array_filter($filteredMaterials, function($m) use ($filterCoating) {
        $specs = $m['specifications'] ?? [];
        return isset($specs['coating']) && $specs['coating'] === $filterCoating;
    });
}

// Сортировка
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';

usort($filteredMaterials, function($a, $b) use ($sortBy, $sortOrder) {
    $result = 0;
    switch ($sortBy) {
        case 'name':
            $result = strcmp($a['name_full'], $b['name_full']);
            break;
        case 'code':
            $result = strcmp($a['code_internal'], $b['code_internal']);
            break;
        case 'category':
            $result = strcmp($a['parent_category']['name_ru'] ?? '', $b['parent_category']['name_ru'] ?? '');
            break;
        case 'grade':
            $gradeA = $a['specifications']['material_grade'] ?? '';
            $gradeB = $b['specifications']['material_grade'] ?? '';
            $result = strcmp($gradeA, $gradeB);
            break;
    }
    return $sortOrder === 'desc' ? -$result : $result;
});

// Подготовка данных о комбинациях свойств для JS
$availableCombinationsJson = json_encode($availableCombinations, JSON_UNESCAPED_UNICODE);
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

<style>
.materials-page {
    padding: 24px;
}

.filters-panel {
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
}

.filters-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.filters-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dynamic-filter {
    display: none;
}

.filter-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-select,
.filter-input {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    cursor: pointer;
    transition: border-color var(--transition-fast);
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.filter-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    align-items: center;
}

.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.filter-chip-remove {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity var(--transition-fast);
}

.filter-chip-remove:hover {
    opacity: 1;
}

.stats-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
}

.stats-count {
    font-size: 14px;
    color: var(--text-secondary);
}

.stats-count strong {
    color: var(--text-primary);
    font-size: 16px;
}

.view-controls {
    display: flex;
    gap: 8px;
}

.view-btn {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all var(--transition-fast);
    font-size: 14px;
}

.view-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.materials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.material-card {
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    cursor: pointer;
    border: 1px solid transparent;
}

.material-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-color);
}

.material-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.material-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.material-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.4;
}

.material-code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: var(--text-secondary);
    background: var(--gray-100);
    padding: 4px 8px;
    border-radius: 4px;
    margin-top: 8px;
    display: inline-block;
}

.material-specs {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.spec-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 8px;
}

.spec-label {
    color: var(--text-secondary);
}

.spec-value {
    font-weight: 500;
    color: var(--text-primary);
}

.quantity-badge {
    display: inline;
    align-items: center;
    gap: 0;
    padding: 0;
    border-radius: 0;
    font-size: 13px;
    font-weight: 500;
    margin-top: 0;
    background: transparent;
    color: var(--text-primary);
}

.quantity-badge-high {
    background: transparent;
    color: var(--text-primary);
}

.quantity-badge-medium {
    background: transparent;
    color: var(--text-primary);
}

.quantity-badge-low {
    background: transparent;
    color: var(--danger-color);
}

.material-badges {
    display: flex;
    gap: 6px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.badge-critical {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-cert {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.materials-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
}

.materials-table thead th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    background: var(--gray-50);
    border-bottom: 2px solid var(--border-color);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.materials-table thead th:hover {
    background: var(--gray-100);
}

.materials-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 14px;
}

.materials-table tbody tr:hover {
    background: var(--gray-50);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-secondary);
}

/* Modal styles */
.modal-material {
    max-width: 800px;
}

.modal-material-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.modal-section {
    margin-bottom: 20px;
}

.modal-section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--primary-color);
}

.specs-list {
    display: grid;
    gap: 10px;
}

.spec-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    font-size: 13px;
}

.spec-item-label {
    color: var(--text-secondary);
}

.spec-item-value {
    font-weight: 500;
    color: var(--text-primary);
}

.gost-link {
    color: var(--primary-color);
    text-decoration: none;
    transition: all var(--transition-fast);
}

.gost-link:hover {
    text-decoration: underline;
    color: var(--primary-dark);
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .materials-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-material-body {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="materials-page">
    <!-- Панель фильтров -->
    <div class="filters-panel">
        <div class="filters-header">
            <div class="filters-title">
                <span>🔍</span>
                Фильтры материалов
            </div>
        </div>
        
        <form method="GET" id="filtersForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Поиск</label>
                    <input type="text" 
                           name="search" 
                           class="filter-input" 
                           placeholder="Название, код..."
                           value="<?= e($filterSearch) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Категория</label>
                    <select name="category" class="filter-select" id="categorySelect" onchange="updatePropertyFilters()">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php if (isset($cat['subcategories'])): ?>
                                <?php foreach ($cat['subcategories'] as $subcat): ?>
                                    <option value="<?= $subcat['id'] ?>" 
                                            <?= $filterCategory == $subcat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name_ru']) ?> → <?= e($subcat['name_ru']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Динамические фильтры свойств -->
                <div class="filter-group dynamic-filter" id="diameterFilter" style="display: none;">
                    <label class="filter-label">Диаметр (мм)</label>
                    <select name="diameter" class="filter-select" id="diameterSelect" onchange="this.form.submit()">
                        <option value="">Все</option>
                    </select>
                </div>
                
                <div class="filter-group dynamic-filter" id="lengthFilter" style="display: none;">
                    <label class="filter-label">Длина (мм)</label>
                    <select name="length" class="filter-select" id="lengthSelect" onchange="this.form.submit()">
                        <option value="">Все</option>
                    </select>
                </div>
                
                <div class="filter-group dynamic-filter" id="strengthClassFilter" style="display: none;">
                    <label class="filter-label">Класс прочности</label>
                    <select name="strength_class" class="filter-select" id="strengthClassSelect" onchange="this.form.submit()">
                        <option value="">Все</option>
                    </select>
                </div>
                
                <div class="filter-group dynamic-filter" id="coatingFilter" style="display: none;">
                    <label class="filter-label">Покрытие</label>
                    <select name="coating" class="filter-select" id="coatingSelect" onchange="this.form.submit()">
                        <option value="">Все</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Марка материала</label>
                    <select name="grade" class="filter-select">
                        <option value="">Все марки</option>
                        <?php foreach ($materialGrades as $grade): ?>
                            <option value="<?= e($grade) ?>" <?= $filterGrade === $grade ? 'selected' : '' ?>>
                                <?= e($grade) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Стандарт</label>
                    <select name="standard" class="filter-select">
                        <option value="">Все стандарты</option>
                        <?php foreach ($standards as $std): ?>
                            <option value="<?= e($std) ?>" <?= $filterStandard === $std ? 'selected' : '' ?>>
                                <?= e($std) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Форма изделия</label>
                    <select name="form" class="filter-select">
                        <option value="">Все формы</option>
                        <?php foreach ($productForms as $form): ?>
                            <option value="<?= e($form) ?>" <?= $filterForm === $form ? 'selected' : '' ?>>
                                <?= e($form) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Ответственность</label>
                    <select name="critical" class="filter-select">
                        <option value="">Все</option>
                        <option value="critical" <?= $filterCritical === 'critical' ? 'selected' : '' ?>>Ответственные</option>
                        <option value="non_critical" <?= $filterCritical === 'non_critical' ? 'selected' : '' ?>>Обычные</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Сертификат</label>
                    <select name="cert" class="filter-select">
                        <option value="">Все</option>
                        <option value="required" <?= $filterCert === 'required' ? 'selected' : '' ?>>Требуется</option>
                        <option value="not_required" <?= $filterCert === 'not_required' ? 'selected' : '' ?>>Не требуется</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Сортировка</label>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>По названию</option>
                        <option value="code" <?= $sortBy === 'code' ? 'selected' : '' ?>>По коду</option>
                        <option value="category" <?= $sortBy === 'category' ? 'selected' : '' ?>>По категории</option>
                        <option value="grade" <?= $sortBy === 'grade' ? 'selected' : '' ?>>По марке</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Применить фильтры</button>
                <a href="materials.php" class="btn btn-outline">Сбросить</a>
                
                <?php if ($filterSearch || $filterCategory || $filterGrade || $filterStandard || $filterForm || $filterCritical || $filterCert || $filterDiameter || $filterLength || $filterStrengthClass || $filterCoating): ?>
                    <div class="active-filters">
                        <span style="font-size: 13px; color: var(--text-secondary); align-self: center;">Активные фильтры:</span>
                        <?php if ($filterSearch): ?>
                            <span class="filter-chip">
                                Поиск: <?= e($filterSearch) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'search', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterCategory): ?>
                            <?php 
                            $catName = '';
                            foreach ($categories as $cat) {
                                if (isset($cat['subcategories'])) {
                                    foreach ($cat['subcategories'] as $subcat) {
                                        if ($subcat['id'] == $filterCategory) {
                                            $catName = $cat['name_ru'] . ' → ' . $subcat['name_ru'];
                                        }
                                    }
                                }
                            }
                            ?>
                            <span class="filter-chip">
                                Категория: <?= e($catName) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'category', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterDiameter): ?>
                            <span class="filter-chip">
                                Диаметр: <?= e($filterDiameter) ?> мм
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'diameter', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterLength): ?>
                            <span class="filter-chip">
                                Длина: <?= e($filterLength) ?> мм
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'length', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterStrengthClass): ?>
                            <span class="filter-chip">
                                Класс прочности: <?= e($filterStrengthClass) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'strength_class', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterCoating): ?>
                            <span class="filter-chip">
                                Покрытие: <?= e($filterCoating) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'coating', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterGrade): ?>
                            <span class="filter-chip">
                                Марка: <?= e($filterGrade) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'grade', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterStandard): ?>
                            <span class="filter-chip">
                                Стандарт: <?= e($filterStandard) ?>
                                <a href="?<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'standard', ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Статистика и управление видом -->
    <div class="stats-bar">
        <div class="stats-count">
            Найдено материалов: <strong><?= count($filteredMaterials) ?></strong> из <strong><?= count($allMaterials) ?></strong>
        </div>
        <div class="view-controls">
            <button class="view-btn active" onclick="setView('grid')" title="Карточки">▦</button>
            <button class="view-btn" onclick="setView('table')" title="Таблица">☰</button>
        </div>
    </div>
    
    <!-- Список материалов -->
    <?php if (empty($filteredMaterials)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>Материалы не найдены</h3>
            <p>Попробуйте изменить параметры фильтров</p>
        </div>
    <?php else: ?>
        <!-- Вид: Карточки -->
        <div class="materials-grid" id="materialsGrid">
            <?php foreach ($filteredMaterials as $material): ?>
                <div class="material-card" onclick="openMaterialModal(<?= htmlspecialchars(json_encode($material), ENT_QUOTES, 'UTF-8') ?>)">
                    <div class="material-card-header">
                        <div>
                            <div class="material-category">
                                <?= e($material['parent_category']['name_ru'] ?? '') ?> → 
                                <?= e($material['subcategory']['name_ru'] ?? '') ?>
                            </div>
                            <div class="material-name"><?= e($material['name_full']) ?></div>
                        </div>
                    </div>
                    
                    <div class="material-code"><?= e($material['code_internal']) ?></div>
                    
                    <div class="material-specs">
                        <?php 
                        $specs = $material['specifications'] ?? [];
                        $displaySpecs = array_slice($specs, 0, 3, true);
                        foreach ($displaySpecs as $key => $value): 
                            if (is_array($value)) {
                                $value = implode(', ', array_slice($value, 0, 3)) . (count($value) > 3 ? '...' : '');
                            }
                            // Перевод ключей спецификаций на русский язык
                            $specLabels = [
                                'material_grade' => 'Марка',
                                'standard_doc' => 'Стандарт',
                                'product_form' => 'Форма',
                                'diameter_mm' => 'Диаметр',
                                'length_m' => 'Длина',
                                'length_mm' => 'Длина',
                                'thickness_mm' => 'Толщина',
                                'width_mm' => 'Ширина',
                                'strength_class' => 'Кл. прочности',
                                'coating' => 'Покрытие',
                                'thread_diameter_mm' => 'Диаметр резьбы',
                                'density_kg_m3' => 'Плотность',
                                'tensile_strength_mpa' => 'Прочность',
                                'yield_strength_mpa' => 'Предел текуч.',
                                'hardness_hb' => 'Твёрдость HB',
                                'hardness_hrc' => 'Твёрдость HRC',
                                'temperature_range_c' => 'Темп. диапазон',
                                'viscosity_sec_20C' => 'Вязкость',
                                'solid_content_percent' => 'Сухое вещество %',
                                'flash_point_c' => 'Темп. вспышки',
                                'shelf_life_months' => 'Срок хранения',
                                'packaging' => 'Упаковка',
                                'color' => 'Цвет',
                                'application' => 'Применение'
                            ];
                            $label = $specLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                        ?>
                            <div class="spec-row">
                                <span class="spec-label"><?= e($label) ?></span>
                                <span class="spec-value"><?= is_array($value) ? e(implode(', ', array_slice($value, 0, 3))) : e($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php 
                    // Определение цвета бейджа количества
                    $qtyClass = 'quantity-badge-medium';
                    $qtyText = 'Нет данных';
                    if ($material['warehouse_quantity'] !== null) {
                        $qty = floatval($material['warehouse_quantity']);
                        $qtyText = number_format($qty, 2, ',', ' ');
                        if ($qty <= 10) {
                            $qtyClass = 'quantity-badge-low';
                        } elseif ($qty <= 50) {
                            $qtyClass = 'quantity-badge-medium';
                        } else {
                            $qtyClass = 'quantity-badge-high';
                        }
                    }
                    ?>
                    
                    <div class="material-badges">
                        <?php if (!empty($material['is_critical'])): ?>
                            <span class="badge-critical">⚠ Ответственный</span>
                        <?php endif; ?>
                        <?php if (!empty($material['requires_cert'])): ?>
                            <span class="badge-cert">📄 Сертификат</span>
                        <?php endif; ?>
                        <span class="quantity-badge <?= $qtyClass ?>"><?= $qtyText ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Вид: Таблица -->
        <table class="materials-table" id="materialsTable" style="display: none;">
            <thead>
                <tr>
                    <th>Код</th>
                    <th>Наименование</th>
                    <th>Категория</th>
                    <th>Марка</th>
                    <th>Стандарт</th>
                    <th>Ед.</th>
                    <th>На складе</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredMaterials as $material): ?>
                    <?php 
                    // Определение цвета бейджа количества для таблицы
                    $qtyClass = 'quantity-badge-medium';
                    $qtyText = 'Нет данных';
                    if ($material['warehouse_quantity'] !== null) {
                        $qty = floatval($material['warehouse_quantity']);
                        $qtyText = number_format($qty, 2, ',', ' ');
                        if ($qty <= 10) {
                            $qtyClass = 'quantity-badge-low';
                        } elseif ($qty <= 50) {
                            $qtyClass = 'quantity-badge-medium';
                        } else {
                            $qtyClass = 'quantity-badge-high';
                        }
                    }
                    ?>
                    <tr onclick="openMaterialModal(<?= htmlspecialchars(json_encode($material), ENT_QUOTES, 'UTF-8') ?>)" style="cursor: pointer;">
                        <td><code><?= e($material['code_internal']) ?></code></td>
                        <td>
                            <strong><?= e($material['name_full']) ?></strong><br>
                            <small style="color: var(--text-muted);"><?= e($material['name_short']) ?></small>
                        </td>
                        <td>
                            <small><?= e($material['parent_category']['name_ru'] ?? '') ?></small><br>
                            <strong><?= e($material['subcategory']['name_ru'] ?? '') ?></strong>
                        </td>
                        <td><?= e($material['specifications']['material_grade'] ?? '—') ?></td>
                        <td><small><?= e($material['specifications']['standard_doc'] ?? '—') ?></small></td>
                        <td><?= e($material['base_unit']) ?></td>
                        <td>
                            <span class="quantity-badge <?= $qtyClass ?>" style="font-size: 13px;"><?= $qtyText ?></span>
                        </td>
                        <td>
                            <?php if (!empty($material['is_critical'])): ?>
                                <span class="badge badge-danger">Ответств.</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Обычный</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно материала -->
<div class="modal-overlay" id="materialModal" onclick="closeMaterialModal(event)">
    <div class="modal modal-material">
        <div class="modal-header">
            <h3 class="modal-title" id="modalMaterialName"></h3>
            <button class="modal-close" onclick="closeMaterialModal()">✕</button>
        </div>
        <div class="modal-body modal-material-body" id="modalMaterialBody">
            <!-- Контент заполняется через JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeMaterialModal()">Закрыть</button>
            <button class="btn btn-primary" onclick="printMaterial()">🖨 Печать</button>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>

<script>
// Данные о доступных комбинациях свойств из PHP
const availableCombinations = <?= $availableCombinationsJson ?>;

let currentMaterial = null;

// Функция обновления фильтров свойств при выборе категории
function updatePropertyFilters() {
    const categorySelect = document.getElementById('categorySelect');
    const selectedCategory = categorySelect.value;
    
    // Скрываем все динамические фильтры
    document.querySelectorAll('.dynamic-filter').forEach(filter => {
        filter.style.display = 'none';
    });
    
    // Если категория не выбрана, выходим
    if (!selectedCategory) {
        return;
    }
    
    // Получаем доступные комбинации для выбранной категории
    const combinations = availableCombinations[selectedCategory];
    if (!combinations) {
        return;
    }
    
    // Заполняем и показываем фильтр диаметра
    if (combinations.thread_diameter_mm || combinations.diameter_mm || combinations.conductor_diameter_mm || combinations.nominal_diameter_mm) {
        const diameterSelect = document.getElementById('diameterSelect');
        const diameterFilter = document.getElementById('diameterFilter');
        diameterSelect.innerHTML = '<option value="">Все</option>';
        
        let diameters = combinations.thread_diameter_mm || combinations.diameter_mm || combinations.conductor_diameter_mm || combinations.nominal_diameter_mm || [];
        if (Array.isArray(diameters)) {
            diameters.forEach(d => {
                const option = document.createElement('option');
                option.value = d;
                option.textContent = d + ' мм';
                diameterSelect.appendChild(option);
            });
        }
        diameterFilter.style.display = 'flex';
    }
    
    // Заполняем и показываем фильтр длины
    if (combinations.length_mm) {
        const lengthSelect = document.getElementById('lengthSelect');
        const lengthFilter = document.getElementById('lengthFilter');
        lengthSelect.innerHTML = '<option value="">Все</option>';
        
        let lengths = combinations.length_mm;
        if (typeof lengths === 'object' && !Array.isArray(lengths)) {
            // Если длины зависят от диаметра, берём все уникальные значения
            const allLengths = new Set();
            Object.values(lengths).forEach(lenArray => {
                if (Array.isArray(lenArray)) {
                    lenArray.forEach(l => allLengths.add(l));
                }
            });
            lengths = Array.from(allLengths).sort((a, b) => a - b);
        }
        if (Array.isArray(lengths)) {
            lengths.forEach(l => {
                const option = document.createElement('option');
                option.value = l;
                option.textContent = l + ' мм';
                lengthSelect.appendChild(option);
            });
        }
        lengthFilter.style.display = 'flex';
    }
    
    // Заполняем и показываем фильтр класса прочности
    if (combinations.strength_class) {
        const strengthSelect = document.getElementById('strengthClassSelect');
        const strengthFilter = document.getElementById('strengthClassFilter');
        strengthSelect.innerHTML = '<option value="">Все</option>';
        
        if (Array.isArray(combinations.strength_class)) {
            combinations.strength_class.forEach(s => {
                const option = document.createElement('option');
                option.value = s;
                option.textContent = s;
                strengthSelect.appendChild(option);
            });
        }
        strengthFilter.style.display = 'flex';
    }
    
    // Заполняем и показываем фильтр покрытия
    if (combinations.coating) {
        const coatingSelect = document.getElementById('coatingSelect');
        const coatingFilter = document.getElementById('coatingFilter');
        coatingSelect.innerHTML = '<option value="">Все</option>';
        
        if (Array.isArray(combinations.coating)) {
            combinations.coating.forEach(c => {
                const option = document.createElement('option');
                option.value = c;
                option.textContent = c;
                coatingSelect.appendChild(option);
            });
        }
        coatingFilter.style.display = 'flex';
    }

    // Показываем расшифровку формата кода для выбранной категории
    showCodeFormatInfo(combinations);
}

// Функция отображения информации о формате кода
function showCodeFormatInfo(combinations) {
    // Удаляем существующую информацию если есть
    const existingInfo = document.getElementById('codeFormatInfo');
    if (existingInfo) {
        existingInfo.remove();
    }

    if (!combinations._code_format_ru) {
        return;
    }

    // Создаём блок с расшифровкой формата кода
    const filtersPanel = document.querySelector('.filters-panel');
    const infoDiv = document.createElement('div');
    infoDiv.id = 'codeFormatInfo';
    infoDiv.style.cssText = `
        margin-top: 16px;
        padding: 12px 16px;
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%);
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: var(--border-radius);
        font-size: 13px;
        color: var(--text-primary);
    `;
    infoDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="font-size: 16px;">📐</span>
            <strong>Формат кода материала:</strong>
        </div>
        <div style="font-family: 'Courier New', monospace; background: var(--gray-100); padding: 8px 12px; border-radius: 4px; margin-bottom: 8px;">
            ${escapeHtml(combinations._code_format || '')}
        </div>
        <div style="color: var(--text-secondary);">
            ${escapeHtml(combinations._code_format_ru || '')}
        </div>
    `;
    filtersPanel.appendChild(infoDiv);
}

// Вызываем при загрузке страницы, если категория уже выбрана
document.addEventListener('DOMContentLoaded', function() {
    updatePropertyFilters();
});

function setView(view) {
    const grid = document.getElementById('materialsGrid');
    const table = document.getElementById('materialsTable');
    const btns = document.querySelectorAll('.view-btn');
    
    if (view === 'grid') {
        grid.style.display = 'grid';
        table.style.display = 'none';
        btns[0].classList.add('active');
        btns[1].classList.remove('active');
    } else {
        grid.style.display = 'none';
        table.style.display = 'table';
        btns[0].classList.remove('active');
        btns[1].classList.add('active');
    }
    
    localStorage.setItem('materialsView', view);
}

// Восстановление вида из localStorage
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('materialsView') || 'grid';
    setView(savedView);
});

function openMaterialModal(material) {
    currentMaterial = material;
    
    const modal = document.getElementById('materialModal');
    const nameEl = document.getElementById('modalMaterialName');
    const bodyEl = document.getElementById('modalMaterialBody');
    
    nameEl.textContent = material.name_full;
    
    const specs = material.specifications || {};
    
    // Словарь перевода ключей характеристик на русский язык
    const specLabels = {
        'material_grade': 'Марка материала',
        'standard_doc': 'Нормативный документ',
        'product_form': 'Форма изделия',
        'diameter_mm': 'Диаметр (мм)',
        'length_m': 'Длина (м)',
        'thickness_mm': 'Толщина (мм)',
        'width_mm': 'Ширина (мм)',
        'length_mm': 'Длина (мм)',
        'treatment': 'Обработка',
        'density_kg_m3': 'Плотность (кг/м³)',
        'tensile_strength_mpa': 'Временное сопротивление (МПа)',
        'yield_strength_mpa': 'Предел текучести (МПа)',
        'alloy_elements': 'Легирующие элементы',
        'weight_per_piece_kg': 'Вес одной штуки (кг)',
        'hardness_hb': 'Твёрдость (HB)',
        'chemical_composition': 'Химический состав',
        'application': 'Применение',
        'elongation_percent': 'Относительное удлинение (%)',
        'product_type': 'Тип продукта',
        'mark': 'Марка',
        'conductor_material': 'Материал проводника',
        'conductor_diameter_mm': 'Диаметр проводника (мм)',
        'insulation_type': 'Тип изоляции',
        'thermal_class': 'Класс нагревостойкости',
        'voltage_test_v': 'Испытательное напряжение (В)',
        'elongation_percent_min': 'Мин. относительное удлинение (%)',
        'adherence_test': 'Испытание на адгезию',
        'resistance_ohm_km_20C': 'Сопротивление (Ом/км при 20°C)',
        'heat_shock_resistance': 'Термостойкость',
        'chemical_resistance': 'Химическая стойкость',
        'electrical_resistivity_ohm_mm2_m': 'Удельное электросопротивление (Ом·мм²/м)',
        'core_type': 'Тип сердечника',
        'core_material': 'Материал сердечника',
        'core_dimensions_mm': 'Размеры сердечника (мм)',
        'stack_factor': 'Коэффициент заполнения',
        'specific_loss_w_kg': 'Удельные потери (Вт/кг)',
        'magnetic_induction_t': 'Магнитная индукция (Тл)',
        'coating_type': 'Тип покрытия',
        'polymer_type': 'Тип полимера',
        'color': 'Цвет',
        'operating_temp_c': 'Рабочая температура (°C)',
        'shelf_life_months': 'Срок хранения (мес)',
        'viscosity_pa_s': 'Вязкость (Па·с)',
        'solid_content_percent': 'Содержание сухого вещества (%)',
        'acid_number_mg_koh': 'Кислотное число (мг КОН/г)',
        'saponification_value': 'Число омыления',
        'iodine_value': 'Йодное число',
        'flash_point_c': 'Температура вспышки (°C)',
        'pour_point_c': 'Температура застывания (°C)',
        'kinematic_viscosity_cst': 'Кинематическая вязкость (сСт)',
        'base_oil': 'Базовое масло',
        'thickener_type': 'Тип загустителя',
        'nlgi_grade': 'Класс NLGI',
        'dropping_point_c': 'Температура каплепадения (°C)',
        'four_ball_weld_load_kg': 'Нагрузка сваривания (4 шара, кг)',
        'four_ball_scars_mm': 'Диаметр пятна износа (4 шара, мм)',
        'grease_color': 'Цвет смазки',
        'penetration_0_1mm': 'Пенетрация (0,1 мм)',
        'ep_additives': 'EP-присадки',
        'anti_wear_additives': 'Противоизносные присадки',
        'corrosion_inhibitors': 'Ингибиторы коррозии',
        'antioxidants': 'Антиокислители',
        'demulsifier': 'Деэмульгатор',
        'foam_inhibitor': 'Противопенная присадка',
        'rust_prevention': 'Защита от ржавления',
        'water_washout_percent': 'Вымываемость водой (%)',
        'sprayability': 'Распыляемость',
        'air_release_min': 'Время releases воздуха (мин)',
        'demulsibility_min': 'Деэмульгируемость (мин)',
        'copper_corrosion': 'Коррозия меди',
        'oxidation_stability': 'Окислительная стабильность',
        'evaporation_loss_percent': 'Потери на испарение (%)',
        'low_temp_fluidity': 'Низкотемпературная текучесть',
        'high_temp_performance': 'Высокотемпературные свойства',
        'load_capacity': 'Несущая способность',
        'wear_protection': 'Защита от износа',
        'friction_coefficient': 'Коэффициент трения',
        'compatibility': 'Совместимость',
        'filterability': 'Фильтруемость',
        'hydrolytic_stability': 'Гидролитическая стабильность',
        'microbial_resistance': 'Микробиологическая стойкость',
        'seal_compatibility': 'Совместимость с уплотнениями',
        'paint_compatibility': 'Совместимость с красками',
        'noise_level': 'Уровень шума',
        'dielectric_strength_kv': 'Электрическая прочность (кВ)',
        'dielectric_constant': 'Диэлектрическая проницаемость',
        'dissipation_factor': 'Тангенс угла диэлектрических потерь',
        'volume_resistivity_ohm_cm': 'Объёмное удельное сопротивление (Ом·см)',
        'surface_resistivity_ohm': 'Поверхностное удельное сопротивление (Ом)',
        'arc_resistance_sec': 'Дуговая стойкость (сек)',
        'comparative_tracking_index_v': 'Сравнительный индекс трекингообразования (В)',
        'flame_retardancy': 'Огнестойкость',
        'ul94_rating': 'Рейтинг UL94',
        'oxygen_index_percent': 'Кислородный индекс (%)',
        'smoke_density': 'Плотность дыма',
        'toxicity_index': 'Индекс токсичности',
        'uv_resistance': 'УФ-стойкость',
        'weather_resistance': 'Атмосферостойкость',
        'cold_resistance': 'Морозостойкость',
        'heat_aging_resistance': 'Термовозрастная стойкость',
        'hydrolysis_resistance': 'Стойкость к гидролизу',
        'chemical_resistance_acids': 'Химическая стойкость (кислоты)',
        'chemical_resistance_alkalis': 'Химическая стойкость (щёлочи)',
        'chemical_resistance_solvents': 'Химическая стойкость (растворители)',
        'chemical_resistance_oils': 'Химическая стойкость (масла)',
        'abrasion_resistance': 'Износостойкость',
        'impact_strength': 'Ударная прочность',
        'flexural_strength_mpa': 'Предел прочности при изгибе (МПа)',
        'flexural_modulus_mpa': 'Модуль упругости при изгибе (МПа)',
        'compressive_strength_mpa': 'Предел прочности при сжатии (МПа)',
        'compressive_modulus_mpa': 'Модуль упругости при сжатии (МПа)',
        'shear_strength_mpa': 'Предел прочности при сдвиге (МПа)',
        'peel_strength_n_mm': 'Прочность на отслаивание (Н/мм)',
        'lap_shear_strength_mpa': 'Прочность нахлёсточного соединения (МПа)',
        'cure_time_min': 'Время отверждения (мин)',
        'cure_temp_c': 'Температура отверждения (°C)',
        'pot_life_min': 'Время жизни смеси (мин)',
        'mix_ratio': 'Пропорция смешивания',
        'shelf_life_years': 'Срок хранения (лет)',
        'storage_temp_c': 'Температура хранения (°C)',
        'humidity_limit_percent': 'Предельная влажность (%)',
        'light_fastness': 'Светостойкость',
        'washability': 'Моющаяся способность',
        'scrub_resistance': 'Стойкость к истиранию',
        'hiding_power': 'Укрывистость',
        'gloss_level': 'Уровень глянца',
        'dry_time_min': 'Время высыхания (мин)',
        'recoat_time_min': 'Время до повторного нанесения (мин)',
        'through_dry_time_h': 'Время полного высыхания (ч)',
        'primer_required': 'Требуется грунтовка',
        'topcoat_required': 'Требуется финишное покрытие',
        'intercoat_adhesion': 'Межслойная адгезия',
        'overcoatable': 'Возможность перекрытия',
        'sandability': 'Шлифуемость',
        'brushability': 'Способность к нанесению кистью',
        'rollability': 'Способность к нанесению валиком',
        'sprayability_viscosity': 'Распыляемость (вязкость)',
        'transfer_efficiency_percent': 'Эффективность переноса (%)',
        'overspray_percent': 'Перерасход (%)',
        'film_thickness_um': 'Толщина плёнки (мкм)',
        'dry_film_thickness_um': 'Толщина сухой плёнки (мкм)',
        'wet_film_thickness_um': 'Толщина влажной плёнки (мкм)',
        'coverage_m2_l': 'Расход (м²/л)',
        'theoretical_coverage_m2_l': 'Теоретический расход (м²/л)',
        'practical_coverage_m2_l': 'Практический расход (м²/л)',
        'voc_g_l': 'ЛОС (г/л)',
        'solvent_content_percent': 'Содержание растворителя (%)',
        'water_content_percent': 'Содержание воды (%)',
        'ph_value': 'Значение pH',
        'conductivity_us_cm': 'Электропроводность (мкСм/см)',
        'turbidity_ntu': 'Мутность (NTU)',
        'total_dissolved_solids_ppm': 'Общее солесодержание (ppm)',
        'suspended_solids_ppm': 'Взвешенные вещества (ppm)',
        'biochemical_oxygen_demand_mg_l': 'БПК (мг/л)',
        'chemical_oxygen_demand_mg_l': 'ХПК (мг/л)',
        'total_organic_carbon_ppm': 'Общий органический углерод (ppm)',
        'heavy_metals_ppm': 'Тяжёлые металлы (ppm)',
        'chloride_ppm': 'Хлориды (ppm)',
        'sulfate_ppm': 'Сульфаты (ppm)',
        'nitrate_ppm': 'Нитраты (ppm)',
        'phosphate_ppm': 'Фосфаты (ppm)',
        'ammonia_ppm': 'Аммиак (ppm)',
        'fluoride_ppm': 'Фториды (ppm)',
        'cyanide_ppm': 'Цианиды (ppm)',
        'phenol_ppm': 'Фенолы (ppm)',
        'oil_grease_ppm': 'Нефтепродукты (ppm)',
        'surfactants_ppm': 'ПАВ (ppm)',
        'pesticides_ppm': 'Пестициды (ppm)',
        'bacteria_cfu_ml': 'Бактерии (КОЕ/мл)',
        'yeast_mold_cfu_ml': 'Дрожжи и плесень (КОЕ/мл)',
        'coliform_count': 'Колиформные бактерии',
        'e_coli': 'Кишечная палочка',
        'salmonella': 'Сальмонелла',
        'staphylococcus': 'Стафилококк',
        'endotoxin_eu_ml': 'Эндотоксин (ЕД/мл)',
        'particulate_matter': 'Твёрдые частицы',
        'sterility': 'Стерильность',
        'pyrogenicity': 'Пирогенность',
        'biocompatibility': 'Биосовместимость',
        'cytotoxicity': 'Цитотоксичность',
        'sensitization': 'Сенсибилизация',
        'irritation': 'Раздражение',
        'genotoxicity': 'Генотоксичность',
        'carcinogenicity': 'Канцерогенность',
        'mutagenicity': 'Мутагенность',
        'teratogenicity': 'Тератогенность',
        'reproductive_toxicity': 'Репродуктивная токсичность',
        'developmental_toxicity': 'Токсичность для развития',
        'neurotoxicity': 'Нейротоксичность',
        'immunotoxicity': 'Иммунотоксичность',
        'hepatotoxicity': 'Гепатотоксичность',
        'nephrotoxicity': 'Нефротоксичность',
        'cardiotoxicity': 'Кардиотоксичность',
        'pulmonary_toxicity': 'Пульмонологическая токсичность',
        'dermal_toxicity': 'Дермальная токсичность',
        'ocular_toxicity': 'Окулярная токсичность',
        'inhalation_toxicity': 'Ингаляционная токсичность',
        'oral_toxicity': 'Оральная токсичность',
        'intravenous_toxicity': 'Внутривенная токсичность',
        'intramuscular_toxicity': 'Внутримышечная токсичность',
        'subcutaneous_toxicity': 'Подкожная токсичность',
        'intraperitoneal_toxicity': 'Внутрибрюшинная токсичность',
        'topical_toxicity': 'Топическая токсичность',
        'chronic_toxicity': 'Хроническая токсичность',
        'subchronic_toxicity': 'Субхроническая токсичность',
        'acute_toxicity': 'Острая токсичность',
        // Новые ключи из JSON данных
        'abrasive_material': 'Абразивный материал',
        'adhesion_grade': 'Класс адгезии',
        'alkalinity_mg_koh_g': 'Щелочность (мг КОН/г)',
        'alt_unit': 'Альтернативная единица',
        'appearance': 'Внешний вид',
        'aromatics_content_percent': 'Содержание ароматики (%)',
        'available_combinations': 'Доступные комбинации',
        'available_dimensions': 'Доступные размеры',
        'available_length_m': 'Доступная длина (м)',
        'available_thickness_mkm': 'Доступная толщина (мкм)',
        'available_width_mm': 'Доступная ширина (мм)',
        'base': 'Основа',
        'base_material': 'Основной материал',
        'base_unit': 'Базовая единица',
        'bearing_number': 'Номер подшипника',
        'block_height_mm': 'Высота блока (мм)',
        'boiling_point_c': 'Температура кипения (°C)',
        'boiling_range_c': 'Интервал кипения (°C)',
        'bond_type': 'Тип связи',
        'bore_diameter': 'Диаметр отверстия',
        'bursting_strength_kpa': 'Прочность на разрыв (кПа)',
        'cage_material': 'Материал сепаратора',
        'categories': 'Категории',
        'category_id': 'ID категории',
        'chemical_formula': 'Химическая формула',
        'clearance': 'Зазор',
        'coating': 'Покрытие',
        'coating_thickness_mkm': 'Толщина покрытия (мкм)',
        'code_internal': 'Внутренний код',
        'coil_weight_kg': 'Вес бухты (кг)',
        'colors': 'Цвета',
        'conversion_factor': 'Коэффициент пересчёта',
        'core_diameter_mm': 'Диаметр сердечника (мм)',
        'corrosion_protection': 'Защита от коррозии',
        'corrosion_resistance': 'Коррозионная стойкость',
        'curing_time_min_130C': 'Время отверждения при 130°C (мин)',
        'current_type': 'Тип тока',
        'deck_board_thickness_mm': 'Толщина доски настила (мм)',
        'density_kg_l_20C': 'Плотность (кг/л при 20°C)',
        'dimensions_mm': 'Размеры (мм)',
        'drive_type': 'Тип привода',
        'drying_time_hours_105C': 'Время высыхания при 105°C (ч)',
        'drying_time_hours_150C': 'Время высыхания при 150°C (ч)',
        'drying_time_hours_180C': 'Время высыхания при 180°C (ч)',
        'drying_time_hours_20C': 'Время высыхания при 20°C (ч)',
        'dynamic_load_rating_kn': 'Динамическая грузоподъёмность (кН)',
        'edge_crush_strength_n_mm': 'Прочность кромки на сжатие (Н/мм)',
        'elasticity_mm': 'Эластичность (мм)',
        'electrical_strength_kv_mm': 'Электрическая прочность (кВ/мм)',
        'evaporation_percent_100C_1h': 'Испаряемость при 100°C за 1ч (%)',
        'evaporation_percent_120C_1h': 'Испаряемость при 120°C за 1ч (%)',
        'fatigue_load_limit_kn': 'Предел усталостной нагрузки (кН)',
        'fill_factor_percent': 'Коэффициент заполнения (%)',
        'flexibility_mm': 'Гибкость (мм)',
        'flute_type': 'Тип флейты',
        'fork_entry': 'Заход вилки',
        'free_acid_mg_koh_g': 'Свободная кислота (мг КОН/г)',
        'free_alkali_mg_koh_g': 'Свободная щёлочь (мг КОН/г)',
        'gloss_percent': 'Глянец (%)',
        'grain_size': 'Размер зерна',
        'grease_type': 'Тип смазки',
        'h': 'Высота',
        'hardness_grade': 'Класс твёрдости',
        'hardness_hrc': 'Твёрдость (HRC)',
        'hardness_hv': 'Твёрдость (HV)',
        'hazard_class': 'Класс опасности',
        'head_diameter_mm': 'Диаметр головки (мм)',
        'head_height_mm': 'Высота головки (мм)',
        'head_size_mm': 'Размер головки (мм)',
        'head_type': 'Тип головки',
        'heat_resistance_c': 'Термостойкость (°C)',
        'heat_resistance_hours_200C': 'Термостойкость при 200°C (ч)',
        'height': 'Высота',
        'height_mm': 'Высота (мм)',
        'id': 'ID',
        'impact_strength_cm': 'Ударная вязкость (см)',
        'impregnation': 'Пропитка',
        'inner_diameter_mm': 'Внутренний диаметр (мм)',
        'insulation_coating': 'Изоляционное покрытие',
        'is_critical': 'Критичный',
        'is_full_thread': 'Полная резьба',
        'is_hazardous': 'Опасный',
        'l': 'Длина',
        'lamination_factor': 'Коэффициент ламинирования',
        'length': 'Длина',
        'length_m_roll': 'Длина в рулоне (м)',
        'level': 'Уровень',
        'limiting_speed_rpm': 'Предельная скорость (об/мин)',
        'manufacturer': 'Производитель',
        'marking': 'Маркировка',
        'marking_method': 'Способ маркировки',
        'material': 'Материал',
        'materials': 'Материалы',
        'max_load_kg': 'Максимальная нагрузка (кг)',
        'max_speed_m_s': 'Максимальная скорость (м/с)',
        'max_speed_rpm': 'Максимальная скорость (об/мин)',
        'nail_type': 'Тип гвоздя',
        'name_en': 'Название (англ.)',
        'name_full': 'Полное название',
        'name_ru': 'Название (рус.)',
        'name_short': 'Краткое название',
        'nominal_diameter_mm': 'Номинальный диаметр (мм)',
        'nut_type': 'Тип гайки',
        'outer_diameter': 'Внешний диаметр',
        'outer_diameter_mm': 'Внешний диаметр (мм)',
        'packaging': 'Упаковка',
        'packaging_standard': 'Стандарт упаковки',
        'parent_id': 'ID родителя',
        'penetration_0_1mm_25C': 'Пенетрация (0,1 мм при 25°C)',
        'ph_working_solution': 'pH рабочего раствора',
        'pigment': 'Пигмент',
        'point_angle_deg': 'Угол заточки (°)',
        'proof_load_mpa': 'Доказательная нагрузка (МПа)',
        'puncture_resistance_n': 'Прочность на прокол (Н)',
        'purity_percent': 'Чистота (%)',
        'reference_speed_rpm': 'Номинальная скорость (об/мин)',
        'requires_batch_tracking': 'Требуется отслеживание партий',
        'requires_cert': 'Требуется сертификат',
        'salt_fog_resistance_hours': 'Стойкость к солевому туману (ч)',
        'seal_type': 'Тип уплотнения',
        'series': 'Серия',
        'shank_type': 'Тип стержня',
        'solvent': 'Растворитель',
        'specifications': 'Характеристики',
        'spring_force_n': 'Сила пружины (Н)',
        'stability_hours': 'Стабильность (ч)',
        'static_load_rating_kn': 'Статическая грузоподъёмность (кН)',
        'storage_condition': 'Условия хранения',
        'strength_class': 'Класс прочности',
        'stretch_percent': 'Удлинение (%)',
        'structure': 'Структура',
        'subcategories': 'Подкатегории',
        'sulfur_content_percent': 'Содержание серы (%)',
        'surface_finish': 'Отделка поверхности',
        'temperature_range_c': 'Температурный диапазон (°C)',
        'tensile_strength_n_cm': 'Прочность на растяжение (Н/см)',
        'thickness': 'Толщина',
        'thickness_mkm': 'Толщина (мкм)',
        'thread_diameter_mm': 'Диаметр резьбы (мм)',
        'thread_length_mm': 'Длина резьбы (мм)',
        'thread_pitch_mm': 'Шаг резьбы (мм)',
        'tolerance_class': 'Класс допуска',
        'transparency_percent': 'Прозрачность (%)',
        'viscosity_cst_50C': 'Кинематическая вязкость (сСт при 50°C)',
        'viscosity_sec_20C': 'Вязкость (сек при 20°C)',
        'volume_resistivity_ohm_m': 'Объёмное удельное сопротивление (Ом·м)',
        'w': 'Ширина',
        'washer_type': 'Тип шайбы',
        'water_absorption_percent': 'Водопоглощение (%)',
        'water_resistance': 'Водостойкость',
        'water_resistance_hours': 'Водостойкость (ч)',
        'water_solubility': 'Растворимость в воде',
        'weave_type': 'Тип плетения',
        'weight_g_m2': 'Вес (г/м²)',
        'weight_kg': 'Вес (кг)',
        'weight_per_roll_kg': 'Вес рулона (кг)',
        'welded_material': 'Сварочный материал',
        'welding_position': 'Сварочная позиция',
        'width': 'Ширина',
        'working_concentration_percent': 'Рабочая концентрация (%)',
        'wrench_size_mm': 'Размер ключа (мм)'
    };
    
    let specsHtml = '';
    for (const [key, value] of Object.entries(specs)) {
        let displayValue = value;
        if (Array.isArray(value)) {
            displayValue = value.join(', ');
        } else if (typeof value === 'object') {
            // Для объектов (как химический состав) форматируем красиво
            const objEntries = Object.entries(value).map(([k, v]) => `${k}: ${v}`).join('; ');
            displayValue = objEntries;
        }
        
        // Получаем русское название или оставляем как есть (преобразуя _)
        const label = specLabels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        
        // Если это standard_doc, делаем его кликабельным
        if (key === 'standard_doc' && typeof value === 'string' && value.includes('ГОСТ')) {
            const gostNumber = extractGostNumber(value);
            const gostLink = generateGostLink(value);
            specsHtml += `
                <div class="spec-item">
                    <span class="spec-item-label">${label}</span>
                    <span class="spec-item-value">
                        <a href="${gostLink}" target="_blank" class="gost-link" title="Открыть документ ГОСТ">
                            ${escapeHtml(value)} ↗
                        </a>
                    </span>
                </div>
            `;
        } else {
            specsHtml += `
                <div class="spec-item">
                    <span class="spec-item-label">${label}</span>
                    <span class="spec-item-value">${escapeHtml(displayValue)}</span>
                </div>
            `;
        }
    }
    
    bodyEl.innerHTML = `
        <div class="modal-section">
            <div class="modal-section-title">📋 Общая информация</div>
            <div class="specs-list">
                <div class="spec-item">
                    <span class="spec-item-label">Внутренний код</span>
                    <span class="spec-item-value"><code>${escapeHtml(material.code_internal)}</code></span>
                </div>
                <div class="spec-item">
                    <span class="spec-item-label">Краткое название</span>
                    <span class="spec-item-value">${escapeHtml(material.name_short)}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-item-label">Категория</span>
                    <span class="spec-item-value">${escapeHtml(material.parent_category?.name_ru || '')} → ${escapeHtml(material.subcategory?.name_ru || '')}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-item-label">Единица измерения</span>
                    <span class="spec-item-value">${escapeHtml(material.base_unit)}</span>
                </div>
                ${material.alt_unit ? `
                <div class="spec-item">
                    <span class="spec-item-label">Альт. единица</span>
                    <span class="spec-item-value">${escapeHtml(material.alt_unit)} (коэф. ${material.conversion_factor})</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="modal-section">
            <div class="modal-section-title">⚙️ Характеристики</div>
            <div class="specs-list">
                ${specsHtml}
            </div>
        </div>
        
        <div class="modal-section">
            <div class="modal-section-title">📌 Дополнительная информация</div>
            <div class="specs-list">
                <div class="spec-item">
                    <span class="spec-item-label">Ответственный материал</span>
                    <span class="spec-item-value">${material.is_critical ? '✅ Да' : '❌ Нет'}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-item-label">Требуется сертификат</span>
                    <span class="spec-item-value">${material.requires_cert ? '✅ Да' : '❌ Нет'}</span>
                </div>
                <div class="spec-item" style="grid-column: 1 / -1;">
                    <span class="spec-item-label">Условия хранения</span>
                    <span class="spec-item-value">${escapeHtml(material.storage_condition || '—')}</span>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMaterialModal(event) {
    if (event && event.target !== event.currentTarget) return;
    
    const modal = document.getElementById('materialModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentMaterial = null;
}

function printMaterial() {
    if (!currentMaterial) return;
    
    // Словарь перевода ключей характеристик на русский язык (для печати)
    const specLabels = {
        'material_grade': 'Марка материала',
        'standard_doc': 'Нормативный документ',
        'product_form': 'Форма изделия',
        'diameter_mm': 'Диаметр (мм)',
        'length_m': 'Длина (м)',
        'thickness_mm': 'Толщина (мм)',
        'width_mm': 'Ширина (мм)',
        'length_mm': 'Длина (мм)',
        'treatment': 'Обработка',
        'density_kg_m3': 'Плотность (кг/м³)',
        'tensile_strength_mpa': 'Временное сопротивление (МПа)',
        'yield_strength_mpa': 'Предел текучести (МПа)',
        'alloy_elements': 'Легирующие элементы',
        'weight_per_piece_kg': 'Вес одной штуки (кг)',
        'hardness_hb': 'Твёрдость (HB)',
        'chemical_composition': 'Химический состав',
        'application': 'Применение',
        'elongation_percent': 'Относительное удлинение (%)',
        'product_type': 'Тип продукта',
        'mark': 'Марка',
        'conductor_material': 'Материал проводника',
        'conductor_diameter_mm': 'Диаметр проводника (мм)',
        'insulation_type': 'Тип изоляции',
        'thermal_class': 'Класс нагревостойкости',
        'voltage_test_v': 'Испытательное напряжение (В)',
        'elongation_percent_min': 'Мин. относительное удлинение (%)',
        'adherence_test': 'Испытание на адгезию',
        'resistance_ohm_km_20C': 'Сопротивление (Ом/км при 20°C)',
        'heat_shock_resistance': 'Термостойкость',
        'chemical_resistance': 'Химическая стойкость',
        'electrical_resistivity_ohm_mm2_m': 'Удельное электросопротивление (Ом·мм²/м)',
        'viscosity_sec_20C': 'Вязкость (сек при 20°C)',
        'drying_time_hours_105C': 'Время высыхания при 105°C (ч)',
        'drying_time_hours_150C': 'Время высыхания при 150°C (ч)',
        'drying_time_hours_180C': 'Время высыхания при 180°C (ч)',
        'drying_time_hours_20C': 'Время высыхания при 20°C (ч)',
        'electrical_strength_kv_mm': 'Электрическая прочность (кВ/мм)',
        'volume_resistivity_ohm_m': 'Объёмное удельное сопротивление (Ом·м)',
        'adhesion_grade': 'Класс адгезии',
        'flexibility_mm': 'Гибкость (мм)',
        'viscosity_cst_50C': 'Кинематическая вязкость (сСт при 50°C)',
        'packaging': 'Упаковка',
        'appearance': 'Внешний вид',
        'color': 'Цвет',
        'density_kg_l_20C': 'Плотность (кг/л при 20°C)',
        'solid_content_percent': 'Содержание сухого вещества (%)',
        'flash_point_c': 'Температура вспышки (°C)',
        'ph_working_solution': 'pH рабочего раствора',
        'water_resistance': 'Водостойкость',
        'water_resistance_hours': 'Водостойкость (ч)',
        'corrosion_resistance': 'Коррозионная стойкость',
        'heat_resistance_c': 'Термостойкость (°C)',
        'strength_class': 'Класс прочности',
        'hardness_hrc': 'Твёрдость (HRC)',
        'hardness_hv': 'Твёрдость (HV)',
        'impact_strength_cm': 'Ударная вязкость (см)',
        'elongation_percent': 'Относительное удлинение (%)',
        'temperature_range_c': 'Температурный диапазон (°C)',
        'storage_condition': 'Условия хранения',
        'shelf_life_months': 'Срок хранения (мес)',
        'manufacturer': 'Производитель'
    };
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${currentMaterial.name_full}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; }
                h1 { font-size: 20px; margin-bottom: 20px; }
                .section { margin-bottom: 20px; }
                .section-title { font-weight: bold; border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 12px; }
                .spec-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                .spec-label { color: #666; }
                .spec-value { font-weight: 500; }
                code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
            </style>
        </head>
        <body>
            <h1>${currentMaterial.name_full}</h1>
            <p><strong>Код:</strong> <code>${currentMaterial.code_internal}</code></p>
            <p><strong>Краткое название:</strong> ${currentMaterial.name_short}</p>
            
            <div class="section">
                <div class="section-title">Характеристики</div>
                ${Object.entries(currentMaterial.specifications || {}).map(([key, value]) => {
                    const label = specLabels[key] || key.replace(/_/g, ' ');
                    const displayValue = Array.isArray(value) ? value.join(', ') : (typeof value === 'object' ? Object.entries(value).map(([k, v]) => `${k}: ${v}`).join('; ') : value);
                    return `
                    <div class="spec-row">
                        <span class="spec-label">${label}</span>
                        <span class="spec-value">${displayValue}</span>
                    </div>`;
                }).join('')}
            </div>
            
            <div class="section">
                <div class="section-title">Дополнительно</div>
                <div class="spec-row">
                    <span class="spec-label">Ответственный</span>
                    <span class="spec-value">${currentMaterial.is_critical ? 'Да' : 'Нет'}</span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">Сертификат</span>
                    <span class="spec-value">${currentMaterial.requires_cert ? 'Требуется' : 'Не требуется'}</span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">Условия хранения</span>
                    <span class="spec-value">${currentMaterial.storage_condition || '—'}</span>
                </div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Функция извлечения номера ГОСТ из строки стандарта
function extractGostNumber(gostString) {
    // Удаляем "ГОСТ " и всё после года (если есть)
    const match = gostString.match(/ГОСТ\s*([0-9.]+)(?:-([0-9]+))?/i);
    if (match) {
        return match[1] + (match[2] ? '-' + match[2] : '');
    }
    return null;
}

// Функция генерации ссылки на ГОСТ
function generateGostLink(gostString) {
    const gostNumber = extractGostNumber(gostString);
    if (!gostNumber) {
        return '#';
    }
    
    // Путь к локальным файлам ГОСТ
    const localPath = '<?= APP_URL ?>/assets/gosts/gost_' + gostNumber + '.pdf';
    
    // Проверяем существование локального файла через AJAX
    // Если файл существует - открываем локально, иначе - внешний ресурс
    return localPath;
}

// Закрытие модального окна по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMaterialModal();
    }
});
</script>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
