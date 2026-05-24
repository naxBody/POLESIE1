<?php
/**
 * Конфигурационный файл системы управления производством
 * ОАО "Полесьеэлектромаш"
 */

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'polesie_production');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('APP_NAME', 'ОАО "Полесьеэлектромаш" - Система управления производством');
define('APP_VERSION', '1.0.0');

// Динамическое определение базового URL приложения
// Определяем путь относительно корня проекта polesie
$scriptPath = $_SERVER['SCRIPT_NAME'];

// Проверяем, есть ли в пути /POLESIE/polesie/ или /polesie/ (с учетом регистра)
if (stripos($scriptPath, '/POLESIE/polesie/') !== false) {
    // Случай когда путь содержит /POLESIE/polesie/
    $pos = stripos($scriptPath, '/POLESIE/polesie/');
    $basePath = rtrim(substr($scriptPath, 0, $pos + strlen('/POLESIE/polesie/')), '/');
} elseif (stripos($scriptPath, '/polesie/') !== false) {
    // Случай когда путь содержит /polesie/
    $pos = stripos($scriptPath, '/polesie/');
    $basePath = rtrim(substr($scriptPath, 0, $pos + strlen('/polesie/')), '/');
} else {
    // Если не нашли, используем dirname
    $basePath = rtrim(dirname($scriptPath), '/');
}

// Нормализуем basePath
if ($basePath === '' || $basePath === '/') {
    $basePath = '';
}
define('APP_BASE_PATH', $basePath);
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . $basePath);

define('APP_TIMEZONE', 'Europe/Minsk');
define('APP_LANGUAGE', 'ru');

// Пути к директориям
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_name('POLESIE_SESSION');

// Отображение ошибок (в production установить false)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Установка временной зоны
date_default_timezone_set(APP_TIMEZONE);

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Подключение к базе данных
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Безопасный вывод данных
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Перенаправление
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Проверка авторизации
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Получение текущего пользователя
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.code as role_code, r.name as role_name 
            FROM users u 
            JOIN user_roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.is_active = TRUE
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

/**
 * Проверка прав доступа
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    if ($user['role_code'] === 'admin') {
        return true;
    }
    
    // Здесь можно добавить проверку конкретных прав
    return true;
}

/**
 * Генерация уникального номера
 */
function generateUniqueNumber($prefix, $table, $column) {
    $pdo = getDbConnection();
    $date = date('Ymd');
    $pattern = $prefix . $date . '%';
    
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1");
    $stmt->execute([$pattern]);
    $last = $stmt->fetchColumn();
    
    if ($last) {
        $num = intval(substr($last, -4)) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . $date . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * Форматирование даты
 */
function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Форматирование суммы
 */
function formatMoney($amount, $currency = 'BYN') {
    $symbols = [
        'BYN' => 'Br',
        'USD' => '$',
        'EUR' => '€',
        'RUB' => '₽'
    ];
    $symbol = $symbols[$currency] ?? $currency;
    return number_format((float)$amount, 2, ',', ' ') . ' ' . $symbol;
}

/**
 * Логирование действий (упрощено - без activity_log)
 */
function logActivity($action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
    // Функция сохранена для обратной совместимости, но больше не записывает в БД
    // При необходимости можно реализовать запись в файл или удалить
    return;
}

/**
 * Создание уведомления (удалено - таблица notifications удалена)
 */
function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    // Функция сохранена для обратной совместимости, но больше не записывает в БД
    // Уведомления удалены для упрощения структуры БД
    return;
}

/**
 * Получение настроек системы
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Получить URL для ассетов (CSS, JS, изображения)
 */
function asset($path) {
    // Убираем ведущий слэш если есть
    $path = ltrim($path, '/');
    // Проверяем, уже ли содержит APP_URL путь к polesie
    if (stripos(APP_URL, '/polesie') !== false && stripos($path, 'polesie/') === 0) {
        // Если APP_URL уже содержит /polesie и путь начинается с polesie/, убираем дублирование
        $path = substr($path, strlen('polesie/'));
    }
    return APP_URL . '/' . $path;
}

/**
 * Получить URL для страниц
 */
function pageUrl($path) {
    // Убираем ведущий слэш если есть
    $path = ltrim($path, '/');
    // Проверяем, уже ли содержит APP_URL путь к polesie
    if (stripos(APP_URL, '/polesie') !== false && stripos($path, 'polesie/') === 0) {
        // Если APP_URL уже содержит /polesie и путь начинается с polesie/, убираем дублирование
        $path = substr($path, strlen('polesie/'));
    }
    return APP_URL . '/' . $path;
}
