<?php
/**
 * Функции аутентификации и авторизации
 */

/**
 * Вход пользователя в систему
 */
function login($username, $password) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.code as role_code, r.name as role_name 
        FROM users u 
        JOIN user_roles r ON u.role_id = r.id 
        WHERE u.username = ? AND u.is_active = TRUE
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    // Простое сравнение паролей без хеширования
    if ($user && $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_code'] = $user['role_code'];
        
        // Обновление времени последнего входа
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        logActivity('login', 'user', $user['id']);
        
        return true;
    }
    
    return false;
}

/**
 * Выход из системы
 */
function logout() {
    if (isLoggedIn()) {
        logActivity('logout', 'user', $_SESSION['user_id']);
    }
    
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Регистрация нового пользователя
 */
function registerUser($data) {
    $pdo = getDbConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Проверка существования username
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$data['username']]);
        if ($checkStmt->fetch()) {
            throw new Exception('Пользователь с таким именем уже существует');
        }
        
        // Пароль без хеширования (простой текст)
        $passwordPlain = $data['password'];
        
        // Вставка пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, email, phone, role_id, department, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['username'],
            $passwordPlain,
            $data['full_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['role_id'],
            $data['department'] ?? null,
            $data['position'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        
        logActivity('user_create', 'user', $userId, null, $data);
        
        $pdo->commit();
        
        return $userId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Обновление данных пользователя
 */
function updateUser($userId, $data) {
    $pdo = getDbConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Получение текущих данных
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldData = $stmt->fetch();
        
        if (!$oldData) {
            throw new Exception('Пользователь не найден');
        }
        
        // Формирование запроса обновления
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['full_name', 'email', 'phone', 'role_id', 'department', 'position', 'is_active'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (!empty($fields)) {
            $values[] = $userId;
            $updateStmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $updateStmt->execute($values);
            
            logActivity('user_update', 'user', $userId, $oldData, $data);
        }
        
        $pdo->commit();
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Смена пароля
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $pdo = getDbConnection();
    
    // Проверка старого пароля (простое сравнение)
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || $oldPassword !== $user['password_hash']) {
        return false;
    }
    
    // Обновление пароля (без хеширования)
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateStmt->execute([$newPassword, $userId]);
    
    logActivity('password_change', 'user', $userId);
    
    return true;
}

/**
 * Получение списка пользователей
 */
function getUsersList($filters = []) {
    $pdo = getDbConnection();
    
    $sql = "
        SELECT u.*, r.name as role_name, r.code as role_code
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    if (isset($filters['role_id']) && $filters['role_id'] !== '') {
        $sql .= " AND u.role_id = ?";
        $params[] = $filters['role_id'];
    }
    
    if (isset($filters['is_active'])) {
        $sql .= " AND u.is_active = ?";
        $params[] = $filters['is_active'];
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Получение пользователя по ID
 */
function getUserById($userId) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.code as role_code
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch();
}

/**
 * Получение списка ролей
 */
function getRolesList() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("SELECT * FROM user_roles WHERE is_active = TRUE ORDER BY name");
    
    return $stmt->fetchAll();
}
