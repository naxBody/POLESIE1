-- ============================================
-- БАЗА ДАННЫХ СИСТЕМЫ УПРАВЛЕНИЯ ПРОИЗВОДСТВОМ
-- ОАО "Полесьеэлектромаш" (Беларусь)
-- ОПТИМИЗИРОВАННАЯ ВЕРСИЯ (17 таблиц)
-- Удалены: production_stages, notifications, activity_log, warehouse_transactions
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00"; -- Время Минска

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `polesie_production` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

-- ============================================
-- ТАБЛИЦЫ СПРАВОЧНИКОВ (6 таблиц)
-- ============================================

-- Статусы заказов
CREATE TABLE `order_statuses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) DEFAULT '#007bff',
  `sort_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Статусы производства
CREATE TABLE `production_statuses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) DEFAULT '#28a745',
  `sort_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Единицы измерения
CREATE TABLE `units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `short_name` VARCHAR(20) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Категории продукции
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Типы проверок качества
CREATE TABLE `quality_check_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `is_mandatory` BOOLEAN DEFAULT TRUE,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Роли пользователей
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ОСНОВНЫЕ ТАБЛИЦЫ (11 таблиц)
-- ============================================

-- Пользователи системы
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `role_id` INT NOT NULL,
  `department` VARCHAR(100),
  `position` VARCHAR(100),
  `avatar` VARCHAR(255),
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_login` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Контрагенты (заказчики, поставщики)
CREATE TABLE `contractors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `inn` VARCHAR(12) NOT NULL UNIQUE,
  `legal_address` TEXT,
  `postal_address` TEXT,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `contact_person` VARCHAR(200),
  `contact_phone` VARCHAR(20),
  `bank_name` VARCHAR(200),
  `bik` VARCHAR(9),
  `account_number` VARCHAR(20),
  `type` ENUM('customer', 'supplier', 'both') DEFAULT 'customer',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_inn` (`inn`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Продукция (каталог изделий)
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `category_id` INT,
  `description` TEXT,
  `specifications` JSON,
  `unit_id` INT,
  `base_price` DECIMAL(15,2) DEFAULT 0.00,
  `currency` CHAR(3) DEFAULT 'BYN',
  `image` VARCHAR(255),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL,
  INDEX `idx_article` (`article`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заказы
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `contractor_id` INT NOT NULL,
  `status_id` INT NOT NULL,
  `order_date` DATE NOT NULL,
  `delivery_date` DATE,
  `delivery_address` TEXT,
  `total_amount` DECIMAL(15,2) DEFAULT 0.00,
  `currency` CHAR(3) DEFAULT 'BYN',
  `payment_terms` TEXT,
  `notes` TEXT,
  `responsible_user_id` INT,
  `contract_number` VARCHAR(50),
  `contract_date` DATE,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`contractor_id`) REFERENCES `contractors`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`status_id`) REFERENCES `order_statuses`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`responsible_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_order_number` (`order_number`),
  INDEX `idx_contractor` (`contractor_id`),
  INDEX `idx_status` (`status_id`),
  INDEX `idx_order_date` (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции заказа
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `discount` DECIMAL(5,2) DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  INDEX `idx_order` (`order_id`),
  INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Производственные задания (объединяет production_orders + production_tasks)
CREATE TABLE `production_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_number` VARCHAR(50) NOT NULL UNIQUE,
  `order_item_id` INT,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity_planned` DECIMAL(15,3) NOT NULL,
  `quantity_completed` DECIMAL(15,3) DEFAULT 0.00,
  `status_id` INT NOT NULL,
  `operation_name` VARCHAR(200),
  `operation_description` TEXT,
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `workshop` VARCHAR(100),
  `brigade` VARCHAR(100),
  `station` VARCHAR(100),
  `responsible_user_id` INT,
  `worker_id` INT,
  `started_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `due_date` DATE,
  `estimated_hours` DECIMAL(10,2),
  `actual_hours` DECIMAL(10,2),
  `start_date` DATE,
  `end_date_planned` DATE,
  `end_date_actual` DATE,
  `technology_card` TEXT,
  `notes` TEXT,
  `auto_generate_serial` BOOLEAN DEFAULT FALSE,
  `serial_number_prefix` VARCHAR(20) DEFAULT 'SN',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`status_id`) REFERENCES `production_statuses`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`responsible_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`worker_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_production_number` (`production_number`),
  INDEX `idx_product` (`product_id`),
  INDEX `idx_status` (`status_id`),
  INDEX `idx_order` (`order_id`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Контроль качества (упрощено: удален stage_id)
CREATE TABLE `quality_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_order_id` INT NOT NULL,
  `check_type_id` INT NOT NULL,
  `inspector_id` INT NOT NULL,
  `check_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `result` ENUM('passed', 'failed', 'conditional') NOT NULL,
  `defects_found` TEXT,
  `measurements` JSON,
  `photos` JSON,
  `comments` TEXT,
  `is_rework_required` BOOLEAN DEFAULT FALSE,
  `rework_completed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`production_order_id`) REFERENCES `production_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`check_type_id`) REFERENCES `quality_check_types`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_production_order` (`production_order_id`),
  INDEX `idx_check_date` (`check_date`),
  INDEX `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Склад материалов и сырья
CREATE TABLE `warehouse_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_name` VARCHAR(200) NOT NULL,
  `article` VARCHAR(50),
  `category` VARCHAR(100),
  `unit_id` INT,
  `current_quantity` DECIMAL(15,3) DEFAULT 0.00,
  `min_quantity` DECIMAL(15,3) DEFAULT 0.00,
  `max_quantity` DECIMAL(15,3),
  `location` VARCHAR(100),
  `supplier_id` INT,
  `last_purchase_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  INDEX `idx_article` (`article`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Готовая продукция на складе
CREATE TABLE `warehouse_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) DEFAULT 0.00,
  `location` VARCHAR(100),
  `batch_number` VARCHAR(50),
  `production_date` DATE,
  `warranty_until` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_product_batch` (`product_id`, `batch_number`),
  INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Настройки системы
CREATE TABLE `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `setting_type` VARCHAR(20) DEFAULT 'string',
  `description` TEXT,
  `updated_by` INT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ТАБЛИЦЫ СЕРИЙНЫХ НОМЕРОВ И ДОКУМЕНТОВ (3 таблицы)
-- ============================================

-- Серийные номера готовой продукции
CREATE TABLE `product_serial_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `product_id` INT NOT NULL,
  `production_order_id` INT,
  `manufacture_date` DATE NOT NULL,
  `warranty_start` DATE,
  `warranty_end` DATE,
  `has_dynamic_passport` BOOLEAN DEFAULT FALSE,
  `status` ENUM('active', 'warranty', 'expired', 'returned', 'scrapped') DEFAULT 'active',
  `technical_specs` JSON,
  `passport_data` JSON,
  `manual_file_path` VARCHAR(500),
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`production_order_id`) REFERENCES `production_orders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial_number` (`serial_number`),
  INDEX `idx_product` (`product_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_manufacture_date` (`manufacture_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Динамические данные паспорта изделия
CREATE TABLE `passport_dynamic_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL UNIQUE,
  `warranty_start` DATE,
  `warranty_end` DATE,
  `warranty_months` INT DEFAULT 12,
  `warranty_period` VARCHAR(100),
  `manufacture_date` DATE,
  `release_date` DATE,
  `product_name_custom` VARCHAR(255),
  `product_description` TEXT,
  `company_name` VARCHAR(255),
  `company_address` VARCHAR(500),
  `company_phone` VARCHAR(50),
  `company_email` VARCHAR(100),
  `additional_sections` JSON,
  `custom_fields` JSON,
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial` (`serial_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Версии паспортов (история изменений)
CREATE TABLE `product_passport_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL,
  `version_number` INT NOT NULL,
  `passport_data` JSON NOT NULL,
  `generated_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial` (`serial_number_id`),
  INDEX `idx_version` (`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Прикрепленные документы (руководства, сертификаты)
CREATE TABLE `product_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL,
  `document_type` ENUM('manual', 'certificate', 'test_report', 'warranty_card', 'other') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT,
  `mime_type` VARCHAR(100),
  `uploaded_by` INT,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `description` TEXT,
  FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial` (`serial_number_id`),
  INDEX `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаблоны разделов паспорта изделия
CREATE TABLE `passport_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(50) NOT NULL COMMENT 'Уникальный ключ раздела (например: header, specs, safety)',
  `title` VARCHAR(255) NOT NULL COMMENT 'Заголовок раздела',
  `content_template` TEXT COMMENT 'Шаблон содержимого (может содержать HTML)',
  `sort_order` INT DEFAULT 0 COMMENT 'Порядок отображения',
  `is_active` BOOLEAN DEFAULT 1 COMMENT 'Показывать ли раздел',
  `custom_fields` JSON DEFAULT NULL COMMENT 'Дополнительные настройки (шрифт, отступы и т.д.)',
  UNIQUE KEY `unique_section` (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Шаблоны разделов паспорта изделия';

-- ============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- ============================================

-- Роли пользователей
INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ ко всем функциям системы', '{"all": true}'),
('Менеджер по продажам', 'sales_manager', 'Управление заказами и клиентами', '{"orders": ["read", "create", "update"], "contractors": ["read", "create", "update"], "products": ["read"]}'),
('Технолог', 'technologist', 'Управление производственными процессами', '{"production": ["read", "create", "update"], "products": ["read", "update"], "quality": ["read"]}'),
('Инспектор ОТК', 'quality_inspector', 'Контроль качества продукции', '{"quality": ["read", "create", "update"], "production": ["read"]}'),
('Кладовщик', 'storekeeper', 'Управление складом', '{"warehouse": ["read", "create", "update"], "materials": ["read", "create", "update"]}'),
('Производственный рабочий', 'worker', 'Выполнение производственных заданий', '{"production": ["read"], "tasks": ["update"]}'),
('Руководитель', 'director', 'Просмотр отчетов и аналитики', '{"reports": ["read"], "dashboard": ["read"], "all": ["read"]}');

-- Статусы заказов
INSERT INTO `order_statuses` (`name`, `color`, `sort_order`) VALUES
('Новый', '#007bff', 1),
('Подтвержден', '#28a745', 2),
('В производстве', '#ffc107', 3),
('Готов к отгрузке', '#17a2b8', 4),
('Отгружен', '#6c757d', 5),
('Выполнен', '#28a745', 6),
('Отменен', '#dc3545', 7);

-- Статусы производства
INSERT INTO `production_statuses` (`name`, `color`, `sort_order`) VALUES
('Запланировано', '#6c757d', 1),
('В работе', '#007bff', 2),
('На контроле', '#ffc107', 3),
('Завершено', '#28a745', 4),
('Приостановлено', '#dc3545', 5);

-- Единицы измерения
INSERT INTO `units` (`name`, `short_name`) VALUES
('Штука', 'шт'),
('Комплект', 'компл'),
('Метр', 'м'),
('Килограмм', 'кг'),
('Тонна', 'т'),
('Литр', 'л');

-- Категории продукции
INSERT INTO `product_categories` (`name`, `description`) VALUES
('Электродвигатели', 'Асинхронные и синхронные электродвигатели'),
('Генераторы', 'Электрогенераторы различной мощности'),
('Трансформаторы', 'Силовые и измерительные трансформаторы'),
('Распределительное оборудование', 'Щиты управления и распределения'),
('Запасные части', 'Компоненты и запчасти для электрооборудования');

-- Типы проверок качества
INSERT INTO `quality_check_types` (`name`, `description`, `is_mandatory`, `sort_order`) VALUES
('Визуальный контроль', 'Проверка внешнего вида, отсутствия повреждений', TRUE, 1),
('Измерение габаритов', 'Контроль размеров согласно чертежу', TRUE, 2),
('Электрические испытания', 'Проверка электрических параметров', TRUE, 3),
('Испытание изоляции', 'Проверка сопротивления изоляции', TRUE, 4),
('Функциональное тестирование', 'Проверка работы изделия', TRUE, 5),
('Климатические испытания', 'Проверка работы в различных условиях', FALSE, 6);

-- Настройки системы
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'ОАО "Полесьеэлектромаш"', 'string', 'Полное наименование предприятия'),
('company_inn', '123456789', 'string', 'ИНН предприятия'),
('company_address', 'Республика Беларусь, Гомельская область', 'string', 'Юридический адрес'),
('company_phone', '+375 232 XX-XX-XX', 'string', 'Контактный телефон'),
('company_email', 'info@polesie.by', 'string', 'Электронная почта'),
('currency_default', 'BYN', 'string', 'Валюта по умолчанию'),
('timezone', 'Europe/Minsk', 'string', 'Часовой пояс'),
('language', 'ru', 'string', 'Язык интерфейса'),
('items_per_page', '20', 'integer', 'Количество записей на странице');

-- Шаблоны паспорта изделия
INSERT INTO `passport_templates` (`section_key`, `title`, `content_template`, `sort_order`, `is_active`) VALUES
('header', 'ПАСПОРТ', '<h2 style="text-align: center;">{product_name}</h2><p style="text-align: center;">Паспорт изделия</p>', 1, 1),
('manufacturer', 'СВЕДЕНИЯ ОБ ИЗГОТОВИТЕЛЕ', '<p><strong>Изготовитель:</strong> {org_name}</p><p><strong>Адрес:</strong> {org_address}</p><p><strong>Телефон:</strong> {org_phone}</p><p><strong>Email:</strong> {org_email}</p>', 2, 1),
('basic_info', 'ОСНОВНЫЕ СВЕДЕНИЯ ОБ ИЗДЕЛИИ', '<p><strong>Наименование:</strong> {product_name}</p><p><strong>Модель:</strong> {product_model}</p><p><strong>Заводской номер:</strong> {serial_number}</p><p><strong>Дата изготовления:</strong> {manufacture_date}</p>', 3, 1),
('specs', 'ТЕХНИЧЕСКИЕ ХАРАКТЕРИСТИКИ', '<table border="1" cellpadding="5" cellspacing="0" width="100%"><tr><th>Параметр</th><th>Значение</th></tr>{specs_rows}</table>', 4, 1),
('warranty', 'ГАРАНТИЙНЫЕ ОБЯЗАТЕЛЬСТВА', '<p>Гарантийный срок эксплуатации: <strong>{warranty_period}</strong> мес.</p><p>Гарантия действительна при соблюдении правил эксплуатации.</p><p>Дата начала гарантии: {warranty_start}</p><p>Дата окончания гарантии: {warranty_end}</p>', 5, 1),
('safety', 'ТРЕБОВАНИЯ БЕЗОПАСНОСТИ', '<ul><li>К работе допускаются лица, изучившие инструкцию.</li><li>Запрещается эксплуатация неисправного изделия.</li><li>Регулярно проводите техническое обслуживание.</li></ul>', 6, 1),
('storage', 'УСЛОВИЯ ХРАНЕНИЯ И ТРАНСПОРТИРОВКИ', '<p>Изделие должно храниться в сухих помещениях при температуре от -20 до +40°C.</p><p>Транспортировка допускается любым видом крытого транспорта.</p>', 7, 1),
('acceptance', 'СВИДЕТЕЛЬСТВО О ПРИЕМКЕ', '<p>Изделие {product_name} заводской номер {serial_number} изготовлено и принято в соответствии с обязательными требованиями государственных стандартов, действующей технической документацией и признано годным для эксплуатации.</p><p><strong>Ответственное лицо:</strong> _________________ / М.П.</p>', 8, 1);

COMMIT;
