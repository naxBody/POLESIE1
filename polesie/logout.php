<?php
/**
 * Выход из системы
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start();

logout();
redirect(pageUrl('login.php'));
