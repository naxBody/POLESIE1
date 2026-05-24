-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ПЛАН ПРОИЗВОДСТВА
-- Единый SQL-файл: схема + 5 таблиц + представления + тестовые данные
-- Совместим с основной схемой из schema.sql
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- ЧАСТЬ 1: ОСНОВНАЯ СХЕМА (если таблицы еще не созданы)
-- ============================================

-- Создаем основную базу данных и таблицы только если они не существуют
-- Это позволяет использовать файл как standalone или как дополнение

CREATE DATABASE IF NOT EXISTS polesie_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE polesie_production;

-- 1. ЕДИНИЦЫ ИЗМЕРЕНИЯ
CREATE TABLE IF NOT EXISTS `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `symbol` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. РОЛИ ПОЛЬЗОВАТЕЛЕЙ
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ПОЛЬЗОВАТЕЛИ
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `role_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. КОНТРАГЕНТЫ
CREATE TABLE IF NOT EXISTS `contractors` (
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

-- 5. КАТЕГОРИИ МАТЕРИАЛОВ
CREATE TABLE IF NOT EXISTS `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. КАТЕГОРИИ ПРОДУКЦИИ
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_prod_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. МАТЕРИАЛЫ
CREATE TABLE IF NOT EXISTS `materials` (
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
  CONSTRAINT `fk_mat_category` FOREIGN KEY (`category_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ПРОДУКЦИЯ
CREATE TABLE IF NOT EXISTS `products` (
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
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЧАСТЬ 2: ТАБЛИЦЫ ПЛАНА ПРОИЗВОДСТВА (5 таблиц)
-- ============================================

-- 1. ТАБЛИЦА: АНАЛИЗ СПРОСА (demand_analysis)
DROP TABLE IF EXISTS `demand_analysis`;
CREATE TABLE `demand_analysis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `forecast_quantity` DECIMAL(15,3) NOT NULL DEFAULT 0,
  `actual_quantity` DECIMAL(15,3) DEFAULT 0,
  `trend_coefficient` DECIMAL(5,4) DEFAULT 1.0000,
  `seasonality_factor` DECIMAL(5,4) DEFAULT 1.0000,
  `confidence_level` DECIMAL(5,2) DEFAULT 0.00,
  `analysis_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  CONSTRAINT `fk_da_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Анализ и прогноз спроса по продукции';

-- 2. ТАБЛИЦА: ПЛАНЫ ПРОИЗВОДСТВА (production_plans)
DROP TABLE IF EXISTS `production_plans`;
CREATE TABLE `production_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_number` VARCHAR(50) NOT NULL UNIQUE,
  `product_id` INT NOT NULL,
  `quantity_plan` DECIMAL(15,3) NOT NULL,
  `quantity_fact` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('draft', 'approved', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `demand_analysis_id` INT,
  `total_material_cost` DECIMAL(15,2) DEFAULT 0,
  `total_labor_cost` DECIMAL(15,2) DEFAULT 0,
  `total_overhead_cost` DECIMAL(15,2) DEFAULT 0,
  `total_cost` DECIMAL(15,2) DEFAULT 0,
  `cost_per_unit` DECIMAL(15,2) DEFAULT 0,
  `created_by` INT,
  `approved_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` TEXT,
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_demand` FOREIGN KEY (`demand_analysis_id`) REFERENCES `demand_analysis`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_created` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_approved` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Планы производства с расчетом себестоимости';

-- 3. ТАБЛИЦА: ПОТРЕБНОСТЬ В МАТЕРИАЛАХ (production_material_requirements)
DROP TABLE IF EXISTS `production_material_requirements`;
CREATE TABLE `production_material_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `norm_per_unit` DECIMAL(15,5) NOT NULL COMMENT 'Норма расхода на единицу продукции',
  `quantity_required` DECIMAL(15,3) NOT NULL COMMENT 'Общее количество для плана',
  `quantity_available` DECIMAL(15,3) DEFAULT 0 COMMENT 'Доступно на складе',
  `quantity_to_purchase` DECIMAL(15,3) DEFAULT 0 COMMENT 'Требуется закупить',
  `estimated_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Стоимость материалов',
  `supplier_id` INT,
  `is_critical` BOOLEAN DEFAULT FALSE COMMENT 'Дефицитный материал',
  `notes` TEXT,
  CONSTRAINT `fk_pmr_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmr_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Потребность в материалах для производственных планов';

-- 4. ТАБЛИЦА: ГРАФИКИ ПРОИЗВОДСТВА (production_schedules)
DROP TABLE IF EXISTS `production_schedules`;
CREATE TABLE `production_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `work_center` VARCHAR(100) NOT NULL COMMENT 'Участок/линия/станок',
  `schedule_date` DATE NOT NULL,
  `shift` ENUM('day', 'evening', 'night') DEFAULT 'day',
  `hour_start` TIME NOT NULL DEFAULT '08:00:00',
  `hour_end` TIME NOT NULL DEFAULT '17:00:00',
  `planned_quantity` DECIMAL(15,3) DEFAULT 0,
  `fact_quantity` DECIMAL(15,3) DEFAULT 0,
  `worker_count` INT DEFAULT 0,
  `responsible_id` INT,
  `status` ENUM('planned', 'in_progress', 'completed', 'blocked') DEFAULT 'planned',
  `downtime_minutes` INT DEFAULT 0 COMMENT 'Простои в минутах',
  `efficiency_percent` DECIMAL(5,2) DEFAULT 100.00,
  `notes` TEXT,
  CONSTRAINT `fk_ps_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='График производства по сменам и участкам';

-- 5. ТАБЛИЦА: КАЛЬКУЛЯЦИЯ СЕБЕСТОИМОСТИ (production_costing)
DROP TABLE IF EXISTS `production_costing`;
CREATE TABLE `production_costing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `cost_type` ENUM('material', 'labor', 'overhead', 'energy', 'other') NOT NULL,
  `cost_item` VARCHAR(200) NOT NULL COMMENT 'Статья затрат',
  `amount` DECIMAL(15,2) NOT NULL,
  `unit_cost` DECIMAL(15,4) DEFAULT 0,
  `total_cost` DECIMAL(15,2) NOT NULL,
  `allocation_base` VARCHAR(100) COMMENT 'База распределения',
  `percentage` DECIMAL(5,2) DEFAULT 0 COMMENT '% от общей себестоимости',
  `calculation_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Детальная калькуляция себестоимости';

-- ============================================
-- ЧАСТЬ 3: ПРЕДСТАВЛЕНИЯ (VIEWS) ДЛЯ ОТЧЕТОВ
-- ============================================

-- VIEW 1: Сводка по производственным планам
DROP VIEW IF EXISTS `v_production_plan_summary`;
CREATE VIEW `v_production_plan_summary` AS
SELECT 
    pp.id,
    pp.plan_number,
    p.name AS product_name,
    p.article AS product_article,
    pp.quantity_plan,
    pp.quantity_fact,
    ROUND((pp.quantity_fact / pp.quantity_plan) * 100, 2) AS completion_percent,
    pp.status,
    pp.start_date,
    pp.end_date,
    DATEDIFF(pp.end_date, pp.start_date) AS duration_days,
    pp.total_material_cost,
    pp.total_labor_cost,
    pp.total_overhead_cost,
    pp.total_cost,
    pp.cost_per_unit,
    da.forecast_quantity,
    da.trend_coefficient,
    CASE 
        WHEN pp.quantity_plan >= da.forecast_quantity THEN 'OK'
        ELSE 'DEFICIT'
    END AS demand_status
FROM production_plans pp
JOIN products p ON pp.product_id = p.id
LEFT JOIN demand_analysis da ON pp.demand_analysis_id = da.id
ORDER BY pp.created_at DESC;

-- VIEW 2: Дефицит материалов
DROP VIEW IF EXISTS `v_material_shortage`;
CREATE VIEW `v_material_shortage` AS
SELECT 
    pp.plan_number,
    p.name AS product_name,
    m.name_full AS material_name,
    m.code AS material_code,
    pmr.norm_per_unit,
    pmr.quantity_required,
    pmr.quantity_available,
    pmr.quantity_to_purchase,
    pmr.estimated_cost,
    c.name AS supplier_name,
    pmr.is_critical,
    CASE 
        WHEN pmr.quantity_available < pmr.quantity_required THEN 'DEFICIT'
        ELSE 'OK'
    END AS availability_status
FROM production_material_requirements pmr
JOIN production_plans pp ON pmr.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
JOIN materials m ON pmr.material_id = m.id
LEFT JOIN contractors c ON pmr.supplier_id = c.id
WHERE pmr.quantity_to_purchase > 0 OR pmr.quantity_available < pmr.quantity_required
ORDER BY pmr.is_critical DESC, pmr.quantity_to_purchase DESC;

-- VIEW 3: Загрузка производственных мощностей
DROP VIEW IF EXISTS `v_capacity_load`;
CREATE VIEW `v_capacity_load` AS
SELECT 
    ps.work_center,
    ps.schedule_date,
    ps.shift,
    COUNT(ps.id) AS total_shifts,
    SUM(ps.planned_quantity) AS total_planned_qty,
    SUM(ps.fact_quantity) AS total_fact_qty,
    SUM(ps.worker_count) AS total_workers,
    ROUND(AVG(ps.efficiency_percent), 2) AS avg_efficiency,
    SUM(ps.downtime_minutes) AS total_downtime_minutes,
    GROUP_CONCAT(DISTINCT pp.plan_number SEPARATOR ', ') AS plan_numbers
FROM production_schedules ps
JOIN production_plans pp ON ps.plan_id = pp.id
GROUP BY ps.work_center, ps.schedule_date, ps.shift
ORDER BY ps.schedule_date, ps.work_center, ps.shift;

-- VIEW 4: Детальная себестоимость
DROP VIEW IF EXISTS `v_costing_detail`;
CREATE VIEW `v_costing_detail` AS
SELECT 
    pp.plan_number,
    p.name AS product_name,
    pp.quantity_plan,
    pc.cost_type,
    pc.cost_item,
    pc.amount,
    pc.unit_cost,
    pc.total_cost,
    pc.percentage,
    pp.total_cost AS plan_total_cost,
    pp.cost_per_unit
FROM production_costing pc
JOIN production_plans pp ON pc.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
ORDER BY pp.plan_number, pc.cost_type, pc.cost_item;

-- VIEW 5: KPI производства
DROP VIEW IF EXISTS `v_production_kpi`;
CREATE VIEW `v_production_kpi` AS
SELECT 
    pp.plan_number,
    p.name AS product_name,
    pp.status,
    ROUND((pp.quantity_fact / pp.quantity_plan) * 100, 2) AS plan_completion_pct,
    ROUND(((pp.total_cost - pp.total_material_cost - pp.total_labor_cost - pp.total_overhead_cost) / 
           NULLIF(pp.total_cost, 0)) * 100, 2) AS cost_variance_pct,
    COALESCE(ROUND((SELECT AVG(efficiency_percent) FROM production_schedules WHERE plan_id = pp.id), 2), 0) AS avg_efficiency,
    COALESCE((SELECT SUM(downtime_minutes) FROM production_schedules WHERE plan_id = pp.id), 0) AS total_downtime_min,
    COALESCE((SELECT COUNT(*) FROM production_material_requirements WHERE plan_id = pp.id AND is_critical = TRUE), 0) AS critical_materials_count,
    DATEDIFF(pp.end_date, CURDATE()) AS days_remaining
FROM production_plans pp
JOIN products p ON pp.product_id = p.id
ORDER BY pp.created_at DESC;

-- ============================================
-- ЧАСТЬ 4: СПРАВОЧНЫЕ ДАННЫЕ
-- ============================================

-- Добавляем единицы измерения
INSERT IGNORE INTO `base_units` (`id`, `name`, `code`, `symbol`) VALUES
(1, 'Килограмм', 'kg', 'кг'),
(2, 'Метр', 'm', 'м'),
(3, 'Литр', 'l', 'л'),
(4, 'Штука', 'pcs', 'шт'),
(5, 'Метр погонный', 'lm', 'м.п.');

-- Добавляем роли пользователей
INSERT IGNORE INTO `user_roles` (`id`, `name`, `code`, `description`) VALUES
(1, 'Директор', 'director', 'Полный доступ ко всем модулям'),
(2, 'Начальник производства', 'production_manager', 'Управление производственными планами'),
(3, 'Мастер участка', 'foreman', 'Управление графиками и задачами'),
(4, 'Инженер', 'engineer', 'Техническая документация и качество'),
(5, 'Кладовщик', 'storekeeper', 'Учет материалов');

-- Добавляем категории материалов
INSERT IGNORE INTO `material_categories` (`id`, `name`, `code`, `description`) VALUES
(1, 'Сырье и материалы', 'raw_material', 'Основные производственные материалы'),
(2, 'Комплектующие', 'components', 'Покупные изделия и компоненты'),
(3, 'Вспомогательные материалы', 'auxiliary', 'Вспомогательные расходные материалы');

-- Добавляем категории продукции
INSERT IGNORE INTO `product_categories` (`id`, `name`, `code`, `description`) VALUES
(1, 'Трансформаторы', 'transformer', 'Силовые трансформаторы'),
(2, 'Распределительные устройства', 'switchgear', 'Ячейки КСО, комплектные устройства'),
(3, 'Щитовое оборудование', 'distribution_board', 'Распределительные щиты');

-- Добавляем контрагентов (поставщиков)
INSERT IGNORE INTO `contractors` (`id`, `name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
(1, 'ООО "МедьПром"', '7701234567', 'supplier', 'Смирнов А.В.', '+7 (495) 111-11-11', 'info@medprom.ru', 'г. Москва, ул. Промышленная, 1'),
(2, 'АО "АлюминийСнаб"', '7702345678', 'supplier', 'Кузнецов Б.Г.', '+7 (343) 222-22-22', 'sales@aluminium.ru', 'г. Екатеринбург, ул. Металлургов, 5'),
(3, 'ООО "НефтеПродукт"', '7703456789', 'supplier', 'Петров В.С.', '+7 (831) 333-33-33', 'order@nefteprodukt.ru', 'г. Нижний Новгород, ул. Нефтяная, 10'),
(4, 'ПАО "СтальИнвест"', '7704567890', 'supplier', 'Иванова М.К.', '+7 (351) 444-44-44', 'zakaz@stalinvest.ru', 'г. Челябинск, пр. Металлургов, 25'),
(5, 'ООО "ЭлектроКомплект"', '7705678901', 'supplier', 'Сидоров П.А.', '+7 (812) 555-55-55', 'info@electrokomplekt.ru', 'г. Санкт-Петербург, ул. Электротехническая, 8'),
(6, 'ЗАО "КабельТорг"', '7706789012', 'supplier', 'Волков Д.М.', '+7 (843) 666-66-66', 'sales@cabletorg.ru', 'г. Казань, ул. Кабельная, 3'),
(7, 'ИП Петров А.С.', '7707890123', 'supplier', 'Петров А.С.', '+7 (846) 777-77-77', 'petrov@mail.ru', 'г. Самара, ул. Заводская, 15');

-- Добавляем материалы
INSERT IGNORE INTO `materials` (`id`, `code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `current_stock`, `min_stock`, `last_price`, `supplier_id`) VALUES
(1, 'MED-001', 'Медь обмоточная ММФ-1', 'Медь обмоточная', 1, 1, 50.000, 10.000, 5000.00, 1),
(2, 'ALU-002', 'Алюминий конструкционный АД31', 'Алюминий', 1, 1, 80.000, 20.000, 2250.00, 2),
(3, 'OIL-003', 'Масло трансформаторное ГК', 'Масло трансформаторное', 1, 3, 5000.000, 1000.000, 14.00, 3),
(4, 'STL-004', 'Сталь электротехническая 3406', 'Сталь электротехническая', 1, 1, 500.000, 100.000, 320.00, 4),
(5, 'ISO-005', 'Изоляция лакоткань ЛЭС', 'Изоляционные материалы', 1, 1, 100.000, 25.000, 290.00, 1),
(6, 'ELC-006', 'Комплектующие электрические', 'Комплектующие', 2, 4, 500.000, 100.000, 200.00, 5),
(7, 'CAB-007', 'Кабель силовой ВВГнг 3х185', 'Кабель силовой', 2, 2, 200.000, 50.000, 1200.00, 6),
(8, 'FST-008', 'Крепеж М12х50 оцинкованный', 'Крепежные изделия', 3, 4, 1000.000, 200.000, 32.00, 4),
(9, 'BOX-009', 'Корпус металлический ЩРн-IP54', 'Корпуса металлические', 2, 4, 50.000, 10.000, 450.00, 7);

-- Добавляем продукцию
INSERT IGNORE INTO `products` (`id`, `article`, `name`, `category_id`, `base_unit_id`, `base_price`) VALUES
(1, 'TMG-10-630', 'Трансформатор ТМГ-10-630 кВА', 1, 4, 450000.00),
(2, 'KSO-393-10', 'Ячейка КСО-393-10 кВ', 2, 4, 280000.00),
(3, 'SHRn-24', 'Щит распределительный ЩРн-24', 3, 4, 85000.00);

-- Добавляем пользователей
INSERT IGNORE INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
(1, 'director', '$2y$10$dummyhashforthedirector', 'Иванов Иван Иванович', 'director@polesie.ru', 1),
(2, 'foreman_wind', '$2y$10$dummyhashforforeman', 'Петров Петр Петрович', 'foreman@polesie.ru', 3),
(3, 'foreman_assembly', '$2y$10$dummyhashforassembly', 'Сидоров Сидор Сидорович', 'assembly@polesie.ru', 3),
(4, 'engineer_qc', '$2y$10$dummyhashforengineer', 'Кузнецов Кузьма Кузьмич', 'qc@polesie.ru', 4),
(5, 'foreman_prep', '$2y$10$dummyhashforprep', 'Волков Волк Волкович', 'prep@polesie.ru', 3);

-- ============================================
-- ЧАСТЬ 5: ТЕСТОВЫЕ ДАННЫЕ
-- ============================================

-- Анализ спроса
INSERT INTO `demand_analysis` (`product_id`, `period_start`, `period_end`, `forecast_quantity`, `actual_quantity`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `notes`) VALUES
(1, '2025-01-01', '2025-01-31', 150.000, 142.000, 1.0500, 1.0200, 92.50, 'Прогноз на январь 2025'),
(1, '2025-02-01', '2025-02-28', 160.000, 0.000, 1.0650, 0.9800, 88.30, 'Прогноз на февраль 2025'),
(2, '2025-01-01', '2025-01-31', 80.000, 75.000, 1.0300, 1.0100, 90.00, 'Прогноз на январь 2025'),
(3, '2025-01-01', '2025-01-31', 200.000, 195.000, 1.0800, 1.0500, 94.20, 'Прогноз на январь 2025');

-- Производственные планы
INSERT INTO `production_plans` (`plan_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `demand_analysis_id`, `notes`) VALUES
('PLAN-2025-001', 1, 100.000, 45.000, 'in_progress', '2025-01-15', '2025-01-31', 1, 'План производства трансформаторов ТМГ-10'),
('PLAN-2025-002', 2, 50.000, 0.000, 'approved', '2025-01-20', '2025-02-10', 3, 'План производства КСО-393'),
('PLAN-2025-003', 3, 150.000, 0.000, 'draft', '2025-02-01', '2025-02-28', 4, 'План производства щитов ЩРн');

-- Потребность в материалах для PLAN-2025-001
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(1, 1, 0.15000, 15.000, 8.500, 6.500, 32500.00, 1, TRUE, 'Медь для обмоток - дефицит'),
(1, 2, 0.08000, 8.000, 12.000, 0.000, 18000.00, 2, FALSE, 'Алюминий в наличии'),
(1, 3, 25.00000, 2500.000, 1800.000, 700.000, 35000.00, 3, FALSE, 'Трансформаторное масло'),
(1, 4, 1.00000, 100.000, 120.000, 0.000, 25000.00, 4, FALSE, 'Сталь электротехническая'),
(1, 5, 0.50000, 50.000, 30.000, 20.000, 14500.00, 1, TRUE, 'Изоляционные материалы');

-- Потребность в материалах для PLAN-2025-002
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(2, 4, 1.50000, 75.000, 40.000, 35.000, 24000.00, 4, TRUE, 'Сталь для корпуса КСО'),
(2, 6, 2.00000, 100.000, 100.000, 0.000, 20000.00, 5, FALSE, 'Комплектующие электрические'),
(2, 7, 0.25000, 12.500, 5.000, 7.500, 9000.00, 6, TRUE, 'Кабель силовой'),
(2, 8, 5.00000, 250.000, 300.000, 0.000, 8000.00, 4, FALSE, 'Крепежные изделия');

-- Потребность в материалах для PLAN-2025-003
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(3, 4, 0.80000, 120.000, 80.000, 40.000, 24000.00, 4, FALSE, 'Сталь для щитов'),
(3, 6, 1.50000, 225.000, 200.000, 25.000, 12000.00, 5, FALSE, 'Автоматические выключатели'),
(3, 9, 1.00000, 150.000, 100.000, 50.000, 9000.00, 7, FALSE, 'Корпуса металлические');

-- Графики производства для PLAN-2025-001
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(1, 'Участок намотки', '2025-01-15', 'day', '08:00:00', '17:00:00', 15.000, 14.000, 4, 2, 'completed', 30, 93.33, 'План выполнен с небольшими простоями'),
(1, 'Участок намотки', '2025-01-16', 'day', '08:00:00', '17:00:00', 15.000, 15.000, 4, 2, 'completed', 0, 100.00, 'Отличная работа'),
(1, 'Участок намотки', '2025-01-17', 'day', '08:00:00', '17:00:00', 15.000, 12.000, 3, 2, 'completed', 90, 80.00, 'Простой из-за отсутствия материала'),
(1, 'Сборочный участок', '2025-01-18', 'day', '08:00:00', '17:00:00', 10.000, 8.000, 5, 3, 'completed', 45, 80.00, 'Наладка оборудования'),
(1, 'Сборочный участок', '2025-01-19', 'day', '08:00:00', '17:00:00', 12.000, 0.000, 5, 3, 'blocked', 0, 0.00, 'Ожидание комплектующих'),
(1, 'Участок испытаний', '2025-01-20', 'day', '08:00:00', '17:00:00', 8.000, 0.000, 2, 4, 'planned', 0, 100.00, 'Запланировано'),
(1, 'Участок испытаний', '2025-01-21', 'day', '08:00:00', '17:00:00', 8.000, 0.000, 2, 4, 'planned', 0, 100.00, 'Запланировано'),
(1, 'Участок намотки', '2025-01-22', 'evening', '17:00:00', '01:00:00', 10.000, 0.000, 3, 2, 'planned', 0, 100.00, 'Вечерняя смена'),
(1, 'Сборочный участок', '2025-01-23', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 6, 3, 'planned', 0, 100.00, 'Усиленная бригада');

-- Графики для PLAN-2025-002
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(2, 'Заготовительный участок', '2025-01-20', 'day', '08:00:00', '17:00:00', 20.000, 0.000, 3, 5, 'planned', 0, 100.00, 'Начало производства КСО'),
(2, 'Сборочный участок', '2025-01-22', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 4, 3, 'planned', 0, 100.00, 'Сборка ячеек'),
(2, 'Участок испытаний', '2025-01-25', 'day', '08:00:00', '17:00:00', 10.000, 0.000, 2, 4, 'planned', 0, 100.00, 'Высоковольтные испытания');

-- Калькуляция себестоимости для PLAN-2025-001
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `notes`) VALUES
(1, 'material', 'Медь обмоточная', 15.000, 5000.0000, 75000.00, 'кг', 'Основной материал'),
(1, 'material', 'Алюминий', 8.000, 2250.0000, 18000.00, 'кг', 'Конструкционный материал'),
(1, 'material', 'Масло трансформаторное', 2500.000, 14.0000, 35000.00, 'л', 'Изоляционная среда'),
(1, 'material', 'Сталь электротехническая', 100.000, 250.0000, 25000.00, 'кг', 'Магнитопровод'),
(1, 'material', 'Изоляционные материалы', 50.000, 290.0000, 14500.00, 'кг', 'Изоляция'),
(1, 'labor', 'Основные рабочие', 800.000, 35.0000, 28000.00, 'час', 'ФОТ основных рабочих'),
(1, 'labor', 'Вспомогательный персонал', 200.000, 25.0000, 5000.00, 'час', 'Подсобные работы'),
(1, 'labor', 'Инженерно-технические работники', 100.000, 20.0000, 2000.00, 'час', 'ИТР'),
(1, 'overhead', 'Аренда помещений', 1.000, 8000.0000, 8000.00, 'месяц', 'Производственные помещения'),
(1, 'overhead', 'Амортизация оборудования', 1.000, 6000.0000, 6000.00, 'месяц', 'Станки и линии'),
(1, 'overhead', 'Коммунальные услуги', 1.000, 4500.0000, 4500.00, 'месяц', 'Электроэнергия, вода'),
(1, 'energy', 'Электроэнергия технологическая', 5000.000, 0.2500, 1250.00, 'кВт*ч', 'Технологические нужды'),
(1, 'other', 'Накладные расходы', 1.000, 3000.0000, 3000.00, 'месяц', 'Прочие расходы');

-- Калькуляция для PLAN-2025-002
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `notes`) VALUES
(2, 'material', 'Сталь электротехническая', 75.000, 320.0000, 24000.00, 'кг', 'Корпус КСО'),
(2, 'material', 'Комплектующие электрические', 100.000, 200.0000, 20000.00, 'шт', 'Автоматы, контакторы'),
(2, 'material', 'Кабель силовой', 12.500, 1200.0000, 15000.00, 'м', 'Внутренняя разводка'),
(2, 'material', 'Крепежные изделия', 250.000, 32.0000, 8000.00, 'шт', 'Крепеж'),
(2, 'labor', 'Основные рабочие', 600.000, 35.0000, 21000.00, 'час', 'Сборка ячеек'),
(2, 'labor', 'Вспомогательный персонал', 150.000, 25.0000, 3750.00, 'час', 'Подсобные работы'),
(2, 'overhead', 'Аренда помещений', 1.000, 6000.0000, 6000.00, 'месяц', 'Производственные помещения'),
(2, 'overhead', 'Амортизация оборудования', 1.000, 4500.0000, 4500.00, 'месяц', 'Станки и линии'),
(2, 'energy', 'Электроэнергия технологическая', 3000.000, 0.2500, 750.00, 'кВт*ч', 'Сварка, сборка');

