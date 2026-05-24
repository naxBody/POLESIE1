<?php
/**
 * API для работы с серийными номерами
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';

try {
    if ($action === 'get') {
        // Получение данных о серийном номере
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            throw new Exception('Не указан ID');
        }
        
        $stmt = $pdo->prepare("
            SELECT sn.*, p.name as product_name, p.article as product_article
            FROM product_serial_numbers sn
            JOIN products p ON sn.product_id = p.id
            WHERE sn.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            throw new Exception('Серийный номер не найден');
        }
        
        echo json_encode($data);
        
    } elseif ($action === 'generate') {
        // Генерация нового серийного номера для продукта
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            throw new Exception('Не указан продукт');
        }
        
        // Получаем данные о продукте
        $stmt = $pdo->prepare("SELECT name, article FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception('Продукт не найден');
        }
        
        // Генерируем префикс из первых букв названия на английском
        $prefix = generateSerialPrefix($product['name'], $product['article']);
        
        // Получаем следующий порядковый номер для этого префикса
        $stmt = $pdo->prepare("
            SELECT serial_number 
            FROM product_serial_numbers 
            WHERE serial_number LIKE ?
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $lastSerial = $stmt->fetchColumn();
        
        if ($lastSerial) {
            // Извлекаем номер из последнего серийного номера
            $lastNumber = (int)substr($lastSerial, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        $newSerialNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        echo json_encode([
            'success' => true,
            'serial_number' => $newSerialNumber,
            'prefix' => $prefix,
            'number' => $nextNumber,
            'product_name' => $product['name'],
            'product_article' => $product['article']
        ]);
        
    } elseif ($action === 'check') {
        // Проверка уникальности серийного номера
        $serialNumber = trim($_GET['serial_number'] ?? '');
        if (!$serialNumber) {
            throw new Exception('Не указан серийный номер');
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_serial_numbers WHERE serial_number = ?");
        $stmt->execute([$serialNumber]);
        $count = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'exists' => $count > 0,
            'serial_number' => $serialNumber
        ]);
        
    } else {
        throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * Генерация префикса серийного номера из названия продукта
 * 
 * @param string $name Название продукта
 * @param string $article Артикул продукта
 * @return string Префикс (первые 2-4 буквы английскими)
 */
function generateSerialPrefix($name, $article) {
    // Если есть артикул, используем его как основу
    if (!empty($article)) {
        // Берем первые 2-3 символа артикула (обычно они уже латиницей)
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $article), 0, 3));
        if (strlen($prefix) >= 2) {
            return $prefix;
        }
    }
    
    // Транслитерация названия на английский
    $transliterated = transliterateToLatin($name);
    
    // Берем первые буквы каждого слова или первые 3-4 буквы
    $words = preg_split('/[\s\-_]+/', $transliterated);
    $prefix = '';
    
    if (count($words) >= 2) {
        // Берем первые буквы первых двух слов
        foreach ($words as $word) {
            if (!empty($word)) {
                $prefix .= strtoupper($word[0]);
                if (strlen($prefix) >= 3) {
                    break;
                }
            }
        }
    }
    
    // Если получилось меньше 2 символов, берем первые 3-4 буквы названия
    if (strlen($prefix) < 2) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $transliterated), 0, 4));
    }
    
    return substr($prefix, 0, 4); // Максимум 4 символа
}

/**
 * Транслитерация русского текста в латиницу
 * 
 * @param string $text Исходный текст
 * @return string Транслитерированный текст
 */
function transliterateToLatin($text) {
    $converter = [
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
        
        'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
        'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
        'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
        'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
        'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
        'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
        'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
    ];
    
    $result = strtr($text, $converter);
    $result = preg_replace('/[^A-Za-z0-9\s\-_]/', '', $result);
    
    return $result;
}
