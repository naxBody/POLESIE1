<?php
// Скрипт экстренного сброса паролей для демонстрации
// Запустите один раз в браузере: http://localhost/POLESIE/polesie/fix_users.php

require_once __DIR__ . '/config/config.php';

echo "<h2>Сброс паролей пользователей</h2>";

try {
    $pdo = getDBConnection();
    
    // Хэшируем пароли
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $manager_pass = password_hash('manager123', PASSWORD_DEFAULT);
    $worker_pass = password_hash('worker123', PASSWORD_DEFAULT);

    // Проверяем наличие таблицы
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        die("<p style='color:red'>Таблица users не найдена! Сначала импортируйте database.sql</p>");
    }

    // Обновляем или создаем админа
    $sql = "INSERT INTO users (login, password, full_name, role_id, status) 
            VALUES ('admin', ?, 'Администратор Системы', 1, 1)
            ON DUPLICATE KEY UPDATE password=?, full_name='Администратор Системы'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_pass, $admin_pass]);
    echo "<p style='color:green'>✓ Пользователь <b>admin</b> (пароль: admin123) обновлен.</p>";

    // Обновляем или создаем менеджера
    $sql = "INSERT INTO users (login, password, full_name, role_id, status) 
            VALUES ('manager', ?, 'Иванов Иван Иванович', 2, 1)
            ON DUPLICATE KEY UPDATE password=?, full_name='Иванов Иван Иванович'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$manager_pass, $manager_pass]);
    echo "<p style='color:green'>✓ Пользователь <b>manager</b> (пароль: manager123) обновлен.</p>";
    
    // Обновляем или создаем рабочего
    $sql = "INSERT INTO users (login, password, full_name, role_id, status) 
            VALUES ('worker', ?, 'Петров Петр Петрович', 6, 1)
            ON DUPLICATE KEY UPDATE password=?, full_name='Петров Петр Петрович'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$worker_pass, $worker_pass]);
    echo "<p style='color:green'>✓ Пользователь <b>worker</b> (пароль: worker123) обновлен.</p>";

    echo "<hr><h3>Готово!</h3><p>Теперь попробуйте войти: <a href='login.php'>Перейти на страницу входа</a></p>";
    echo "<p><small>Удалите этот файл (fix_users.php) после использования в целях безопасности.</small></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Ошибка базы данных: " . $e->getMessage() . "</p>";
    echo "<p>Проверьте настройки в config/config.php</p>";
}
?>
