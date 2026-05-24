<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$pdo = getDbConnection();

if (!isset($_GET['id'])) {
    die("Не указан ID продукта");
}

$product_id = $_GET['id'];
$serial_number = $_GET['serial'] ?? null;

// Проверяем, является ли ID числом (из БД) или строкой (из JSON)
$isJsonProduct = !is_numeric($product_id);

// Загружаем production.json для данных организации и продуктов
$jsonPath = dirname(BASE_PATH) . '/production.json';
if (!file_exists($jsonPath)) {
    $jsonPath = BASE_PATH . '/production.json';
}

$productionData = [];
if (file_exists($jsonPath)) {
    $jsonData = file_get_contents($jsonPath);
    $productionData = json_decode($jsonData, true);
}

$product = null;

if ($isJsonProduct) {
    // Продукт из JSON-каталога - ищем данные в production.json
    $categories = $productionData['categories'] ?? [];
    
    // Ищем продукт по ID
    $found = false;
    foreach ($categories as $category) {
        if ($found) break;
        if (isset($category['subcategories'])) {
            foreach ($category['subcategories'] as $subcategory) {
                if ($found) break;
                if (isset($subcategory['products'])) {
                    foreach ($subcategory['products'] as $prod) {
                        // Генерируем ID так же как в JavaScript (djb2 алгоритм)
                        $hash = 5381;
                        $sku = $prod['sku'] ?? '';
                        for ($i = 0; $i < strlen($sku); $i++) {
                            $hash = (($hash << 5) + $hash) + ord($sku[$i]);
                            // Преобразуем в 32-битное знаковое целое
                            $hash = $hash & 0xFFFFFFFF;
                        }
                        $expectedId = 'PROD_' . abs((int)$hash);
                        
                        // Сравниваем с SKU или сгенерированным ID
                        if (($prod['sku'] ?? '') === $product_id || $expectedId === $product_id) {
                            $product = [
                                'id' => $prod['id'] ?? $expectedId,
                                'name' => $prod['name_full'] ?? $prod['name_short'] ?? 'Изделие',
                                'article' => $prod['sku'] ?? '',
                                'category_name' => ($category['name_ru'] ?? '') . ' / ' . ($subcategory['name_ru'] ?? ''),
                                'description' => '',
                                'specifications' => json_encode($prod['specs'] ?? []),
                                'manual_file' => '',
                                'status' => 'В эксплуатации',
                                'warranty_months' => $prod['warranty_months'] ?? 12,
                                'code_gost' => $prod['code_gost'] ?? '',
                                'sku' => $prod['sku'] ?? ''
                            ];
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    if (!$product) {
        // Если не найдено в JSON, создаём заглушку
        $product = [
            'id' => $product_id,
            'name' => 'Изделие #' . $product_id,
            'article' => '',
            'category_name' => '',
            'description' => '',
            'specifications' => '{}',
            'manual_file' => '',
            'status' => 'В эксплуатации',
            'warranty_months' => 12
        ];
    }
} else {
    // Продукт из таблицы products (числовой ID)
    $product_id = (int)$product_id;
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN product_categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        // Если в таблице products не найдено, создаём заглушку
        $product = [
            'id' => $product_id,
            'name' => 'Изделие #' . $product_id,
            'article' => '',
            'category_name' => '',
            'description' => '',
            'specifications' => '{}',
            'manual_file' => '',
            'status' => 'В эксплуатации',
            'warranty_months' => 12
        ];
    }
}

// Получаем данные серийного номера из БД
// Если serial_number передан и не пустой, ищем по нему, иначе берём последний для продукта
$serial_data = null;

if ($isJsonProduct) {
    // Для JSON-продуктов используем SKU как product_id в БД
    $skuProductId = $product['sku'] ?? $product_id;
    
    if ($serial_number && $serial_number !== 'Не присвоен') {
        $stmt = $pdo->prepare("SELECT * FROM product_serial_numbers WHERE product_id = ? AND serial_number = ?");
        $stmt->execute([$skuProductId, $serial_number]);
        $serial_data = $stmt->fetch();
    }
    
    if (!$serial_data) {
        // Берём последний активный серийный номер для продукта
        $stmt = $pdo->prepare("SELECT * FROM product_serial_numbers WHERE product_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$skuProductId]);
        $serial_data = $stmt->fetch();
    }
} else {
    // Для продуктов из БД (числовой ID)
    if ($serial_number && $serial_number !== 'Не присвоен') {
        $stmt = $pdo->prepare("SELECT * FROM product_serial_numbers WHERE product_id = ? AND serial_number = ?");
        $stmt->execute([$product_id, $serial_number]);
        $serial_data = $stmt->fetch();
    }
    
    if (!$serial_data) {
        // Берём последний активный серийный номер для продукта
        $stmt = $pdo->prepare("SELECT * FROM product_serial_numbers WHERE product_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$product_id]);
        $serial_data = $stmt->fetch();
    }
}

// Если серийный номер всё ещё не найден, используем переданный параметр или генерируем
if (!$serial_data) {
    if (!$serial_number || $serial_number === 'Не присвоен') {
        // Генерируем новый серийный номер
        $date = new DateTime();
        $year = $date->format('y');
        $month = $date->format('m');
        $randomPart = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        // Используем SKU или ID для серийного номера
        $serialBase = $product['sku'] ?? $product_id;
        $serial_number = 'SN-' . $year . $month . '-' . $serialBase . '-' . $randomPart;
    }
    $manufacture_date = date('Y-m-d');
    $warranty_start = date('Y-m-d');
    $warranty_months = $product['warranty_months'] ?? 12;
    $warranty_end = date('Y-m-d', strtotime('+' . $warranty_months . ' months'));
    
    $serial_data = [
        'serial_number' => $serial_number,
        'manufacture_date' => $manufacture_date,
        'warranty_start' => $warranty_start,
        'warranty_end' => $warranty_end,
        'warranty_months' => $warranty_months,
        'warranty_period' => $warranty_months . ' месяцев'
    ];
}

// Получаем документы для серийного номера
$documents = [];
$serial_number_id = $serial_data['id'] ?? null;
if ($serial_number_id) {
    $stmt = $pdo->prepare("SELECT * FROM product_documents WHERE serial_number_id = ? ORDER BY document_type, uploaded_at");
    $stmt->execute([$serial_number_id]);
    $documents = $stmt->fetchAll();
}

// Получаем спецификации
$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];

// Получаем данные организации из JSON или используем значения по умолчанию
$company_name = $productionData['company']['name'] ?? "ОАО «Полесьеэлектромаш»";
$company_address = $productionData['company']['address'] ?? "225644, Брестская область, г. Лунинец, ул. Красная, 179";
$company_phone = $productionData['company']['phone'] ?? "+375 1647 2-78-09";
$company_email = $productionData['company']['email'] ?? "polesie@polesieelectromash.by";

// Получаем динамические данные паспорта если они есть
$dynamic_passport_data = null;
if ($serial_number_id) {
    $dynStmt = $pdo->prepare("SELECT * FROM passport_dynamic_data WHERE serial_number_id = ?");
    $dynStmt->execute([$serial_number_id]);
    $dynamic_passport_data = $dynStmt->fetch();
}

// Загружаем шаблоны разделов из БД
$templateSections = [];
try {
    $tplStmt = $pdo->query("SELECT * FROM passport_templates WHERE is_active = 1 ORDER BY sort_order");
    $templateSections = $tplStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Если таблица ещё не создана, используем стандартные разделы
    $templateSections = [];
}

// Если есть динамические данные, используем их вместо стандартных
if ($dynamic_passport_data) {
    // Переопределяем данные компании если заданы в паспорте
    if (!empty($dynamic_passport_data['company_name'])) {
        $company_name = $dynamic_passport_data['company_name'];
    }
    if (!empty($dynamic_passport_data['company_address'])) {
        $company_address = $dynamic_passport_data['company_address'];
    }
    if (!empty($dynamic_passport_data['company_phone'])) {
        $company_phone = $dynamic_passport_data['company_phone'];
    }
    if (!empty($dynamic_passport_data['company_email'])) {
        $company_email = $dynamic_passport_data['company_email'];
    }
    
    // Используем кастомное имя продукта если задано
    if (!empty($dynamic_passport_data['product_name_custom'])) {
        $product['name'] = $dynamic_passport_data['product_name_custom'];
    }
}

// Дата выпуска - приоритет у динамических данных
$manufacture_date = $dynamic_passport_data['manufacture_date'] ?? $serial_data['manufacture_date'] ?? date('Y-m-d');
$release_date = date('d.m.Y', strtotime($manufacture_date));
$warranty_start = $dynamic_passport_data['warranty_start'] ?? $serial_data['warranty_start'] ?? date('d.m.Y');
$warranty_end = $dynamic_passport_data['warranty_end'] ?? $serial_data['warranty_end'] ?? date('d.m.Y', strtotime('+1 year'));
$warranty_period = $dynamic_passport_data['warranty_period'] ?? $serial_data['warranty_period'] ?? ($dynamic_passport_data['warranty_months'] ?? $serial_data['warranty_months'] ?? 12) . ' месяцев';

// Форматируем даты для отображения
$warranty_start_display = is_string($warranty_start) ? $warranty_start : date('d.m.Y', strtotime($warranty_start));
$warranty_end_display = is_string($warranty_end) ? $warranty_end : date('d.m.Y', strtotime($warranty_end));

// Генерируем HTML для печати
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Паспорт изделия - <?= htmlspecialchars($product['name']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #fff;
        }
        
        .passport-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }
        
        .passport-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 10pt;
            color: #333;
        }
        
        .passport-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
        }
        
        .serial-block {
            border: 2px solid #000;
            padding: 10px;
            text-align: center;
            margin: 20px 0;
            background: #f9f9f9;
        }
        
        .serial-label {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .serial-number {
            font-size: 16pt;
            font-weight: bold;
            font-family: "Courier New", monospace;
            letter-spacing: 2px;
        }
        
        .section {
            margin: 20px 0;
        }
        
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .info-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        
        .info-label {
            font-weight: bold;
            width: 40%;
            color: #333;
        }
        
        .info-value {
            width: 60%;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .specs-table td, .specs-table th {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        .specs-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .description {
            text-align: justify;
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #000;
        }
        
        .documents-list {
            list-style: none;
            margin: 10px 0;
        }
        
        .documents-list li {
            padding: 5px 0;
            border-bottom: 1px dashed #ccc;
        }
        
        .signatures {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            gap: 50px;
        }
        
        .signature-block {
            flex: 1;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        
        .signature-label {
            font-size: 10pt;
            color: #666;
            margin-bottom: 30px;
        }
        
        .signature-line {
            height: 30px;
            border-bottom: 1px solid #000;
            margin-top: 5px;
        }
        
        .stamp-placeholder {
            width: 100px;
            height: 100px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 10pt;
            text-align: center;
            margin: 20px auto;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        @media print {
            body {
                background: #fff;
            }
            
            .no-print {
                display: none;
            }
            
            .passport-container {
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="passport-container">
        <!-- Шапка -->
        <div class="passport-header">
            <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
            <div class="company-info">
                <?= htmlspecialchars($company_address) ?> | Тел.: <?= htmlspecialchars($company_phone) ?> | E-mail: <?= htmlspecialchars($company_email) ?>
            </div>
        </div>
        
        <!-- Заголовок -->
        <div class="passport-title">ПАСПОРТ ИЗДЕЛИЯ</div>
        
        <!-- Серийный номер -->
        <div class="serial-block">
            <div class="serial-label">Заводской номер изделия</div>
            <div class="serial-number"><?= htmlspecialchars($serial_number) ?></div>
            <div style="font-size: 9pt; color: #666; margin-top: 5px;">Присвоен <?= $release_date ?></div>
        </div>
        
        <!-- Динамические разделы из шаблонов БД -->
        <?php if (!empty($templateSections)): ?>
            <?php 
            // Функция для замены плейсхолдеров на данные
            function replacePlaceholders($template, $data) {
                $placeholders = [
                    '{product_name}' => htmlspecialchars($data['product']['name']),
                    '{product_model}' => htmlspecialchars($data['product']['article'] ?? ''),
                    '{serial_number}' => htmlspecialchars($data['serial_number']),
                    '{manufacture_date}' => $data['release_date'],
                    '{warranty_period}' => htmlspecialchars($data['warranty_period']),
                    '{warranty_start}' => $data['warranty_start_display'],
                    '{warranty_end}' => $data['warranty_end_display'],
                    '{org_name}' => htmlspecialchars($data['company_name']),
                    '{org_address}' => htmlspecialchars($data['company_address']),
                    '{org_phone}' => htmlspecialchars($data['company_phone']),
                    '{org_email}' => htmlspecialchars($data['company_email']),
                ];
                return str_replace(array_keys($placeholders), array_values($placeholders), $template);
            }
            
            // Генерируем строки спецификаций для шаблона
            $specsRows = '';
            if (!empty($specs)) {
                foreach ($specs as $spec) {
                    $paramName = htmlspecialchars($spec['param_name'] ?? $spec['name'] ?? '');
                    $value = htmlspecialchars($spec['value'] ?? '');
                    $unit = htmlspecialchars($spec['unit'] ?? '');
                    $specsRows .= "<tr><td>{$paramName}</td><td>{$value}</td><td>{$unit}</td></tr>";
                }
            }
            
            $templateData = [
                'product' => $product,
                'serial_number' => $serial_number,
                'release_date' => $release_date,
                'warranty_period' => $warranty_period,
                'warranty_start_display' => $warranty_start_display,
                'warranty_end_display' => $warranty_end_display,
                'company_name' => $company_name,
                'company_address' => $company_address,
                'company_phone' => $company_phone,
                'company_email' => $company_email,
            ];
            ?>
            
            <?php foreach ($templateSections as $section): ?>
                <?php 
                $sectionKey = $section['section_key'];
                $content = $section['content_template'] ?? '';
                
                // Для раздела спецификаций заменяем плейсхолдер {specs_rows}
                if ($sectionKey === 'specs' && !empty($specsRows)) {
                    $content = str_replace('{specs_rows}', $specsRows, $content);
                }
                
                // Заменяем все плейсхолдеры
                $content = replacePlaceholders($content, $templateData);
                ?>
                <div class="section">
                    <div class="section-title"><?= htmlspecialchars($section['title']) ?></div>
                    <div><?= $content ?></div>
                </div>
            <?php endforeach; ?>
        
        <?php else: ?>
        <!-- Стандартные разделы (если шаблоны не загружены) -->
            <table class="info-table">
                <tr>
                    <td class="info-label">Наименование:</td>
                    <td class="info-value" style="font-weight: bold;"><?= htmlspecialchars($product['name']) ?></td>
                </tr>
                <?php if (!empty($product['article'])): ?>
                <tr>
                    <td class="info-label">Артикул (SKU):</td>
                    <td class="info-value"><?= htmlspecialchars($product['article']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($product['code_gost'])): ?>
                <tr>
                    <td class="info-label">Обозначение по ГОСТ:</td>
                    <td class="info-value"><?= htmlspecialchars($product['code_gost']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($product['category_name'])): ?>
                <tr>
                    <td class="info-label">Категория:</td>
                    <td class="info-value"><?= htmlspecialchars($product['category_name']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="info-label">Дата изготовления:</td>
                    <td class="info-value"><?= $release_date ?></td>
                </tr>
                <tr>
                    <td class="info-label">Статус изделия:</td>
                    <td class="info-value"><?= htmlspecialchars($product['status'] ?? 'В эксплуатации') ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Технические характеристики -->
        <div class="section">
            <div class="section-title">2. ТЕХНИЧЕСКИЕ ХАРАКТЕРИСТИКИ И ПАРАМЕТРЫ</div>
            <?php if (!empty($specs)): ?>
            <table class="specs-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Наименование параметра</th>
                        <th style="width: 45%;">Значение</th>
                        <th style="width: 15%;">Ед. изм.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Форматируем технические характеристики с русскими названиями
                    $specLabels = [
                        'power_kw_min' => 'Мощность (минимальная)',
                        'power_kw_max' => 'Мощность (максимальная)',
                        'power_kw' => 'Мощность',
                        'rpm' => 'Частота вращения',
                        'shaft_height_mm' => 'Высота оси вращения',
                        'voltage_v' => 'Напряжение питания',
                        'frequency_hz' => 'Частота тока',
                        'efficiency_class' => 'Класс энергоэффективности',
                        'protection_class' => 'Степень защиты',
                        'climate_versions' => 'Климатическое исполнение',
                        'mounting_versions' => 'Исполнение по монтажу',
                        'weight_kg' => 'Масса',
                        'dimensions' => 'Габаритные размеры',
                        'insulation_class' => 'Класс изоляции',
                        'duty_cycle' => 'Режим работы',
                        'ambient_temp' => 'Температура окружающей среды',
                        'bearing_type' => 'Тип подшипников',
                        'cooling_method' => 'Способ охлаждения',
                        'noise_level' => 'Уровень шума',
                        'vibration_class' => 'Класс вибрации',
                        'ip_rating' => 'Степень защиты IP',
                        'service_factor' => 'Эксплуатационный коэффициент',
                        'starting_current' => 'Пусковой ток',
                        'starting_torque' => 'Пусковой момент',
                        'max_torque' => 'Максимальный момент',
                        'efficiency' => 'Коэффициент полезного действия (КПД)',
                        'power_factor' => 'Коэффициент мощности',
                        'winding_material' => 'Материал обмотки',
                        'frame_material' => 'Материал корпуса',
                        'housing_material' => 'Материал корпуса',
                        'shaft_material' => 'Материал вала',
                        'paint_color' => 'Цвет покрытия',
                        'terminal_box' => 'Расположение коробки выводов',
                        'cable_entry' => 'Ввод кабеля',
                        'lubrication' => 'Тип смазки',
                        'maintenance_interval' => 'Интервал технического обслуживания',
                        'explosion_protection' => 'Вид взрывозащиты'
                    ];
                    
                    $specUnits = [
                        'power_kw_min' => 'кВт',
                        'power_kw_max' => 'кВт',
                        'power_kw' => 'кВт',
                        'rpm' => 'об/мин',
                        'shaft_height_mm' => 'мм',
                        'voltage_v' => 'В',
                        'frequency_hz' => 'Гц',
                        'weight_kg' => 'кг',
                        'ambient_temp' => '°C',
                        'noise_level' => 'дБ',
                        'efficiency' => '%',
                        'power_factor' => 'cos φ'
                    ];
                    
                    foreach ($specs as $key => $value): 
                        $label = $specLabels[$key] ?? $key;
                        $unit = $specUnits[$key] ?? '';
                        
                        // Форматируем значение
                        if (is_array($value)) {
                            $displayValue = $value['value'] ?? '';
                            $displayUnit = $value['unit'] ?? $unit;
                        } elseif (is_array(json_decode($value, true))) {
                            $arr = json_decode($value, true);
                            $displayValue = implode(', ', $arr);
                            $displayUnit = $unit;
                        } else {
                            $displayValue = $value;
                            $displayUnit = $unit;
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($displayValue) ?></td>
                        <td><?= htmlspecialchars($displayUnit) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="padding: 20px; text-align: center; color: #666;">Технические характеристики загружаются из каталога продукции</p>
            <?php endif; ?>
        </div>
        
        <!-- Результаты испытаний -->
        <div class="section page-break">
            <div class="section-title">3. РЕЗУЛЬТАТЫ ИСПЫТАНИЙ</div>
            <p style="margin-bottom: 15px;">Изделие прошло приёмо-сдаточные испытания в соответствии с требованиями технической документации и действующих стандартов.</p>
            <table class="specs-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Наименование испытания</th>
                        <th style="width: 25%;">Результат</th>
                        <th style="width: 25%;">Примечание</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Проверка соответствия техническим характеристикам</td>
                        <td>Соответствует</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Измерение сопротивления изоляции обмоток</td>
                        <td>Норма</td>
                        <td>> 20 МОм</td>
                    </tr>
                    <tr>
                        <td>Испытание изоляции повышенным напряжением</td>
                        <td>Выдержало</td>
                        <td>без пробоя и перекрытия</td>
                    </tr>
                    <tr>
                        <td>Проверка работы на холостом ходу</td>
                        <td>Норма</td>
                        <td>ток холостого хода в пределах нормы</td>
                    </tr>
                    <tr>
                        <td>Проверка под нагрузкой</td>
                        <td>Норма</td>
                        <td>параметры в пределах допуска</td>
                    </tr>
                    <tr>
                        <td>Измерение уровня вибрации</td>
                        <td>Норма</td>
                        <td>класс вибрации N</td>
                    </tr>
                    <tr>
                        <td>Измерение уровня шума</td>
                        <td>Норма</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Проверка комплектности и внешнего вида</td>
                        <td>Соответствует</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 20px; padding: 15px; border: 1px solid #000; background: #f9f9f9;">
                <strong>ЗАКЛЮЧЕНИЕ:</strong> Изделие признано годным к эксплуатации и соответствует требованиям технической документации.
            </div>
        </div>
        
        <!-- Комплектность -->
        <div class="section">
            <div class="section-title">4. КОМПЛЕКТНОСТЬ</div>
            <p style="margin-bottom: 15px;">Изделие поставляется в следующей комплектности:</p>
            <table class="specs-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">№</th>
                        <th style="width: 50%;">Наименование</th>
                        <th style="width: 15%;">Кол-во</th>
                        <th style="width: 25%;">Примечание</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Изделие основное</td>
                        <td>1 шт.</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Паспорт изделия</td>
                        <td>1 экз.</td>
                        <td>настоящий документ</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Руководство по эксплуатации</td>
                        <td>1 экз.</td>
                        <td><?php echo !empty($product['manual_file']) ? 'прилагается' : 'по запросу'; ?></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Упаковка транспортная</td>
                        <td>1 шт.</td>
                        <td>коробка/обрешётка</td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Комплект запасных частей</td>
                        <td>1 компл.</td>
                        <td>согласно ведомости ЗИП</td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>Сертификат соответствия</td>
                        <td>1 экз.</td>
                        <td>копия</td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top: 15px; font-size: 10pt;"><em>Примечание: Комплект запасных частей и принадлежностей (ЗИП) поставляется отдельно согласно договору поставки.</em></p>
        </div>
        
        <!-- Сведения о производстве -->
        <div class="section page-break">
            <div class="section-title">5. СВЕДЕНИЯ О ПРОИЗВОДИТЕЛЕ</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Наименование предприятия:</td>
                    <td class="info-value" style="font-weight: bold;"><?= htmlspecialchars($company_name) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Юридический адрес:</td>
                    <td class="info-value"><?= htmlspecialchars($company_address) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Контактный телефон:</td>
                    <td class="info-value"><?= htmlspecialchars($company_phone) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Электронная почта:</td>
                    <td class="info-value"><?= htmlspecialchars($company_email) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Дата изготовления изделия:</td>
                    <td class="info-value"><?= $release_date ?></td>
                </tr>
                <tr>
                    <td class="info-label">Место производства:</td>
                    <td class="info-value">Республика Беларусь, Брестская область, г. Лунинец</td>
                </tr>
            </table>
            <div style="margin-top: 20px; padding: 15px; border-left: 3px solid #000; background: #f9f9f9;">
                <p><strong>ОАО «Полесьеэлектромаш»</strong> — ведущее предприятие Республики Беларусь по производству электродвигателей и электрооборудования.</p>
                <p style="margin-top: 10px;">Предприятие сертифицировано по системе менеджмента качества ISO 9001 и производит продукцию в соответствии с требованиями ГОСТ и международных стандартов.</p>
            </div>
        </div>
        
        <!-- Гарантия -->
        <div class="section">
            <div class="section-title">6. ГАРАНТИЙНЫЕ ОБЯЗАТЕЛЬСТВА</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Гарантийный срок:</td>
                    <td class="info-value"><?= htmlspecialchars($warranty_period) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Начало гарантии:</td>
                    <td class="info-value"><?= $warranty_start_display ?></td>
                </tr>
                <tr>
                    <td class="info-label">Окончание гарантии:</td>
                    <td class="info-value"><?= $warranty_end_display ?></td>
                </tr>
            </table>
            <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #000;">
                <p style="margin-bottom: 10px;"><strong>Гарантийные обязательства включают:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Бесплатное устранение дефектов, возникших по вине производителя</li>
                    <li>Замену неисправных узлов и деталей в течение гарантийного срока</li>
                    <li>Техническую консультацию по вопросам эксплуатации</li>
                </ul>
                <p style="margin-top: 10px;"><em>Гарантия не распространяется на дефекты, возникшие вследствие нарушения правил эксплуатации, хранения или транспортировки.</em></p>
            </div>
        </div>
        
        <!-- Меры безопасности -->
        <div class="section page-break">
            <div class="section-title">7. МЕРЫ БЕЗОПАСНОСТИ</div>
            <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; margin-bottom: 15px;">
                <strong>⚠ ВНИМАНИЕ!</strong> Перед началом эксплуатации внимательно изучите настоящий паспорт и руководство по эксплуатации.
            </div>
            <p style="margin-bottom: 10px;"><strong>Общие требования безопасности:</strong></p>
            <ol style="margin-left: 20px; margin-bottom: 15px;">
                <li>Монтаж, демонтаж и техническое обслуживание должны выполняться квалифицированным персоналом.</li>
                <li>Перед проведением любых работ необходимо отключить изделие от сети электропитания.</li>
                <li>Запрещается эксплуатация изделия с повреждённой изоляцией или корпусом.</li>
                <li>Необходимо обеспечить надёжное заземление изделия.</li>
                <li>Запрещается превышение номинальных параметров, указанных в паспорте.</li>
                <li>При работе следует использовать средства индивидуальной защиты.</li>
                <li>В помещении должна быть предусмотрена вентиляция согласно требованиям ПУЭ.</li>
            </ol>
            <p style="margin-bottom: 10px;"><strong>Требования пожарной безопасности:</strong></p>
            <ol style="margin-left: 20px;">
                <li>Изделие должно быть защищено от попадания легковоспламеняющихся веществ.</li>
                <li>Запрещается загромождать пространство вокруг изделия.</li>
                <li>Помещение должно быть оборудовано средствами пожаротушения.</li>
            </ol>
        </div>
        
        <!-- Условия хранения -->
        <div class="section">
            <div class="section-title">8. УСЛОВИЯ ХРАНЕНИЯ И ТРАНСПОРТИРОВАНИЯ</div>
            <table class="specs-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Параметр</th>
                        <th style="width: 70%;">Требования</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Температура хранения</td>
                        <td>От -50°C до +50°C</td>
                    </tr>
                    <tr>
                        <td>Влажность воздуха</td>
                        <td>Не более 80% при температуре 25°C</td>
                    </tr>
                    <tr>
                        <td>Помещение</td>
                        <td>Сухое, вентилируемое, без агрессивных паров и газов</td>
                    </tr>
                    <tr>
                        <td>Упаковка</td>
                        <td>Изделие должно храниться в заводской упаковке</td>
                    </tr>
                    <tr>
                        <td>Положение при хранении</td>
                        <td>В горизонтальном положении на ровной поверхности</td>
                    </tr>
                    <tr>
                        <td>Срок хранения</td>
                        <td>Не более 2 лет с даты изготовления без консервации</td>
                    </tr>
                    <tr>
                        <td>Транспортирование</td>
                        <td>В крытых транспортных средствах, защита от осадков и механических повреждений</td>
                    </tr>
                    <tr>
                        <td>Крепление при транспортировке</td>
                        <td>Надёжное крепление для предотвращения перемещения и ударов</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Описание -->
        <?php if (!empty($product['description'])): ?>
        <div class="section page-break">
            <div class="section-title">9. ОПИСАНИЕ ИЗДЕЛИЯ</div>
            <div class="description">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Документы -->
        <?php if (!empty($documents)): ?>
        <div class="section">
            <div class="section-title">10. КОМПЛЕКТ ДОКУМЕНТАЦИИ</div>
            <ul class="documents-list">
                <?php foreach ($documents as $doc): ?>
                <li>
                    <strong><?= htmlspecialchars($doc['document_name']) ?></strong> 
                    (<?= htmlspecialchars($doc['document_type']) ?>) 
                    <?php if (!empty($doc['notes'])): ?>
                    - <?= htmlspecialchars($doc['notes']) ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Руководство по эксплуатации -->
        <?php if (!empty($product['manual_file'])): ?>
        <div class="section">
            <div class="section-title">11. РУКОВОДСТВО ПО ЭКСПЛУАТАЦИИ</div>
            <p>Руководство по эксплуатации прилагается к изделию.</p>
            <p style="margin-top: 10px;">
                <em>Файл: <?= htmlspecialchars(basename($product['manual_file'])) ?></em>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Подписи и штамп ОТК -->
        <div class="section page-break">
            <div class="section-title">12. ОТМЕТКИ О ВЫДАЧЕ И ПРИЁМКЕ</div>
            
            <!-- Штамп ОТК -->
            <div style="margin: 30px 0; padding: 20px; border: 3px double #000; text-align: center;">
                <div style="font-size: 14pt; font-weight: bold; margin-bottom: 15px;">ОТК - ИЗДЕЛИЕ ГОДНО</div>
                <div style="display: flex; justify-content: space-around; align-items: flex-end;">
                    <div style="text-align: left;">
                        <div style="margin-bottom: 30px;">Дата приёмки: «___» __________ 20__ г.</div>
                        <div>Нормоконтроль: _________________</div>
                    </div>
                    <div style="width: 120px; height: 120px; border: 2px solid #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11pt; font-weight: bold; color: #000;">
                        ОТК<br>ОАО «Полесьеэлектромаш»<br>г. Лунинец
                    </div>
                    <div style="text-align: right;">
                        <div style="margin-bottom: 30px;">Подпись ответственного лица</div>
                        <div>_________________ / _________________</div>
                        <div style="font-size: 9pt; color: #666;">(подпись) (ФИО)</div>
                    </div>
                </div>
            </div>
            
            <!-- Подписи о выдаче -->
            <div class="signatures">
                <div class="signature-block">
                    <div class="signature-label">Изделие принял(а):</div>
                    <div class="signature-line"></div>
                    <div style="font-size: 9pt; color: #666; margin-top: 5px;">(подпись) ___________________ / (ФИО) ___________________</div>
                    <div style="font-size: 9pt; color: #666; margin-top: 5px;">Дата: «___» __________ 20__ г.</div>
                </div>
                <div class="signature-block">
                    <div class="signature-label">Изделие передал(а):</div>
                    <div class="signature-line"></div>
                    <div style="font-size: 9pt; color: #666; margin-top: 5px;">(подпись) ___________________ / (ФИО) ___________________</div>
                    <div style="font-size: 9pt; color: #666; margin-top: 5px;">Дата: «___» __________ 20__ г.</div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Документ сформирован автоматически <?= date('d.m.Y H:i') ?> | Паспорт действителен в течение всего срока службы изделия
        </div>
    </div>
    <?php endif; // Конец условия для стандартных разделов ?>
    
    <script>
        // Автоматическая печать при загрузке (опционально)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
