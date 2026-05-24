<?php
/**
 * Скрипт для генерации списка всех уникальных ГОСТов из list_materials.json
 * Запуск: php generate_gost_list.php
 */

$jsonPath = __DIR__ . '/../../../list_materials.json';
$outputPath = __DIR__ . '/gost_list.txt';

if (!file_exists($jsonPath)) {
    echo "Ошибка: файл $jsonPath не найден\n";
    exit(1);
}

$jsonData = file_get_contents($jsonPath);
$data = json_decode($jsonData, true);

$gosts = [];

// Рекурсивный обход всех материалов
function extractGosts($categories, &$gosts) {
    foreach ($categories as $category) {
        if (isset($category['subcategories'])) {
            foreach ($category['subcategories'] as $subcategory) {
                if (isset($subcategory['materials'])) {
                    foreach ($subcategory['materials'] as $material) {
                        if (isset($material['specifications']['standard_doc'])) {
                            $gosts[] = $material['specifications']['standard_doc'];
                        }
                    }
                }
            }
        }
    }
}

extractGosts($data['categories'] ?? [], $gosts);

// Уникальные ГОСТы
$uniqueGosts = array_unique($gosts);
sort($uniqueGosts);

// Генерация отчёта
$report = "# Список ГОСТов для скачивания\n\n";
$report .= "Всего уникальных стандартов: " . count($uniqueGosts) . "\n\n";
$report .= "## Список:\n\n";

foreach ($uniqueGosts as $gost) {
    // Извлекаем номер и год
    preg_match('/ГОСТ\s*([0-9.]+)(?:-([0-9]{2,4}))?/', $gost, $matches);
    if ($matches) {
        $number = str_replace('.', '-', $matches[1]);
        $year = $matches[2] ?? '';
        $filename = "GOST-{$number}" . ($year ? "-{$year}" : "") . ".pdf";
        $report .= "- [ ] `{$gost}` → `{$filename}`\n";
    } else {
        $report .= "- [ ] `{$gost}` (не удалось распознать формат)\n";
    }
}

file_put_contents($outputPath, $report);

echo "Список ГОСТов сохранён в: {$outputPath}\n";
echo "Найдено уникальных стандартов: " . count($uniqueGosts) . "\n";
