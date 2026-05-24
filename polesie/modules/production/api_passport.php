<?php
/**
 * API для работы с паспортами и документами серийных номеров
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

$user = getCurrentUser();
$pdo = getDbConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'get_dynamic_data' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Получение динамических данных паспорта для предпросмотра печати
        $id = $_GET['id'] ?? '';
        
        if (!$id) {
            throw new Exception('Не указан ID продукта или серийного номера');
        }
        
        // Пытаемся найти серийный номер по ID (если это числовой ID)
        $dynamicData = null;
        if (is_numeric($id)) {
            $stmt = $pdo->prepare("SELECT * FROM passport_dynamic_data WHERE serial_number_id = ?");
            $stmt->execute([(int)$id]);
            $dynamicData = $stmt->fetch();
        }
        
        // Возвращаем данные в формате для формы
        $result = [
            'product_name' => $dynamicData['product_name_custom'] ?? '',
            'product_description' => $dynamicData['product_description'] ?? '',
            'manufacture_date' => $dynamicData['manufacture_date'] ?? '',
            'warranty_period' => $dynamicData['warranty_months'] ?? '12',
            'warranty_start' => $dynamicData['warranty_start'] ?? '',
            'warranty_end' => $dynamicData['warranty_end'] ?? '',
            'org_name' => $dynamicData['company_name'] ?? '',
            'org_address' => $dynamicData['company_address'] ?? '',
            'org_phone' => $dynamicData['company_phone'] ?? '',
            'org_email' => $dynamicData['company_email'] ?? ''
        ];
        
        echo json_encode($result);
        
    } elseif ($action === 'save_dynamic_data' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Сохранение динамических данных паспорта перед печатью
        if (!hasPermission('production.edit')) {
            throw new Exception('Нет прав доступа');
        }
        
        $id = $_POST['id'] ?? '';
        $serialNumber = $_POST['serial_number'] ?? '';
        
        if (!$id) {
            throw new Exception('Не указан ID продукта');
        }
        
        // Находим serial_number_id по серийному номеру или ID
        $serialId = null;
        if (!empty($serialNumber) && is_numeric($id)) {
            $serialId = (int)$id;
        } else {
            // Пытаемся найти по серийному номеру
            $stmt = $pdo->prepare("SELECT id FROM product_serial_numbers WHERE serial_number = ?");
            $stmt->execute([$serialNumber]);
            $sn = $stmt->fetch();
            if ($sn) {
                $serialId = $sn['id'];
            }
        }
        
        // Если не нашли, создаем новую запись или используем заглушку
        // Для продуктов из JSON без серийного номера создаем временную запись
        if (!$serialId) {
            // Создаем временный серийный номер для сохранения данных
            $tempSerial = 'TEMP_' . $id . '_' . time();
            $productId = is_numeric($id) ? (int)$id : null;
            
            $insertStmt = $pdo->prepare("
                INSERT INTO product_serial_numbers 
                (serial_number, product_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");
            $insertStmt->execute([$tempSerial, $productId]);
            $serialId = $pdo->lastInsertId();
        }
        
        // Данные из формы
        $productName = trim($_POST['product_name'] ?? '');
        $productDescription = trim($_POST['product_description'] ?? '');
        $manufactureDate = !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null;
        $warrantyPeriod = !empty($_POST['warranty_period']) ? (int)$_POST['warranty_period'] : 12;
        $warrantyStart = !empty($_POST['warranty_start']) ? $_POST['warranty_start'] : null;
        $warrantyEnd = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;
        $orgName = trim($_POST['org_name'] ?? '');
        $orgAddress = trim($_POST['org_address'] ?? '');
        $orgPhone = trim($_POST['org_phone'] ?? '');
        $orgEmail = trim($_POST['org_email'] ?? '');
        
        $user = getCurrentUser();
        
        // Проверяем существование записи
        $checkStmt = $pdo->prepare("SELECT id FROM passport_dynamic_data WHERE serial_number_id = ?");
        $checkStmt->execute([$serialId]);
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            // Обновление существующих данных
            $updateStmt = $pdo->prepare("
                UPDATE passport_dynamic_data 
                SET warranty_start = ?, warranty_end = ?, warranty_months = ?,
                    manufacture_date = ?, company_name = ?, company_address = ?,
                    company_phone = ?, company_email = ?, product_name_custom = ?,
                    product_description = ?, updated_at = NOW()
                WHERE serial_number_id = ?
            ");
            $updateStmt->execute([
                $warrantyStart, $warrantyEnd, $warrantyPeriod,
                $manufactureDate, $orgName, $orgAddress,
                $orgPhone, $orgEmail, $productName,
                $productDescription, $serialId
            ]);
        } else {
            // Создание новых данных
            $insertStmt = $pdo->prepare("
                INSERT INTO passport_dynamic_data 
                (serial_number_id, warranty_start, warranty_end, warranty_months, manufacture_date,
                 company_name, company_address, company_phone, company_email, product_name_custom,
                 product_description, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $serialId, $warrantyStart, $warrantyEnd, $warrantyPeriod, $manufactureDate,
                $orgName, $orgAddress, $orgPhone, $orgEmail, $productName,
                $productDescription, $user['id']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Данные сохранены', 'serial_id' => $serialId]);
        
    } elseif ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Получение данных о серийном номере (для AJAX)
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            throw new Exception('Не указан ID');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM product_serial_numbers WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            throw new Exception('Серийный номер не найден');
        }
        
        // Получаем динамические данные паспорта если они есть
        $dynamicStmt = $pdo->prepare("SELECT * FROM passport_dynamic_data WHERE serial_number_id = ?");
        $dynamicStmt->execute([$id]);
        $dynamicData = $dynamicStmt->fetch();
        
        if ($dynamicData) {
            $data['passport_dynamic'] = $dynamicData;
        }
        
        echo json_encode($data);
        
    } elseif ($action === 'update_passport' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Обновление паспорта
        if (!hasPermission('production.edit')) {
            throw new Exception('Нет прав доступа');
        }
        
        $serialId = (int)$_POST['serial_id'];
        $technicalSpecs = !empty($_POST['technical_specs']) ? json_decode($_POST['technical_specs'], true) : null;
        $notes = trim($_POST['notes'] ?? '');
        
        // Данные для динамического паспорта
        $warrantyStart = !empty($_POST['warranty_start']) ? $_POST['warranty_start'] : null;
        $warrantyEnd = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;
        $warrantyMonths = !empty($_POST['warranty_months']) ? (int)$_POST['warranty_months'] : 12;
        $manufactureDate = !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null;
        $companyName = !empty($_POST['company_name']) ? trim($_POST['company_name']) : null;
        $companyAddress = !empty($_POST['company_address']) ? trim($_POST['company_address']) : null;
        $companyPhone = !empty($_POST['company_phone']) ? trim($_POST['company_phone']) : null;
        $companyEmail = !empty($_POST['company_email']) ? trim($_POST['company_email']) : null;
        $productNameCustom = !empty($_POST['product_name_custom']) ? trim($_POST['product_name_custom']) : null;
        $productDescription = !empty($_POST['product_description']) ? trim($_POST['product_description']) : null;
        
        // Сохраняем текущую версию перед обновлением
        $versionStmt = $pdo->prepare("SELECT MAX(version_number) as max_ver FROM product_passport_versions WHERE serial_number_id = ?");
        $versionStmt->execute([$serialId]);
        $maxVer = $versionStmt->fetchColumn() ?: 0;
        $newVersion = $maxVer + 1;
        
        // Получаем текущие данные для сохранения версии
        $currentStmt = $pdo->prepare("SELECT technical_specs, passport_data FROM product_serial_numbers WHERE id = ?");
        $currentStmt->execute([$serialId]);
        $currentData = $currentStmt->fetch();
        
        // Сохраняем версию
        $saveVersionStmt = $pdo->prepare("
            INSERT INTO product_passport_versions (serial_number_id, version_number, passport_data, generated_by)
            VALUES (?, ?, ?, ?)
        ");
        $passportData = [
            'technical_specs' => $technicalSpecs,
            'notes' => $notes,
            'warranty_start' => $warrantyStart,
            'warranty_end' => $warrantyEnd,
            'warranty_months' => $warrantyMonths,
            'manufacture_date' => $manufactureDate
        ];
        $saveVersionStmt->execute([
            $serialId, 
            $newVersion, 
            json_encode($passportData, JSON_UNESCAPED_UNICODE),
            $user['id']
        ]);
        
        // Обновляем запись в product_serial_numbers
        $stmt = $pdo->prepare("
            UPDATE product_serial_numbers 
            SET technical_specs = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $technicalSpecs ? json_encode($technicalSpecs, JSON_UNESCAPED_UNICODE) : null,
            $notes,
            $serialId
        ]);
        
        // Обновляем или создаём динамические данные паспорта
        $checkDynamicStmt = $pdo->prepare("SELECT id FROM passport_dynamic_data WHERE serial_number_id = ?");
        $checkDynamicStmt->execute([$serialId]);
        $exists = $checkDynamicStmt->fetch();
        
        if ($exists) {
            // Обновление существующих данных
            $updateDynamicStmt = $pdo->prepare("
                UPDATE passport_dynamic_data 
                SET warranty_start = ?, warranty_end = ?, warranty_months = ?,
                    manufacture_date = ?, company_name = ?, company_address = ?,
                    company_phone = ?, company_email = ?, product_name_custom = ?,
                    product_description = ?, updated_at = NOW()
                WHERE serial_number_id = ?
            ");
            $updateDynamicStmt->execute([
                $warrantyStart, $warrantyEnd, $warrantyMonths,
                $manufactureDate, $companyName, $companyAddress,
                $companyPhone, $companyEmail, $productNameCustom,
                $productDescription, $serialId
            ]);
        } else {
            // Создание новых данных
            $insertDynamicStmt = $pdo->prepare("
                INSERT INTO passport_dynamic_data 
                (serial_number_id, warranty_start, warranty_end, warranty_months, manufacture_date,
                 company_name, company_address, company_phone, company_email, product_name_custom,
                 product_description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertDynamicStmt->execute([
                $serialId, $warrantyStart, $warrantyEnd, $warrantyMonths, $manufactureDate,
                $companyName, $companyAddress, $companyPhone, $companyEmail, $productNameCustom,
                $productDescription, $user['id']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Паспорт обновлён', 'version' => $newVersion]);
        
    } elseif ($action === 'upload_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Сохранение документа
        if (!hasPermission('production.edit')) {
            throw new Exception('Нет прав доступа');
        }
        
        $serialId = (int)$_POST['serial_id'];
        $documentType = $_POST['document_type'];
        $description = trim($_POST['description'] ?? '');
        
        // Проверка файла
        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Ошибка загрузки файла');
        }
        
        $file = $_FILES['document_file'];
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Недопустимый тип файла');
        }
        
        // Создание директории для документов
        $uploadDir = BASE_PATH . '/uploads/documents/serial_' . $serialId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Генерация уникального имени файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;
        
        // Перемещение файла
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Не удалось сохранить файл');
        }
        
        // Сохранение в БД
        $webPath = '/polesie/uploads/documents/serial_' . $serialId . '/' . $fileName;
        $stmt = $pdo->prepare("
            INSERT INTO product_documents (serial_number_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $serialId,
            $documentType,
            $file['name'],
            $webPath,
            $file['size'],
            $file['type'],
            $user['id'],
            $description
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Документ загружен']);
        
    } elseif ($action === 'delete_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Удаление документа
        if (!hasPermission('production.delete')) {
            throw new Exception('Нет прав доступа');
        }
        
        $docId = (int)$_POST['document_id'];
        
        // Получение информации о файле
        $stmt = $pdo->prepare("SELECT file_path FROM product_documents WHERE id = ?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // Удаление файла
            $fullPath = BASE_PATH . $doc['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Удаление записи из БД
            $deleteStmt = $pdo->prepare("DELETE FROM product_documents WHERE id = ?");
            $deleteStmt->execute([$docId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Документ удалён']);
        
    } elseif ($action === 'get_templates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Получение всех шаблонов разделов паспорта
        $stmt = $pdo->prepare("SELECT * FROM passport_templates ORDER BY sort_order");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'templates' => $templates]);
        
    } elseif ($action === 'save_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Сохранение изменений в шаблоне раздела
        if (!hasPermission('production.edit')) {
            throw new Exception('Нет прав доступа');
        }
        
        $sectionKey = $_POST['section_key'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $contentTemplate = $_POST['content_template'] ?? '';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$sectionKey || !$title) {
            throw new Exception('Не указаны обязательные поля');
        }
        
        // Проверяем существование
        $checkStmt = $pdo->prepare("SELECT id FROM passport_templates WHERE section_key = ?");
        $checkStmt->execute([$sectionKey]);
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            $updateStmt = $pdo->prepare("
                UPDATE passport_templates 
                SET title = ?, content_template = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE section_key = ?
            ");
            $updateStmt->execute([$title, $contentTemplate, $sortOrder, $isActive, $sectionKey]);
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO passport_templates 
                (section_key, title, content_template, sort_order, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([$sectionKey, $title, $contentTemplate, $sortOrder, $isActive]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Шаблон сохранён']);
        
    } elseif ($action === 'generate_serial' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Автоматическая генерация серийного номера
        if (!hasPermission('production.create')) {
            throw new Exception('Нет прав доступа');
        }
        
        $productId = (int)$_POST['product_id'];
        $productionOrderId = !empty($_POST['production_order_id']) ? (int)$_POST['production_order_id'] : null;
        $prefix = trim($_POST['prefix'] ?? 'SN');
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        // Получение информации о продукте
        $productStmt = $pdo->prepare("SELECT article, name FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        
        if (!$product) {
            throw new Exception('Продукт не найден');
        }
        
        $generatedSerials = [];
        
        for ($i = 0; $i < $quantity; $i++) {
            // Генерация формата: PREFIX-ARTICLE-YYYYMMDD-XXXX
            $datePart = date('Ymd');
            $randomPart = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $serialNumber = $prefix . '-' . $product['article'] . '-' . $datePart . '-' . $randomPart;
            
            // Проверка уникальности
            $checkStmt = $pdo->prepare("SELECT id FROM product_serial_numbers WHERE serial_number = ?");
            $checkStmt->execute([$serialNumber]);
            if ($checkStmt->fetch()) {
                continue; // Пропускаем если уже существует
            }
            
            // Вставка
            $insertStmt = $pdo->prepare("
                INSERT INTO product_serial_numbers 
                (serial_number, product_id, production_order_id, manufacture_date, status, created_by)
                VALUES (?, ?, ?, CURDATE(), 'active', ?)
            ");
            $insertStmt->execute([
                $serialNumber,
                $productId,
                $productionOrderId,
                $user['id']
            ]);
            
            $generatedSerials[] = $serialNumber;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Сгенерировано " . count($generatedSerials) . " серийных номеров",
            'serials' => $generatedSerials
        ]);
        
    } else {
        throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
