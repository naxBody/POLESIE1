<?php
/**
 * API для получения данных о продукте (серийные номера, документы)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Product ID not provided']);
    exit;
}

$pdo = getDbConnection();

// Получаем последний активный серийный номер для продукта
$stmt = $pdo->prepare("
    SELECT sn.*, p.warranty_months, pd.file_path as manual_path
    FROM product_serial_numbers sn
    LEFT JOIN products p ON sn.product_id = p.id
    LEFT JOIN product_documents pd ON sn.id = pd.serial_number_id AND pd.document_type = 'manual'
    WHERE sn.product_id = ? AND sn.status IN ('active', 'warranty')
    ORDER BY sn.created_at DESC
    LIMIT 1
");
$stmt->execute([$productId]);
$serialData = $stmt->fetch();

if (!$serialData) {
    // Если нет серийных номеров, пробуем получить данные о продукте для генерации
    $prodStmt = $pdo->prepare("SELECT warranty_months FROM products WHERE id = ?");
    $prodStmt->execute([$productId]);
    $productData = $prodStmt->fetch();
    
    // Генерируем серийный номер если его нет
    $date = new DateTime();
    $year = $date->format('y');
    $month = $date->format('m');
    $randomPart = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $generatedSerial = 'SN-' . $year . $month . '-' . $productId . '-' . $randomPart;
    
    // Возвращаем данные с сгенерированным серийным номером
    echo json_encode([
        'success' => true,
        'data' => [
            'serial_number' => $generatedSerial,
            'manufacture_date' => null,
            'warranty_start' => null,
            'warranty_end' => null,
            'warranty_months' => $productData['warranty_months'] ?? null,
            'manual_path' => null,
            'documents' => []
        ]
    ]);
    exit;
}

// Загружаем все документы для этого серийного номера
$docsStmt = $pdo->prepare("
    SELECT * FROM product_documents 
    WHERE serial_number_id = ? 
    ORDER BY 
        CASE document_type 
            WHEN 'manual' THEN 1 
            WHEN 'certificate' THEN 2 
            WHEN 'test_report' THEN 3 
            WHEN 'warranty_card' THEN 4 
            ELSE 5 
        END,
        uploaded_at DESC
");
$docsStmt->execute([$serialData['id']]);
$documents = $docsStmt->fetchAll();

// Формируем ответ
$response = [
    'success' => true,
    'data' => [
        'serial_number' => $serialData['serial_number'],
        'manufacture_date' => $serialData['manufacture_date'],
        'warranty_start' => $serialData['warranty_start'],
        'warranty_end' => $serialData['warranty_end'],
        'warranty_months' => $serialData['warranty_months'] ?? null,
        'manual_path' => $serialData['manual_path'],
        'technical_specs' => $serialData['technical_specs'] ? json_decode($serialData['technical_specs'], true) : [],
        'passport_data' => $serialData['passport_data'] ? json_decode($serialData['passport_data'], true) : [],
        'documents' => $documents,
        'status' => $serialData['status'],
        'notes' => $serialData['notes']
    ]
];

echo json_encode($response);
