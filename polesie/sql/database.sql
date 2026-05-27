-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ОПТИМИЗИРОВАННАЯ СХЕМА И ДАННЫЕ
-- Таблицы создаются первыми, данные вставляются строго по зависимостям
-- ============================================

CREATE DATABASE IF NOT EXISTS polesie_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. УДАЛЕНИЕ СТАРЫХ ОБЪЕКТОВ
-- ============================================
DROP TABLE IF EXISTS `product_documents`;
DROP TABLE IF EXISTS `product_serial_numbers`;
DROP TABLE IF EXISTS `quality_checks`;
DROP TABLE IF EXISTS `production_tasks_materials`;
DROP TABLE IF EXISTS `production_tasks`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `production_costing`;
DROP TABLE IF EXISTS `production_schedules`;
DROP TABLE IF EXISTS `production_material_requirements`;
DROP TABLE IF EXISTS `production_plans`;
DROP TABLE IF EXISTS `demand_analysis`;
DROP TABLE IF EXISTS `work_centers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `material_categories`;
DROP TABLE IF EXISTS `contractors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `base_units`;

-- ============================================
-- 2. СОЗДАНИЕ ТАБЛИЦ (исправлены опечатки CREAT E -> CREATE)
-- ============================================
CREATE TABLE `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `symbol` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `role_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `contractors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `inn` VARCHAR(20) UNIQUE,
  `type` ENUM('supplier', 'customer', 'both') DEFAULT 'both',
  `contact_person` VARCHAR(100),
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_prod_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name_full` VARCHAR(200) NOT NULL,
  `name_short` VARCHAR(100),
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON,
  `current_stock` DECIMAL(15,3) DEFAULT 0,
  `min_stock` DECIMAL(15,3) DEFAULT 0,
  `location` VARCHAR(100),
  `supplier_id` INT,
  `last_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mat_category` FOREIGN KEY (`category_id`) REFERENCES `material_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON,
  `image` VARCHAR(255),
  `base_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT,
  `responsible_user_id` INT,
  `status` ENUM('new', 'processing', 'ready', 'shipped', 'cancelled') DEFAULT 'new',
  `order_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2),
  `notes` TEXT,
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `total` DECIMAL(15,2) NOT NULL,
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `production_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_number` VARCHAR(50) UNIQUE,
  `product_id` INT,
  `quantity_plan` DECIMAL(15,3),
  `quantity_fact` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `start_date` DATE,
  `end_date` DATE,
  `responsible_id` INT,
  CONSTRAINT `fk_task_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_user` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `production_tasks_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity_required` DECIMAL(15,3) NOT NULL,
  `quantity_used` DECIMAL(15,3) DEFAULT 0,
  CONSTRAINT `fk_ptm_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `quality_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT,
  `product_id` INT,
  `check_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `inspector_id` INT,
  `status` ENUM('pass', 'fail', 'rework') NOT NULL,
  `defect_description` TEXT,
  `quantity_checked` INT,
  `quantity_defective` INT DEFAULT 0,
  CONSTRAINT `fk_qc_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `product_serial_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `production_date` DATE,
  `task_id` INT,
  `status` ENUM('active', 'warranty', 'archived') DEFAULT 'active',
  `warranty_start` DATE,
  `warranty_end` DATE,
  `notes` TEXT,
  `technical_specs` JSON,
  `passport_data` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_psn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psn_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `product_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL,
  `document_type` ENUM('manual', 'certificate', 'test_report', 'warranty_card', 'other') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT,
  `mime_type` VARCHAR(100),
  `description` TEXT,
  `uploaded_by` INT,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pd_serial` FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === НОВЫЕ ТАБЛИЦЫ ПЛАНИРОВАНИЯ ===
