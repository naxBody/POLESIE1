<!-- Боковая панель навигации -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">⚡</div>
            <span>Полесьеэлектромаш</span>
        </div>
    </div>
    
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-user-avatar">
                <?= mb_substr($user['full_name'], 0, 1) ?>
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name"><?= e($user['full_name']) ?></div>
                <div class="sidebar-user-role"><?= e($user['role_name']) ?></div>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Основное -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Основное</div>
            <?php
            // Получаем полный путь относительно корня приложения
            $currentPath = $_SERVER['PHP_SELF'];
            // Извлекаем относительный путь после /polesie/ (регистронезависимо)
            if (preg_match('#/polesie/(.*)$#i', $currentPath, $matches)) {
                $relativePath = $matches[1];
            } else {
                $relativePath = ltrim($currentPath, '/');
            }
            ?>
            <a href="<?= pageUrl('index.php') ?>" class="sidebar-nav-item <?= $relativePath === 'index.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📊</span>
                <span>Панель управления</span>
            </a>
        </div>
        
        <!-- Заказы -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Заказы</div>
            <a href="<?= pageUrl('modules/orders/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📦</span>
                <span>Все заказы</span>
            </a>
            <a href="<?= pageUrl('modules/orders/create.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/create.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">➕</span>
                <span>Новый заказ</span>
            </a>
            <a href="<?= pageUrl('modules/contractors/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/contractors/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">🏢</span>
                <span>Контрагенты</span>
            </a>
        </div>
        
        <!-- Производство -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Производство</div>
            <a href="<?= pageUrl('modules/production/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">⚙️</span>
                <span>Производственные задания</span>
            </a>
            <a href="<?= pageUrl('modules/production/serial_numbers.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/serial_numbers.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">🔢</span>
                <span>Серийные номера</span>
            </a>
            <a href="<?= pageUrl('modules/products/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">🔧</span>
                <span>Продукция</span>
            </a>
            <a href="<?= pageUrl('modules/products/passports.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/passports.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📄</span>
                <span>Паспорта продуктов</span>
            </a>
        </div>
        
        <!-- Контроль качества -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Контроль качества</div>
            <a href="<?= pageUrl('modules/quality/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/quality/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">✅</span>
                <span>Проверки</span>
            </a>
        </div>
        
        <!-- Склад -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Склад</div>
            <a href="<?= pageUrl('modules/warehouse/materials.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/materials.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📦</span>
                <span>Материалы</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/production.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/production.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">⚡</span>
                <span>Продукция (каталог)</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">🏭</span>
                <span>Остатки на складе</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/docs.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/docs.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📚</span>
                <span>Документы и справочники</span>
            </a>
        </div>
        
        <!-- Сотрудники -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Сотрудники</div>
            <a href="<?= pageUrl('modules/employees/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/employees/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">👥</span>
                <span>Все сотрудники</span>
            </a>
        </div>
        
        <!-- Отчеты -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Отчеты</div>
            <a href="<?= pageUrl('modules/reports/index.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/reports/index.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon">📊</span>
                <span>Производство</span>
            </a>
        </div>
    </nav>
</div>
