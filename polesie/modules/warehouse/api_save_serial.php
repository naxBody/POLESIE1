<?php
/**
 * API для сохранения серийного номера
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

header('Content-Type: application/json');

$pdo = getDbConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['product_id']) || empty($input['serial_number'])) {
        echo json_encode(['success' => false, 'error' => 'Не указаны product_id или serial_number']);
        exit;
    }
    
    $productId = (int)$input['product_id'];
    $serialNumber = trim($input['serial_number']);
    $userId = getCurrentUser()['id'] ?? null;
    
    // Проверяем, существует ли уже серийный номер для этого продукта
    $stmt = $pdo->prepare("SELECT id FROM product_serial_numbers WHERE product_id = ?");
    $stmt->execute([$productId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Обновляем существующий
        $stmt = $pdo->prepare("
            UPDATE product_serial_numbers 
            SET serial_number = ?, updated_at = NOW()
            WHERE product_id = ?
        ");
        $stmt->execute([$serialNumber, $productId]);
    } else {
        // Создаём новый
        $stmt = $pdo->prepare("
            INSERT INTO product_serial_numbers (product_id, serial_number, status, created_by, created_at, updated_at)
            VALUES (?, ?, 'active', ?, NOW(), NOW())
        ");
        $stmt->execute([$productId, $serialNumber, $userId]);
    }
    
    echo json_encode(['success' => true, 'serial_number' => $serialNumber]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