CREATE TABLE `work_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `type` ENUM('assembly', 'packaging', 'quality_control', 'storage', 'other') DEFAULT 'other',
  `capacity_hours` DECIMAL(5,2) DEFAULT 8.0 COMMENT 'Плановая мощность в часах за смену',
  `workers_max` INT DEFAULT 10 COMMENT 'Максимальное количество рабочих',
  `hourly_rate` DECIMAL(10,2) DEFAULT 0.0 COMMENT 'Ставка в час',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Рабочие центры/участки производства';

CREATE TABLE `demand_analysis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `analysis_date` DATE NOT NULL,
  `period_type` ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
  `historical_avg` DECIMAL(15,3) DEFAULT 0 COMMENT 'Историческое среднее',
  `forecast_value` DECIMAL(15,3) DEFAULT 0 COMMENT 'Прогнозируемое значение',
  `trend_coefficient` DECIMAL(5,4) DEFAULT 1.0 COMMENT 'Коэффициент тренда',
  `seasonality_factor` DECIMAL(5,4) DEFAULT 1.0 COMMENT 'Сезонный фактор',
  `confidence_level` DECIMAL(5,2) DEFAULT 0.0 COMMENT 'Достоверность %',
  `variance_percent` DECIMAL(5,2) DEFAULT 0.0 COMMENT 'Отклонение %',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_da_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  INDEX `idx_da_product_date` (`product_id`, `analysis_date`),
  INDEX `idx_da_period` (`period_type`, `analysis_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Анализ и прогноз спроса на продукцию';

CREATE TABLE `production_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_number` VARCHAR(50) UNIQUE COMMENT 'Номер плана',
  `product_id` INT NOT NULL,
  `plan_date` DATE NOT NULL COMMENT 'Дата производства',
  `planned_quantity` DECIMAL(15,3) NOT NULL DEFAULT 0,
  `actual_quantity` DECIMAL(15,3) DEFAULT 0 COMMENT 'Фактически произведено',
  `demand_forecast` DECIMAL(15,3) DEFAULT 0 COMMENT 'Прогноз спроса',
  `priority` TINYINT DEFAULT 2 COMMENT 'Приоритет: 1-высокий, 2-средний, 3-низкий',
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `responsible_id` INT COMMENT 'Ответственный сотрудник',
  `order_id` INT COMMENT 'Связь с заказом (если есть)',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  INDEX `idx_pp_date` (`plan_date`),
  INDEX `idx_pp_status` (`status`),
  INDEX `idx_pp_product_date` (`product_id`, `plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Планы производства продукции';

CREATE TABLE `production_material_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `consumption_rate` DECIMAL(15,6) NOT NULL DEFAULT 0 COMMENT 'Норма расхода на ед.продукции',
  `required_quantity` DECIMAL(15,3) NOT NULL DEFAULT 0 COMMENT 'Требуется всего',
  `reserved_quantity` DECIMAL(15,3) DEFAULT 0 COMMENT 'Зарезервировано на складе',
  `actual_quantity` DECIMAL(15,3) DEFAULT 0 COMMENT 'Фактически списано',
  `unit_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Стоимость за единицу материала',
  `total_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Общая стоимость',
  `status` ENUM('pending', 'reserved', 'consumed', 'shortage') DEFAULT 'pending',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pmr_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmr_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  INDEX `idx_pmr_plan` (`plan_id`),
  INDEX `idx_pmr_material` (`material_id`),
  INDEX `idx_pmr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Потребность в материалах для планов производства';

CREATE TABLE `production_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `work_center_id` INT NOT NULL,
  `schedule_date` DATE NOT NULL,
  `shift_type` ENUM('morning', 'afternoon', 'night') DEFAULT 'morning' COMMENT 'Тип смены',
  `start_time` TIME NOT NULL DEFAULT '08:00:00',
  `end_time` TIME NOT NULL DEFAULT '17:00:00',
  `planned_hours` DECIMAL(5,2) DEFAULT 8.0,
  `actual_hours` DECIMAL(5,2) DEFAULT 0,
  `workers_count` INT DEFAULT 0,
  `efficiency_percent` DECIMAL(5,2) DEFAULT 100.0 COMMENT 'Эффективность %',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ps_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_work_center` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE CASCADE,
  INDEX `idx_ps_plan` (`plan_id`),
  INDEX `idx_ps_date` (`schedule_date`),
  INDEX `idx_ps_shift` (`shift_type`, `schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Графики работы производственных мощностей';

CREATE TABLE `production_costing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL UNIQUE COMMENT 'Связь с планом (один к одному)',
  `material_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Затраты на материалы',
  `labor_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Затраты на работу',
  `overhead_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Накладные расходы',
  `total_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Общая себестоимость',
  `cost_per_unit` DECIMAL(15,2) DEFAULT 0 COMMENT 'Себестоимость за единицу',
  `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans` (`id`) ON DELETE CASCADE,
  INDEX `idx_pc_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Расчет себестоимости производства';

-- ============================================
-- 3. ВСТАВКА ДАННЫХ (СТРОГО ПО ЗАВИСИМОСТЯМ)
-- ============================================

-- 3.1. Базовые справочники
INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'), ('Килограмм', 'kg', 'кг'), ('Метр', 'm', 'м'),
('Тонна', 't', 'т'), ('Литр', 'l', 'л'), ('Комплект', 'set', 'компл');

INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all": true}'),
('Директор', 'director', 'Руководство', '{"all": true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders": ["read", "create"], "products": ["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production": ["read", "create"], "materials": ["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse": ["read", "create"], "materials": ["read", "update"]}');

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2),
('ivanov', 'admin123', 'Иванов Иван', 'ivanov@polesie.by', 3),
('petrov', 'admin123', 'Петров Петр', 'petrov@polesie.by', 4),
('sidorov', 'admin123', 'Сидоров Сидор', 'sidorov@polesie.by', 5);

INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3');

INSERT INTO `material_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Металлы', 'METAL', 'Черные и цветные металлы'),
(1, 'Прутки', 'METAL_BAR', 'Стальные прутки круглого сечения'),
(1, 'Листовой прокат', 'METAL_SHEET', 'Листы стальные горячекатаные'),
(1, 'Чугун', 'METAL_CAST', 'Чугунные заготовки'),
(NULL, 'Электротехника', 'ELECTRO', 'Электротехнические материалы'),
(5, 'Провода', 'ELECTRO_WIRE', 'Медные и алюминиевые провода'),
(5, 'Шины', 'ELECTRO_BUS', 'Медные шины'),
(NULL, 'Крепеж', 'FASTENER', 'Болты, гайки, шайбы'),
(8, 'Болты', 'FAST_BOLT', 'Болты различных классов прочности'),
(8, 'Гайки', 'FAST_NUT', 'Гайки шестигранные'),
(NULL, 'Подшипники', 'BEARING', 'Подшипники качения');

INSERT INTO `product_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные генераторы'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(NULL, 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие');

INSERT INTO `work_centers` (`code`, `name`, `type`, `capacity_hours`, `workers_max`, `hourly_rate`) VALUES
('WC-001', 'Сборочный цех №1', 'assembly', 8.0, 15, 25.00),
('WC-002', 'Сборочный цех №2', 'assembly', 8.0, 12, 25.00),
('WC-003', 'Упаковочный участок', 'packaging', 8.0,  8, 20.00),
('WC-004', 'Контроль качества', 'quality_control', 8.0, 4, 30.00),
('WC-005', 'Склад готовой продукции', 'storage', 8.0, 6, 18.00);

-- 3.2. Материалы и Продукция
INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "45", "length_m": 6, "surface": "калиброванный", "gost": "10702-78"}', 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "40Х", "length_m": 6, "surface": "горячекатаный", "gost": "2590-2006"}', 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, '{"diameter_mm": 20, "steel_grade": "45", "length_m": 6, "surface": "калиброванный", "gost": "10702-78"}', 450.00, 50.00, 'Склад №1, Секция Б', 1, 4.80, 'BYN'),
('ST-SHEET-3-08', 'Лист стальной 3мм', 'Лист 3мм', 3, 2, '{"thickness_mm": 3, "width_mm": 1500, "length_mm": 6000, "steel_grade": "Ст3сп", "gost": "19903-90"}', 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
('ST-SHEET-5-10', 'Лист стальной 5мм', 'Лист 5мм', 3, 2, '{"thickness_mm": 5, "width_mm": 1500, "length_mm": 6000, "steel_grade": "Ст3сп", "gost": "19903-90"}', 890.00, 150.00, 'Склад №2, Секция А', 1, 3.50, 'BYN'),
('CAST-IRON-CH20', 'Чугун серый СЧ20', 'Чугун СЧ20', 4, 2, '{"grade": "СЧ20", "hardness_hb": "170-220", "gost": "1412-85"}', 500.00, 100.00, 'Склад №3', 1, 2.80, 'BYN'),
('WIRE-CU-2.5', 'Провод медный 2.5мм²', 'Провод 2.5', 6, 3, '{"cross_section_mm2": 2.5, "material": "медь", "insulation": "ПВХ", "gost": "6323-79"}', 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('WIRE-CU-4.0', 'Провод медный 4мм²', 'Провод 4.0', 6, 3, '{"cross_section_mm2": 4.0, "material": "медь", "insulation": "ПВХ", "gost": "6323-79"}', 1200.00, 250.00, 'Склад №4, Секция А', 2, 1.85, 'BYN'),
('BUS-CU-20x3', 'Шина медная 20x3мм', 'Шина 20x3', 7, 3, '{"width_mm": 20, "thickness_mm": 3, "material": "М1", "gost": "434-73"}', 250.00, 50.00, 'Склад №4, Секция Б', 2, 45.00, 'BYN'),
('BOLT-M10x50', 'Болт М10х50 8.8', 'Болт М10х50', 9, 1, '{"thread": "M10", "length_mm": 50, "strength_class": "8.8", "coating": "цинк", "gost": "7798-70"}', 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('BOLT-M12x60', 'Болт М12х60 8.8', 'Болт М12х60', 9, 1, '{"thread": "M12", "length_mm": 60, "strength_class": "8.8", "coating": "цинк", "gost": "7798-70"}', 3500.00, 800.00, 'Склад №5, Ящик 1', 3, 0.52, 'BYN'),
('NUT-M10', 'Гайка М10 8', 'Гайка М10', 10, 1, '{"thread": "M10", "strength_class": "8", "coating": "цинк", "gost": "5915-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
('NUT-M12', 'Гайка М12 8', 'Гайка М12', 10, 1, '{"thread": "M12", "strength_class": "8", "coating": "цинк", "gost": "5915-70"}', 4500.00, 1000.00, 'Склад №5, Ящик 2', 3, 0.22, 'BYN'),
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, '{"inner_d_mm": 25, "outer_d_mm": 52, "width_mm": 15, "type": "шариковый", "seal": "2RS"}', 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('BRG-6305', 'Подшипник 6305-2RS', 'Подшипник 6305', 11, 1, '{"inner_d_mm": 25, "outer_d_mm": 62, "width_mm": 17, "type": "шариковый", "seal": "2RS"}', 120.00, 25.00, 'Склад №5, Ящик 3', 2, 12.30, 'BYN');

INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('ADM-80A4', 'Двигатель АДМ 80A4', 1, 1, '{"power_kw": 1.1, "rpm": 1500, "voltage_v": 380, "frame": "80A", "efficiency_class": "IE2", "mounting": "IM1081"}', 'motor_adm80a4.jpg', 350.00, 'BYN', TRUE),
('ADM-90L4', 'Двигатель АДМ 90L4', 1, 1, '{"power_kw": 2.2, "rpm": 1500, "voltage_v": 380, "frame": "90L", "efficiency_class": "IE2", "mounting": "IM1081"}', 'motor_adm90l4.jpg', 480.00, 'BYN', TRUE),
('ADM-100L4', 'Двигатель АДМ 100L4', 1, 1, '{"power_kw": 4.0, "rpm": 1500, "voltage_v": 380, "frame": "100L", "efficiency_class": "IE2", "mounting": "IM1081"}', 'motor_adm100l4.jpg', 650.00, 'BYN', TRUE),
('ADM-112M4', 'Двигатель АДМ 112M4', 1, 1, '{"power_kw": 5.5, "rpm": 1500, "voltage_v": 380, "frame": "112M", "efficiency_class": "IE2", "mounting": "IM1081"}', 'motor_adm112m4.jpg', 820.00, 'BYN', TRUE),
('DG-5000', 'Генератор дизельный 5кВт', 2, 1, '{"power_kw": 5, "fuel_type": "дизель", "voltage_v": 220, "phase": 1, "start": "электростартер"}', 'generator_5kw.jpg', 2500.00, 'BYN', TRUE),
('DG-10000', 'Генератор дизельный 10кВт', 2, 1, '{"power_kw": 10, "fuel_type": "дизель", "voltage_v": 380, "phase": 3, "start": "электростартер"}', 'generator_10kw.jpg', 4200.00, 'BYN', TRUE),
('TM-25', 'Трансформатор ТМ-25', 3, 1, '{"power_kva": 25, "voltage_primary_kv": 10, "voltage_secondary_kv": 0.4, "cooling": "масляное"}', 'transformer_25.jpg', 3500.00, 'BYN', TRUE),
('TM-63', 'Трансформатор ТМ-63', 3, 1, '{"power_kva": 63, "voltage_primary_kv": 10, "voltage_secondary_kv": 0.4, "cooling": "масляное"}', 'transformer_63.jpg', 5200.00, 'BYN', TRUE),
('SCH-100A', 'Щит распределительный 100А', 4, 1, '{"current_a": 100, "circuits": 12, "protection_ip": "IP54", "material": "металл"}', 'switchgear_100a.jpg', 450.00, 'BYN', TRUE),
('SCH-250A', 'Щит распределительный 250А', 4, 1, '{"current_a": 250, "circuits": 24, "protection_ip": "IP54", "material": "металл"}', 'switchgear_250a.jpg', 850.00, 'BYN', TRUE),
('SP-BRG-6205', 'Подшипник 6205 (запчасть)', 5, 1, '{"compatible_with": "ADM-80A4, ADM-90L4", "inner_d_mm": 25, "outer_d_mm": 52}', 'spare_brg6205.jpg', 15.00, 'BYN', TRUE),
('SP-FAN-80', 'Вентилятор двигателя 80', 5, 1, '{"compatible_with": "ADM-80A4", "diameter_mm": 180, "material": "пластик"}', 'spare_fan80.jpg', 25.00, 'BYN', TRUE),
('SP-TERM-BOX', 'Клеммная коробка', 5, 1, '{"compatible_with": "ADM-80A4, ADM-90L4", "terminals": 6, "material": "алюминий"}', 'spare_termbox.jpg', 45.00, 'BYN', TRUE);

-- 3.3. Операционные данные (Заказы, Задачи, Анализы)
INSERT INTO `orders` (`order_number`, `customer_id`, `status`, `order_date`, `total_amount`, `notes`) VALUES
('ORD-2024-001', 4, 'processing', '2024-01-15', 2450.00, 'Срочный заказ'),
('ORD-2024-002', 5, 'ready', '2024-01-18', 1850.00, 'Отгрузка со склада'),
('ORD-2024-003', 4, 'new', '2024-01-20', 3200.00, 'Новый заказ на двигатели');

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`) VALUES
(1, 1, 2, 350.00, 700.00), (1, 2, 3, 480.00, 1440.00), (1, 11, 10, 15.00, 150.00),
(2, 5, 1, 2500.00, 2500.00), (2, 12, 5, 25.00, 125.00),
(3, 3, 4, 650.00, 2600.00), (3, 4, 1, 820.00, 820.00);

INSERT INTO `production_tasks` (`task_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `responsible_id`) VALUES
('TASK-2024-001', 1, 10, 10, 'completed', '2024-01-10', '2024-01-12', 3),
('TASK-2024-002', 2, 5, 5, 'completed', '2024-01-13', '2024-01-15', 3),
('TASK-2024-003', 3, 8, 6 , 'in_progress', '2024-01-16', '2024-01-20', 3),
('TASK-2024-004', 4, 3, 0, 'planned', '2024-01-22', '2024-01-25', 3),
('TASK-2024-005', 5, 2, 0, 'planned', '2024-01-25', '2024-01-28', 3);

INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`) VALUES
(1, 1, 60, 60), (1, 7, 100, 100), (1, 14, 20, 20),
(2, 2, 30, 30), (2, 8, 75, 75), (2, 15, 10, 10),
(3, 3, 48, 36), (3, 9, 24, 18),
(4, 4, 18, 0), (4, 10, 48, 0);

INSERT INTO `quality_checks` (`task_id`, `product_id`, `check_date`, `inspector_id`, `status`, `defect_description`, `quantity_checked`, `quantity_defective`) VALUES
(1, 1, '2024-01-12 14:00:00', 2, 'pass', NULL, 10, 0),
(2, 2, '2024-01-15 15:30:00', 2, 'pass', NULL, 5, 0),
(3, 3, '2024-01-18 10:00:00', 2, 'pass', NULL, 6, 0),
(3, 3, '2024-01-19 11:00:00', 2, 'fail', 'Превышен уровень вибрации', 2, 2),
(4, 4, '2024-01-20 09:00:00', 2, 'pass', NULL, 3, 0),
(5, 5, '2024-01-22 16:00:00', 2, 'rework', 'Требуется балансировка ротора', 2, 1),
(5, 5, '2024-01-23 10:00:00', 2, 'pass', NULL, 1, 0);

INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`) VALUES
(1, 'SN-ADM80A4-2024-0001', '2024-01-12', 1, 'active'),
(1, 'SN-ADM80A4-2024-0002', '2024-01-12', 1, 'active'),
(2, 'SN-ADM90L4-2024-0001', '2024-01-15', 2, 'active'),
(3, 'SN-ADM100L4-2024-0001', '2024-01-18', 3, 'active'),
(5, 'SN-DG5000-2024-0001', '2024-01-22', 5, 'warranty');

INSERT INTO `demand_analysis` (`product_id`, `analysis_date`, `period_type`, `historical_avg`, `forecast_value`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `variance_percent`)
SELECT id, CURDATE(), 'weekly', ROUND(RAND() * 50 + 10, 0), ROUND(RAND() * 60 + 15, 0), ROUND(0.9 + RAND() * 0.3, 3), ROUND(0.85 + RAND() * 0.3, 3), ROUND(75 + RAND() * 20, 2), ROUND(-15 + RAND() * 30, 2)
FROM products WHERE is_active = 1 LIMIT 10;

INSERT INTO `demand_analysis` (`product_id`, `analysis_date`, `period_type`, `historical_avg`, `forecast_value`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `variance_percent`)
SELECT id, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'daily', ROUND(RAND() * 20 + 5, 0), ROUND(RAND() * 25 + 8, 0), ROUND(0.95 + RAND() * 0.2, 3), 1.0, ROUND(80 + RAND() * 15, 2), ROUND(-10 + RAND() * 20, 2)
FROM products WHERE is_active = 1 LIMIT 8;

-- 3.4. Планирование (вставляются после products, users, orders)
INSERT INTO `production_plans` (`plan_number`, `product_id`, `plan_date`, `planned_quantity`, `demand_forecast`, `priority`, `status`, `responsible_id`, `notes`) VALUES
('PLAN-2025-001', 1, CURDATE(), 25, 22, 1, 'in_progress', 1, 'Срочный заказ'),
('PLAN-2025-002', 2, CURDATE(), 15, 18, 2, 'planned', 2, NULL),
('PLAN-2025-003', 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 30, 28, 1, 'planned', 1, 'Плановое производство'),
('PLAN-2025-004', 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 20, 22, 2, 'planned', 3, NULL),
('PLAN-2025-005', 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 40, 35, 2, 'planned', 2, 'Большая партия'),
('PLAN-2025-006', 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 12, 15, 3, 'planned', 1, NULL ),
('PLAN-2025-007', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 18, 20, 1, 'planned', 3, 'Приоритетный заказ'),
('PLAN-2025-008', 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 25, 28, 2, 'planned', 2, NULL),
('PLAN-2025-009', 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 22, 22, 2, 'planned', 1, NULL),
('PLAN-2025-010', 4, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 35, 35, 3, 'planned', 3, 'На склад');

INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 1, id, CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END,
ROUND(25 * CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END, 2),
COALESCE(last_price, 10), ROUND(25 * CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END * COALESCE(last_price, 10), 2), 'reserved'
FROM materials LIMIT 5;

INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 2, id, CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END,
ROUND(15 * CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END, 2),
COALESCE(last_price, 15), ROUND(15 * CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END * COALESCE(last_price, 15), 2), 'pending'
FROM materials LIMIT 4;

INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 3, id, 0.5, ROUND(30 * 0.5, 2), COALESCE(last_price, 12), ROUND(30 * 0.5 * COALESCE(last_price, 12), 2), 'pending'
FROM materials LIMIT 6;

INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 4, id, 0.4, ROUND(20 * 0.4, 2), COALESCE(last_price, 10), ROUND(20 * 0.4 * COALESCE(last_price, 10), 2), 'pending'
FROM materials LIMIT 4;

INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 5, id, 0.6, ROUND(40 * 0.6, 2), COALESCE(last_price, 8), ROUND(40 * 0.6 * COALESCE(last_price, 8), 2), 'pending'
FROM materials LIMIT 7;

INSERT INTO `production_schedules` (`plan_id`, `work_center_id`, `schedule_date`, `shift_type`, `start_time`, `end_time`, `planned_hours`, `workers_count`, `efficiency_percent`) VALUES
(1, 1, CURDATE(), 'morning', '08:00:00', '17:00:00', 8.0, 8, 95.0),
(1, 3, CURDATE(), 'afternoon', '14:00:00', '22:00:00', 8.0, 4, 90.0),
(2, 2, CURDATE(), 'morning', '08:00:00', '17:00:00', 8.0, 6, 100.0),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 10, 0),
(3, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 3, 0),
(4, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'afternoon', '14:00:00', '22:00:00', 8.0, 7, 0),
(5, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 12, 0),
(5, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 5, 0),
(6, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'afternoon', '14:00:00', '22:00:00', 8.0, 4, 0),
(7, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 9, 0),
(8, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 8, 0),
(9, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 7, 0),
(10, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 6, 0);

INSERT INTO `production_costing` (`plan_id`, `material_cost`, `labor_cost`, `overhead_cost`, `total_cost`, `cost_per_unit`)
SELECT pp.id, COALESCE(mat.total_material, 0), ROUND(COALESCE(mat.total_material, 0) * 0.3, 2),
ROUND(COALESCE(mat.total_material, 0) * 0.15, 2), ROUND(COALESCE(mat.total_material, 0) * 1.45, 2),
ROUND(ROUND(COALESCE(mat.total_material, 0) * 1.45, 2) / pp.planned_quantity, 2)
FROM production_plans pp
LEFT JOIN (SELECT plan_id, SUM(total_cost) as total_material FROM production_material_requirements GROUP BY plan_id) mat ON pp.id = mat.plan_id
WHERE pp.id BETWEEN 1 AND 10;

-- 3.5. Финальные обновления
UPDATE `production_material_requirements` SET status = 'shortage' WHERE plan_id IN (3, 5) AND material_id IN (1, 2);
UPDATE `production_material_requirements` SET reserved_quantity = required_quantity, status = 'reserved' WHERE plan_id = 1;

-- ============================================
-- 4. ПРЕДСТАВЛЕНИЯ (VIEWS)
-- ============================================
CREATE OR REPLACE VIEW `v_production_plan_summary` AS
SELECT pp.id, pp.plan_number, pp.product_id, p.name as product_name, p.article as product_article,
pp.plan_date, pp.planned_quantity, pp.actual_quantity, pp.demand_forecast, pp.priority, pp.status,
COALESCE(pc.total_cost, 0) as total_cost, COALESCE(pc.cost_per_unit, 0) as cost_per_unit, u.full_name as responsible_name
FROM production_plans pp
JOIN products p ON pp.product_id = p.id
LEFT JOIN production_costing pc ON pp.id = pc.plan_id
LEFT JOIN users u ON pp.responsible_id = u.id;

CREATE OR REPLACE VIEW `v_material_requirements_detail` AS
SELECT pmr.id, pmr.plan_id, pp.plan_number, pp.product_id, p.name as product_name, pp.plan_date,
pmr.material_id, m.name_full as material_name, m.code as material_code, m.current_stock,
pmr.consumption_rate, pmr.required_quantity, pmr.reserved_quantity, pmr.actual_quantity,
pmr.unit_cost, pmr.total_cost, pmr.status,
CASE WHEN pmr.required_quantity > m.current_stock THEN 'shortage' ELSE 'ok' END as stock_status
FROM production_material_requirements pmr
JOIN production_plans pp ON pmr.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
JOIN materials m ON pmr.material_id = m.id;

CREATE OR REPLACE VIEW `v_work_center_load` AS
SELECT ps.id, ps.plan_id, ps.work_center_id, wc.name as work_center_name, ps.schedule_date,
ps.shift_type, ps.start_time, ps.end_time, ps.planned_hours, ps.actual_hours,
ps.workers_count, ps.efficiency_percent, pp.plan_number, p.name as product_name
FROM production_schedules ps
JOIN work_centers wc ON ps.work_center_id = wc.id
JOIN production_plans pp ON ps.plan_id = pp.id
JOIN products p ON pp.product_id = p.id;

-- ============================================
-- 5. ЗАВЕРШЕНИЕ
-- ============================================
SET FOREIGN_KEY_CHECKS = 1;