-- Калькуляция для PLAN-2025-003
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `notes`) VALUES
(3, 'material', 'Сталь электротехническая', 120.000, 320.0000, 38400.00, 'кг', 'Корпуса щитов'),
(3, 'material', 'Комплектующие электрические', 225.000, 200.0000, 45000.00, 'шт', 'Автоматы, УЗО'),
(3, 'material', 'Корпуса металлические', 150.000, 450.0000, 67500.00, 'шт', 'Готовые корпуса'),
(3, 'labor', 'Основные рабочие', 450.000, 35.0000, 15750.00, 'час', 'Сборка щитов'),
(3, 'labor', 'Вспомогательный персонал', 100.000, 25.0000, 2500.00, 'час', 'Подсобные работы'),
(3, 'overhead', 'Аренда помещений', 1.000, 5000.0000, 5000.00, 'месяц', 'Производственные помещения'),
(3, 'overhead', 'Амортизация оборудования', 1.000, 3500.0000, 3500.00, 'месяц', 'Станки и линии'),
(3, 'energy', 'Электроэнергия технологическая', 2000.000, 0.2500, 500.00, 'кВт*ч', 'Сборка, монтаж');

-- Обновляем итоговые суммы в планах производства
UPDATE `production_plans` SET
    `total_material_cost` = (SELECT COALESCE(SUM(total_cost), 0) FROM `production_costing` WHERE `plan_id` = `production_plans`.`id` AND `cost_type` = 'material'),
    `total_labor_cost` = (SELECT COALESCE(SUM(total_cost), 0) FROM `production_costing` WHERE `plan_id` = `production_plans`.`id` AND `cost_type` = 'labor'),
    `total_overhead_cost` = (SELECT COALESCE(SUM(total_cost), 0) FROM `production_costing` WHERE `plan_id` = `production_plans`.`id` AND `cost_type` = 'overhead'),
    `total_cost` = (SELECT COALESCE(SUM(total_cost), 0) FROM `production_costing` WHERE `plan_id` = `production_plans`.`id`),
    `cost_per_unit` = (SELECT COALESCE(SUM(total_cost), 0) FROM `production_costing` WHERE `plan_id` = `production_plans`.`id`) / NULLIF(`quantity_plan`, 0)
WHERE `id` IN (1, 2, 3);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ЗАВЕРШЕНИЕ
-- ============================================
-- Файл успешно выполнен!
-- Проверьте данные через представления:
-- SELECT * FROM v_production_plan_summary;
-- SELECT * FROM v_material_shortage;
-- SELECT * FROM v_capacity_load;
-- SELECT * FROM v_costing_detail;
-- SELECT * FROM v_production_kpi;
