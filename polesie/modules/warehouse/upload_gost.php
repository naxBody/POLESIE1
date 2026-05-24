<?php
/**
 * Обработчик загрузки ГОСТов
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

header('Content-Type: application/json');

// Проверка авторизации
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

$user = getCurrentUser();

// Проверка прав доступа (только администраторы или инженеры)
if ($user['role_code'] !== 'admin' && $user['role_code'] !== 'engineer') {
    echo json_encode(['success' => false, 'message' => 'Недостаточно прав для загрузки файлов']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Определяем действие: загрузка нового или редактирование
$action = $_GET['action'] ?? 'upload';
$isEdit = ($action === 'edit');

// Обработка добавления категории
if ($action === 'add_category') {
    $input = json_decode(file_get_contents('php://input'), true);
    $category = trim($input['category'] ?? '');
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Категория не указана']);
        exit;
    }
    
    // Загружаем существующие данные
    $docsPath = BASE_PATH . '/list_materials_docs.json';
    $docsData = [];
    
    if (file_exists($docsPath)) {
        $jsonData = file_get_contents($docsPath);
        $docsData = json_decode($jsonData, true);
    }
    
    // Инициализация структуры если не существует
    if (!isset($docsData['gost_standards'])) {
        $docsData['gost_standards'] = [];
    }
    
    // Проверяем, есть ли уже такая категория
    $categoryExists = false;
    foreach ($docsData['gost_standards'] as &$gost) {
        if (isset($gost['category']) && $gost['category'] === $category) {
            $categoryExists = true;
            break;
        }
    }
    
    if (!$categoryExists) {
        // Добавляем новый ГОСТ с этой категорией для сохранения категории в списке
        $docsData['gost_standards'][] = [
            'gost_number' => '_CATEGORY_' . $category,
            'title' => '_RESERVED_CATEGORY_',
            'category' => $category,
            'status' => 'Действующий',
            'file_name' => '',
            'uploaded_at' => date('Y-m-d H:i:s'),
            'uploaded_by' => $user['id']
        ];
    }
    
    // Сохранение обновленных данных
    if (file_put_contents($docsPath, json_encode($docsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'message' => 'Категория сохранена']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения данных']);
    }
    exit;
}

// Обработка удаления категории
if ($action === 'delete_category') {
    $input = json_decode(file_get_contents('php://input'), true);
    $category = trim($input['category'] ?? '');
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Категория не указана']);
        exit;
    }
    
    // Загружаем существующие данные
    $docsPath = BASE_PATH . '/list_materials_docs.json';
    $docsData = [];
    
    if (file_exists($docsPath)) {
        $jsonData = file_get_contents($docsPath);
        $docsData = json_decode($jsonData, true);
    }
    
    if (!isset($docsData['gost_standards'])) {
        echo json_encode(['success' => false, 'message' => 'Список ГОСТов пуст']);
        exit;
    }
    
    // Удаляем все ГОСТы с этой категорией (включая зарезервированные)
    $filteredStandards = array_filter($docsData['gost_standards'], function($gost) use ($category) {
        return !isset($gost['category']) || $gost['category'] !== $category;
    });
    
    $docsData['gost_standards'] = array_values($filteredStandards);
    
    // Сохранение обновленных данных
    if (file_put_contents($docsPath, json_encode($docsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'message' => 'Категория удалена']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения данных']);
    }
    exit;
}

// Для редактирования файл не обязателен
$fileRequired = !$isEdit;
$hasFile = isset($_FILES['gost_file']) && $_FILES['gost_file']['error'] !== UPLOAD_ERR_NO_FILE;

if ($fileRequired && !$hasFile) {
    echo json_encode(['success' => false, 'message' => 'Файл не был загружен']);
    exit;
}

$file = null;

// Обрабатываем файл если он есть
if ($hasFile) {
    $file = $_FILES['gost_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Файл слишком большой (превышен лимит php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой (превышен лимит формы)',
            UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная директория',
            UPLOAD_ERR_CANT_WRITE => 'Ошибка записи файла на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка прервана расширением PHP'
        ];
        echo json_encode([
            'success' => false, 
            'message' => $errorMessages[$file['error']] ?? 'Ошибка загрузки файла'
        ]);
        exit;
    }
    
    $allowedTypes = ['application/pdf', 'application/x-pdf'];
    $maxSize = 50 * 1024 * 1024; // 50 MB
    
    // Проверка типа файла
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileType = $finfo->file($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Разрешены только PDF файлы']);
        exit;
    }
    
    // Проверка размера
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Файл слишком большой (максимум 50 MB)']);
        exit;
    }
}

// Получение данных из формы
$gostNumber = trim($_POST['gost_number'] ?? '');
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$status = trim($_POST['status'] ?? 'Действующий');

// Валидация данных
if (empty($gostNumber)) {
    echo json_encode(['success' => false, 'message' => 'Укажите номер ГОСТа']);
    exit;
}

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Укажите название стандарта']);
    exit;
}

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Укажите категорию']);
    exit;
}

// Получение существующих данных
$docsPath = BASE_PATH . '/list_materials_docs.json';
$docsData = [];

if (file_exists($docsPath)) {
    $jsonData = file_get_contents($docsPath);
    $docsData = json_decode($jsonData, true);
}

// Инициализация структуры если не существует
if (!isset($docsData['gost_standards'])) {
    $docsData['gost_standards'] = [];
}

// Для редактирования - ищем существующий ГОСТ по индексу
$existingGost = null;
$existingIndex = null;

if ($isEdit) {
    $index = $_POST['index'] ?? null;
    if ($index !== null && isset($docsData['gost_standards'][$index])) {
        $existingGost = $docsData['gost_standards'][$index];
        $existingIndex = $index;
    }
}

// Извлечение номера ГОСТа для имени файла
$gostNumberClean = preg_replace('/ГОСТ\s*([0-9.]+(?:-[0-9]+)?).*/i', '$1', $gostNumber);
$newFileName = 'gost_' . str_replace('.', '-', $gostNumberClean) . '.pdf';
$uploadPath = ASSETS_PATH . '/gosts/' . $newFileName;

