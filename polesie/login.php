<?php
/**
 * Страница входа в систему
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start();

// Если уже авторизован - перенаправляем на главную
if (isLoggedIn()) {
    redirect(pageUrl('index.php'));
}

$error = '';
$success = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Введите логин и пароль';
    } elseif (login($username, $password)) {
        logActivity('login_success', 'user', null, null, ['username' => $username]);
        redirect(pageUrl('index.php'));
    } else {
        $error = 'Неверный логин или пароль';
        logActivity('login_failed', 'user', null, null, ['username' => $username]);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">⚡</div>
            <h1 class="login-title">ОАО "Полесьеэлектромаш"</h1>
            <p class="login-subtitle">Система управления производством</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <?= e($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <?= e($success) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Логин</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Введите логин"
                    value="<?= e($username ?? '') ?>"
                    required 
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Введите пароль"
                    required
                >
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="remember" style="width: 16px; height: 16px;">
                    <span style="font-size: 13px; color: var(--text-secondary);">Запомнить меня</span>
                </label>
                <a href="#" style="font-size: 13px; color: var(--primary-color);">Забыли пароль?</a>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px;">
                Войти в систему
            </button>
        </form>
        
        <div style="margin-top: 24px; text-align: center; font-size: 13px; color: var(--text-secondary);">
            <p>Тестовые учетные записи:</p>
            <p style="margin-top: 8px;"><strong>admin / admin123</strong> — Администратор</p>
            <p><strong>manager / manager123</strong> — Менеджер</p>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