// Если это редактирование и файл не загружен, используем старый файл
$fileName = $newFileName;
if ($isEdit && $existingGost && !$hasFile) {
    $fileName = $existingGost['file_name'] ?? $newFileName;
}

// Сохранение нового файла если он есть
if ($hasFile) {
    // Удаляем старый файл при редактировании если имя изменилось
    if ($isEdit && $existingGost && $existingGost['file_name'] !== $fileName) {
        $oldFilePath = ASSETS_PATH . '/gosts/' . $existingGost['file_name'];
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения файла']);
        exit;
    }
}

// Обновление или добавление ГОСТа
$updatedGost = [
    'gost_number' => $gostNumber,
    'title' => $title,
    'category' => $category,
    'status' => $status,
    'file_name' => $fileName
];

// Сохраняем дату загрузки и пользователя если это редактирование
if ($isEdit && $existingGost) {
    $updatedGost['uploaded_at'] = $existingGost['uploaded_at'] ?? date('Y-m-d H:i:s');
    $updatedGost['uploaded_by'] = $existingGost['uploaded_by'] ?? $user['id'];
} else {
    $updatedGost['uploaded_at'] = date('Y-m-d H:i:s');
    $updatedGost['uploaded_by'] = $user['id'];
}

// Обновляем или добавляем запись
if ($isEdit && $existingIndex !== null) {
    $docsData['gost_standards'][$existingIndex] = $updatedGost;
} else {
    // Проверяем, есть ли уже такой ГОСТ по номеру и обновляем его
    $found = false;
    foreach ($docsData['gost_standards'] as &$existingGostLoop) {
        if ($existingGostLoop['gost_number'] === $gostNumber) {
            $existingGostLoop = $updatedGost;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $docsData['gost_standards'][] = $updatedGost;
    }
}

// Сохранение обновленных данных
if (file_put_contents($docsPath, json_encode($docsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    $message = $isEdit ? 'ГОСТ успешно обновлён' : 'ГОСТ успешно загружен';
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'file_name' => $fileName,
        'gost_number' => $gostNumber
    ]);
} else {
    // Удаляем файл если не удалось сохранить JSON (только если файл был загружен)
    if ($hasFile && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode(['success' => false, 'message' => 'Ошибка сохранения данных']);
}
