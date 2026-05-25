-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ЕДИНЫЙ SQL ФАЙЛ
-- Полная схема базы данных + Производственный план
-- Все таблицы, представления и тестовые данные
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- ЧАСТЬ 1: ОСНОВНАЯ СХЕМА БАЗЫ ДАННЫХ
-- ============================================

CREATE DATABASE IF NOT EXISTS polesie_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE polesie_production;

-- Полное удаление всех таблиц для чистой установки
DROP TABLE IF EXISTS `product_documents`;
DROP TABLE IF EXISTS `product_serial_numbers`;
DROP TABLE IF EXISTS `quality_checks`;
DROP TABLE IF EXISTS `production_tasks_materials`;
DROP TABLE IF EXISTS `production_tasks`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `material_categories`;
DROP TABLE IF EXISTS `contractors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `base_units`;

-- Таблицы производственного плана
DROP TABLE IF EXISTS `production_costing`;
DROP TABLE IF EXISTS `production_schedules`;
DROP TABLE IF EXISTS `production_material_requirements`;
DROP TABLE IF EXISTS `production_plans`;
DROP TABLE IF EXISTS `demand_analysis`;

-- Представления
DROP VIEW IF EXISTS `v_production_kpi`;
DROP VIEW IF EXISTS `v_costing_detail`;
DROP VIEW IF EXISTS `v_capacity_load`;
DROP VIEW IF EXISTS `v_material_shortage`;
DROP VIEW IF EXISTS `v_production_plan_summary`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 1. ЕДИНИЦЫ ИЗМЕРЕНИЯ
-- ============================================
CREATE TABLE `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `symbol` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. РОЛИ ПОЛЬЗОВАТЕЛЕЙ
-- ============================================
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. ПОЛЬЗОВАТЕЛИ
-- ============================================
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `role_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. КОНТРАГЕНТЫ
-- ============================================
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

-- ============================================
-- 5. КАТЕГОРИИ МАТЕРИАЛОВ
-- ============================================
CREATE TABLE `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. КАТЕГОРИИ ПРОДУКЦИИ
-- ============================================
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_prod_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. МАТЕРИАЛЫ
-- ============================================
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
  CONSTRAINT `fk_mat_category` FOREIGN KEY (`category_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. ПРОДУКЦИЯ
-- ============================================
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
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. ЗАКАЗЫ
-- ============================================
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT,
  `responsible_user_id` INT,
  `status` ENUM('new', 'processing', 'ready', 'shipped', 'cancelled') DEFAULT 'new',
  `order_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2),
  `notes` TEXT,
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. ПОЗИЦИИ ЗАКАЗОВ
-- ============================================
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `total` DECIMAL(15,2) NOT NULL,
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
-- ============================================
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
  CONSTRAINT `fk_task_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_user` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. МАТЕРИАЛЫ ДЛЯ ЗАДАНИЙ
-- ============================================
CREATE TABLE `production_tasks_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity_required` DECIMAL(15,3) NOT NULL,
  `quantity_used` DECIMAL(15,3) DEFAULT 0,
  CONSTRAINT `fk_ptm_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 13. ПРОВЕРКИ КАЧЕСТВА
-- ============================================
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
  CONSTRAINT `fk_qc_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 14. СЕРИЙНЫЕ НОМЕРА ПРОДУКЦИИ
-- ============================================
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
  CONSTRAINT `fk_psn_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psn_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. ДОКУМЕНТЫ ПРОДУКЦИИ
-- ============================================
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
  CONSTRAINT `fk_pd_serial` FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЧАСТЬ 2: ТАБЛИЦЫ ПЛАНА ПРОИЗВОДСТВА (5 таблиц)
-- ============================================

-- ============================================
-- 16. АНАЛИЗ СПРОСА (demand_analysis)
-- ============================================
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

-- ============================================
-- 17. ПЛАНЫ ПРОИЗВОДСТВА (production_plans)
-- ============================================
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

-- ============================================
-- 18. ПОТРЕБНОСТЬ В МАТЕРИАЛАХ (production_material_requirements)
-- ============================================
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

-- ============================================
-- 19. ГРАФИКИ ПРОИЗВОДСТВА (production_schedules)
-- ============================================
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

-- ============================================
-- 20. КАЛЬКУЛЯЦИЯ СЕБЕСТОИМОСТИ (production_costing)
-- ============================================
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

-- Единицы измерения
INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'),
('Килограмм', 'kg', 'кг'),
('Метр', 'm', 'м'),
('Тонна', 't', 'т'),
('Литр', 'l', 'л'),
('Комплект', 'set', 'компл'),
('Метр погонный', 'lm', 'м.п.');

-- Роли пользователей
INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all": true}'),
('Директор', 'director', 'Руководство', '{"all": true}'),
('Начальник производства', 'production_manager', 'Управление производственными планами', '{"production": ["read", "create", "update"], "materials": ["read"]}'),
('Мастер участка', 'foreman', 'Управление графиками и задачами', '{"production": ["read", "update"], "schedules": ["read", "create"]}'),
('Технолог', 'technologist', 'Производство', '{"production": ["read", "create"], "materials": ["read"]}'),
('Инженер', 'engineer', 'Техническая документация и качество', '{"quality": ["read", "create"], "documents": ["read", "create"]}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders": ["read", "create"], "products": ["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse": ["read", "create"], "materials": ["read", "update"]}');

-- Пользователи (пароль в открытом виде для тестирования)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role_id`, `is_active`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1, TRUE),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2, TRUE),
('ivanov', 'admin123', 'Иванов Иван Иванович', 'ivanov@polesie.by', 3, TRUE),
('petrov', 'admin123', 'Петров Петр Петрович', 'petrov@polesie.by', 4, TRUE),
('sidorov', 'admin123', 'Сидоров Сидор Сидорович', 'sidorov@polesie.by', 4, TRUE),
('kuznetsov', 'admin123', 'Кузнецов Кузьма Кузьмич', 'kuznetsov@polesie.by', 6, TRUE),
('volkov', 'admin123', 'Волков Волк Волкович', 'volkov@polesie.by', 5, TRUE),
('smirnova', 'admin123', 'Смирнова Елена', 'smirnova@polesie.by', 7, TRUE),
('kozlov', 'admin123', 'Козлов Андрей', 'kozlov@polesie.by', 8, TRUE);

-- Категории материалов
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
(NULL, 'Подшипники', 'BEARING', 'Подшипники качения'),
(NULL, 'Сырье и материалы', 'raw_material', 'Основные производственные материалы'),
(NULL, 'Комплектующие', 'components', 'Покупные изделия и компоненты'),
(NULL, 'Вспомогательные материалы', 'auxiliary', 'Вспомогательные расходные материалы');

-- Категории продукции
INSERT INTO `product_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные генераторы'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(NULL, 'Распределительные устройства', 'switchgear', 'Ячейки КСО, комплектные устройства'),
(NULL, 'Щитовое оборудование', 'distribution_board', 'Распределительные щиты'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие');

-- Контрагенты
INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3'),
('ООО "МедьПром"', '770123456', 'supplier', 'Медников В.В.', '+7 (495) 111-11-11', 'info@medprom.ru', 'г. Москва, ул. Промышленная, 1'),
('АО "АлюминийСнаб"', '770234567', 'supplier', 'Алюминиев А.А.', '+7 (343) 222-22-22', 'sales@aluminium.ru', 'г. Екатеринбург, ул. Металлургов, 5'),
('ООО "НефтеПродукт"', '770345678', 'supplier', 'Нефтянов Н.Н.', '+7 (831) 333-33-33', 'order@nefteprodukt.ru', 'г. Нижний Новгород, ул. Нефтяная, 10'),
('ПАО "СтальИнвест"', '770456789', 'supplier', 'Сталеваров С.С.', '+7 (351) 444-44-44', 'zakaz@stalinvest.ru', 'г. Челябинск, пр. Металлургов, 25'),
('ООО "ЭлектроКомплект"', '770567890', 'supplier', 'Электриков Э.Э.', '+7 (812) 555-55-55', 'info@electrokomplekt.ru', 'г. Санкт-Петербург, ул. Электротехническая, 8');

-- Материалы
INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "45", "length_m": 6}', 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "40Х"}', 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-SHEET-3-08', 'Лист стальной 3мм', 'Лист 3мм', 3, 2, '{"thickness_mm": 3, "width_mm": 1500}', 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
('CAST-IRON-CH20', 'Чугун серый СЧ20', 'Чугун СЧ20', 4, 2, '{"grade": "СЧ20", "hardness_hb": "170-220"}', 500.00, 100.00, 'Склад №3', 1, 2.80, 'BYN'),
('WIRE-CU-2.5', 'Провод медный 2.5мм²', 'Провод 2.5', 6, 3, '{"cross_section_mm2": 2.5, "material": "медь"}', 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('BUS-CU-20x3', 'Шина медная 20x3мм', 'Шина 20x3', 7, 3, '{"width_mm": 20, "thickness_mm": 3, "material": "М1"}', 250.00, 50.00, 'Склад №4, Секция Б', 2, 45.00, 'BYN'),
('BOLT-M10x50', 'Болт М10х50 8.8', 'Болт М10х50', 9, 1, '{"thread": "M10", "length_mm": 50, "strength_class": "8.8"}', 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('NUT-M10', 'Гайка М10 8', 'Гайка М10', 10, 1, '{"thread": "M10", "strength_class": "8"}', 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, '{"inner_d_mm": 25, "outer_d_mm": 52}', 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('MED-001', 'Медь обмоточная ММФ-1', 'Медь обмоточная', 12, 2, '{"grade": "ММФ-1"}', 50.000, 10.000, 'Склад №1', 6, 5000.00, 'BYN'),
('ALU-002', 'Алюминий конструкционный АД31', 'Алюминий', 12, 2, '{"grade": "АД31"}', 80.000, 20.000, 'Склад №1', 7, 2250.00, 'BYN'),
('OIL-003', 'Масло трансформаторное ГК', 'Масло трансформаторное', 14, 6, '{"type": "ГК"}', 5000.000, 1000.000, 'Склад №6', 8, 14.00, 'BYN'),
('STL-004', 'Сталь электротехническая 3406', 'Сталь электротехническая', 12, 2, '{"grade": "3406"}', 500.000, 100.000, 'Склад №1', 9, 320.00, 'BYN'),
('ISO-005', 'Изоляция лакоткань ЛЭС', 'Изоляционные материалы', 14, 2, '{"type": "ЛЭС"}', 100.000, 25.000, 'Склад №4', 6, 290.00, 'BYN'),
('ELC-006', 'Комплектующие электрические', 'Комплектующие', 13, 1, '{}', 500.000, 100.000, 'Склад №4', 10, 200.00, 'BYN'),
('CAB-007', 'Кабель силовой ВВГнг 3х185', 'Кабель силовой', 6, 7, '{"type": "ВВГнг", "cores": 3, "section": 185}', 200.000, 50.000, 'Склад №4', 2, 1200.00, 'BYN'),
('FST-008', 'Крепеж М12х50 оцинкованный', 'Крепежные изделия', 9, 1, '{"thread": "M12", "length_mm": 50}', 1000.000, 200.000, 'Склад №5', 3, 32.00, 'BYN'),
('BOX-009', 'Корпус металлический ЩРн-IP54', 'Корпуса металлические', 13, 1, '{"protection": "IP54"}', 50.000, 10.000, 'Склад №7', 10, 450.00, 'BYN');

-- Продукция
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `base_price`, `currency`, `is_active`) VALUES
('ADM-80A4', 'Двигатель АДМ 80A4', 1, 1, '{"power_kw": 1.1, "rpm": 1500, "voltage_v": 380}', 350.00, 'BYN', TRUE),
('ADM-90L4', 'Двигатель АДМ 90L4', 1, 1, '{"power_kw": 2.2, "rpm": 1500, "voltage_v": 380}', 480.00, 'BYN', TRUE),
('ADM-100L4', 'Двигатель АДМ 100L4', 1, 1, '{"power_kw": 4.0, "rpm": 1500, "voltage_v": 380}', 650.00, 'BYN', TRUE),
('ADM-112M4', 'Двигатель АДМ 112M4', 1, 1, '{"power_kw": 5.5, "rpm": 1500, "voltage_v": 380}', 820.00, 'BYN', TRUE),
('DG-5000', 'Генератор дизельный 5кВт', 2, 1, '{"power_kw": 5, "fuel_type": "дизель", "voltage_v": 220}', 2500.00, 'BYN', TRUE),
('DG-10000', 'Генератор дизельный 10кВт', 2, 1, '{"power_kw": 10, "fuel_type": "дизель", "voltage_v": 380}', 4200.00, 'BYN', TRUE),
('TM-25', 'Трансформатор ТМ-25', 3, 1, '{"power_kva": 25, "voltage_primary_kv": 10}', 3500.00, 'BYN', TRUE),
('TM-63', 'Трансформатор ТМ-63', 3, 1, '{"power_kva": 63, "voltage_primary_kv": 10}', 5200.00, 'BYN', TRUE),
('SCH-100A', 'Щит распределительный 100А', 5, 1, '{"current_a": 100, "circuits": 12, "protection_ip": "IP54"}', 450.00, 'BYN', TRUE),
('SCH-250A', 'Щит распределительный 250А', 5, 1, '{"current_a": 250, "circuits": 24, "protection_ip": "IP54"}', 850.00, 'BYN', TRUE),
('TMG-10-630', 'Трансформатор ТМГ-10-630 кВА', 3, 1, '{"power_kva": 630, "voltage_primary_kv": 10}', 450000.00, 'BYN', TRUE),
('KSO-393-10', 'Ячейка КСО-393-10 кВ', 4, 1, '{"voltage_kv": 10, "type": "КСО-393"}', 280000.00, 'BYN', TRUE),
('SHRn-24', 'Щит распределительный ЩРн-24', 5, 1, '{"circuits": 24, "protection_ip": "IP54"}', 85000.00, 'BYN', TRUE);

-- ============================================
-- ЧАСТЬ 5: ТЕСТОВЫЕ ДАННЫЕ
-- ============================================

-- Заказы
INSERT INTO `orders` (`order_number`, `customer_id`, `responsible_user_id`, `status`, `order_date`, `total_amount`, `notes`) VALUES
('ORD-2024-001', 4, 7, 'processing', '2024-01-15', 2450.00, 'Срочный заказ'),
('ORD-2024-002', 5, 7, 'ready', '2024-01-18', 1850.00, 'Отгрузка со склада'),
('ORD-2024-003', 4, 7, 'new', '2024-01-20', 3200.00, 'Новый заказ на двигатели');

-- Позиции заказов
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`) VALUES
(1, 1, 2, 350.00, 700.00),
(1, 2, 3, 480.00, 1440.00),
(1, 11, 10, 15.00, 150.00),
(2, 5, 1, 2500.00, 2500.00),
(2, 12, 5, 25.00, 125.00),
(3, 3, 4, 650.00, 2600.00),
(3, 4, 1, 820.00, 820.00);

-- Производственные задания
INSERT INTO `production_tasks` (`task_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `responsible_id`) VALUES
('TASK-2024-001', 1, 10, 10, 'completed', '2024-01-10', '2024-01-12', 4),
('TASK-2024-002', 2, 5, 5, 'completed', '2024-01-13', '2024-01-15', 4),
('TASK-2024-003', 3, 8, 6, 'in_progress', '2024-01-16', '2024-01-20', 4),
('TASK-2024-004', 4, 3, 0, 'planned', '2024-01-22', '2024-01-25', 4),
('TASK-2024-005', 5, 2, 0, 'planned', '2024-01-25', '2024-01-28', 5);

-- Материалы для заданий
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`) VALUES
(1, 1, 60, 60),
(1, 5, 100, 100),
(1, 9, 20, 20),
(2, 2, 30, 30),
(2, 6, 75, 75),
(2, 8, 10, 10),
(3, 3, 48, 36),
(3, 7, 24, 18),
(4, 4, 18, 0),
(4, 10, 48, 0);

-- Проверки качества
INSERT INTO `quality_checks` (`task_id`, `product_id`, `check_date`, `inspector_id`, `status`, `defect_description`, `quantity_checked`, `quantity_defective`) VALUES
(1, 1, '2024-01-12 14:00:00', 6, 'pass', NULL, 10, 0),
(2, 2, '2024-01-15 15:30:00', 6, 'pass', NULL, 5, 0),
(3, 3, '2024-01-18 10:00:00', 6, 'pass', NULL, 6, 0),
(3, 3, '2024-01-19 11:00:00', 6, 'fail', 'Превышен уровень вибрации', 2, 2),
(4, 4, '2024-01-20 09:00:00', 6, 'pass', NULL, 3, 0),
(5, 5, '2024-01-22 16:00:00', 6, 'rework', 'Требуется балансировка ротора', 2, 1),
(5, 5, '2024-01-23 10:00:00', 6, 'pass', NULL, 1, 0);

-- Серийные номера
INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`) VALUES
(1, 'SN-ADM80A4-2024-0001', '2024-01-12', 1, 'active'),
(1, 'SN-ADM80A4-2024-0002', '2024-01-12', 1, 'active'),
(2, 'SN-ADM90L4-2024-0001', '2024-01-15', 2, 'active'),
(3, 'SN-ADM100L4-2024-0001', '2024-01-18', 3, 'active'),
(5, 'SN-DG5000-2024-0001', '2024-01-22', 5, 'warranty');

-- ============================================
-- ЧАСТЬ 6: ДАННЫЕ ДЛЯ ПЛАНА ПРОИЗВОДСТВА
-- ============================================

-- Анализ спроса
INSERT INTO `demand_analysis` (`product_id`, `period_start`, `period_end`, `forecast_quantity`, `actual_quantity`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `notes`) VALUES
(11, '2025-01-01', '2025-01-31', 150.000, 142.000, 1.0500, 1.0200, 92.50, 'Прогноз на январь 2025'),
(11, '2025-02-01', '2025-02-28', 160.000, 0.000, 1.0650, 0.9800, 88.30, 'Прогноз на февраль 2025'),
(12, '2025-01-01', '2025-01-31', 80.000, 75.000, 1.0300, 1.0100, 90.00, 'Прогноз на январь 2025'),
(13, '2025-01-01', '2025-01-31', 200.000, 195.000, 1.0800, 1.0500, 94.20, 'Прогноз на январь 2025');

-- Производственные планы
INSERT INTO `production_plans` (`plan_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `demand_analysis_id`, `total_material_cost`, `total_labor_cost`, `total_overhead_cost`, `total_cost`, `cost_per_unit`, `created_by`, `notes`) VALUES
('PLAN-2025-001', 11, 100.000, 45.000, 'in_progress', '2025-01-15', '2025-01-31', 1, 125000.00, 35000.00, 18000.00, 178000.00, 1780.00, 1, 'План производства трансформаторов ТМГ-10'),
('PLAN-2025-002', 12, 50.000, 0.000, 'approved', '2025-01-20', '2025-02-10', 3, 85000.00, 22000.00, 12000.00, 119000.00, 2380.00, 1, 'План производства КСО-393'),
('PLAN-2025-003', 13, 150.000, 0.000, 'draft', '2025-02-01', '2025-02-28', 4, 45000.00, 15000.00, 8000.00, 68000.00, 453.33, 1, 'План производства щитов ЩРн');

-- Потребность в материалах для PLAN-2025-001
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(1, 10, 0.15000, 15.000, 8.500, 6.500, 32500.00, 6, TRUE, 'Медь для обмоток - дефицит'),
(1, 11, 0.08000, 8.000, 12.000, 0.000, 18000.00, 7, FALSE, 'Алюминий в наличии'),
(1, 12, 25.00000, 2500.000, 1800.000, 700.000, 35000.00, 8, FALSE, 'Трансформаторное масло'),
(1, 13, 1.00000, 100.000, 120.000, 0.000, 25000.00, 9, FALSE, 'Сталь электротехническая'),
(1, 14, 0.50000, 50.000, 30.000, 20.000, 14500.00, 6, TRUE, 'Изоляционные материалы');

-- Потребность в материалах для PLAN-2025-002
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(2, 13, 1.50000, 75.000, 40.000, 35.000, 42000.00, 9, TRUE, 'Сталь для корпуса КСО'),
(2, 15, 2.00000, 100.000, 100.000, 0.000, 20000.00, 10, FALSE, 'Комплектующие электрические'),
(2, 16, 0.25000, 12.500, 5.000, 7.500, 15000.00, 2, TRUE, 'Кабель силовой'),
(2, 17, 5.00000, 250.000, 300.000, 0.000, 8000.00, 3, FALSE, 'Крепежные изделия');

-- Потребность в материалах для PLAN-2025-003
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(3, 13, 0.80000, 120.000, 80.000, 40.000, 24000.00, 9, FALSE, 'Сталь для щитов'),
(3, 15, 1.50000, 225.000, 200.000, 25.000, 12000.00, 10, FALSE, 'Автоматические выключатели'),
(3, 18, 1.00000, 150.000, 100.000, 50.000, 9000.00, 10, FALSE, 'Корпуса металлические');

-- Графики производства для PLAN-2025-001
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(1, 'Участок намотки', '2025-01-15', 'day', '08:00:00', '17:00:00', 15.000, 14.000, 4, 4, 'completed', 30, 93.33, 'План выполнен с небольшими простоями'),
(1, 'Участок намотки', '2025-01-16', 'day', '08:00:00', '17:00:00', 15.000, 15.000, 4, 4, 'completed', 0, 100.00, 'Отличная работа'),
(1, 'Участок намотки', '2025-01-17', 'day', '08:00:00', '17:00:00', 15.000, 12.000, 3, 4, 'completed', 90, 80.00, 'Простой из-за отсутствия материала'),
(1, 'Сборочный участок', '2025-01-18', 'day', '08:00:00', '17:00:00', 10.000, 8.000, 5, 5, 'completed', 45, 80.00, 'Наладка оборудования'),
(1, 'Сборочный участок', '2025-01-19', 'day', '08:00:00', '17:00:00', 12.000, 0.000, 5, 5, 'blocked', 0, 0.00, 'Ожидание комплектующих'),
(1, 'Участок испытаний', '2025-01-20', 'day', '08:00:00', '17:00:00', 8.000, 0.000, 2, 6, 'planned', 0, 100.00, 'Запланировано'),
(1, 'Участок испытаний', '2025-01-21', 'day', '08:00:00', '17:00:00', 8.000, 0.000, 2, 6, 'planned', 0, 100.00, 'Запланировано'),
(1, 'Участок намотки', '2025-01-22', 'evening', '17:00:00', '01:00:00', 10.000, 0.000, 3, 4, 'planned', 0, 100.00, 'Вечерняя смена'),
(1, 'Сборочный участок', '2025-01-23', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 6, 5, 'planned', 0, 100.00, 'Усиленная бригада');

-- Графики для PLAN-2025-002
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(2, 'Заготовительный участок', '2025-01-20', 'day', '08:00:00', '17:00:00', 20.000, 0.000, 4, 5, 'planned', 0, 100.00, 'Начало производства КСО'),
(2, 'Сборочный участок', '2025-01-25', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 5, 5, 'planned', 0, 100.00, 'Сборка ячеек'),
(2, 'Участок испытаний', '2025-02-05', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 2, 6, 'planned', 0, 100.00, 'Испытания высоковольтные');

-- Графики для PLAN-2025-003
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(3, 'Заготовительный участок', '2025-02-01', 'day', '08:00:00', '17:00:00', 50.000, 0.000, 3, 5, 'planned', 0, 100.00, 'Раскрой металла'),
(3, 'Сборочный участок', '2025-02-10', 'day', '08:00:00', '17:00:00', 50.000, 0.000, 4, 5, 'planned', 0, 100.00, 'Сборка щитов'),
(3, 'Участок испытаний', '2025-02-25', 'day', '08:00:00', '17:00:00', 50.000, 0.000, 2, 6, 'planned', 0, 100.00, 'Проверка автоматики');

-- Калькуляция себестоимости для PLAN-2025-001
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `percentage`, `notes`) VALUES
(1, 'material', 'Медь обмоточная', 15.000, 5000.00, 75000.00, 'weight_kg', 42.13, 'Основной материал'),
(1, 'material', 'Сталь электротехническая', 100.000, 320.00, 32000.00, 'weight_kg', 17.98, 'Активная сталь'),
(1, 'material', 'Масло трансформаторное', 2500.000, 14.00, 35000.00, 'volume_l', 19.66, 'Изоляционное масло'),
(1, 'labor', 'Зарплата основных рабочих', 200.000, 175.00, 35000.00, 'hours', 19.66, 'Намотка, сборка'),
(1, 'overhead', 'Аренда помещения', 1.000, 10000.00, 10000.00, 'month', 5.62, 'Доля аренды'),
(1, 'overhead', 'Электроэнергия', 5000.000, 0.35, 1750.00, 'kwh', 0.98, 'Производственные нужды'),
(1, 'overhead', 'Амортизация оборудования', 1.000, 6250.00, 6250.00, 'month', 3.51, 'Станки и оснастка');

-- Калькуляция себестоимости для PLAN-2025-002
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `percentage`, `notes`) VALUES
(2, 'material', 'Сталь конструкционная', 75.000, 320.00, 24000.00, 'weight_kg', 20.17, 'Корпус КСО'),
(2, 'material', 'Комплектующие электрические', 100.000, 200.00, 20000.00, 'pcs', 16.81, 'Разъединители, приводы'),
(2, 'material', 'Кабель силовой', 12.500, 1200.00, 15000.00, 'meters', 12.61, 'Внутренние соединения'),
(2, 'labor', 'Зарплата сборщиков', 150.000, 146.67, 22000.00, 'hours', 18.49, 'Сборка ячеек'),
(2, 'overhead', 'Аренда', 1.000, 8000.00, 8000.00, 'month', 6.72, 'Доля аренды'),
(2, 'overhead', 'Электроэнергия', 3000.000, 0.35, 1050.00, 'kwh', 0.88, 'Сварка, монтаж'),
(2, 'energy', 'Газ (сварка)', 500.000, 5.90, 2950.00, 'm3', 2.48, 'Сварочные работы');

-- Калькуляция себестоимости для PLAN-2025-003
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `percentage`, `notes`) VALUES
(3, 'material', 'Корпуса металлические', 150.000, 160.00, 24000.00, 'pcs', 35.29, 'ЩРн-24'),
(3, 'material', 'Автоматические выключатели', 225.000, 53.33, 12000.00, 'pcs', 17.65, 'Автоматы защиты'),
(3, 'material', ' DIN-рейки, крепеж', 300.000, 30.00, 9000.00, 'set', 13.24, 'Монтажные элементы'),
(3, 'labor', 'Зарплата сборщиков', 120.000, 125.00, 15000.00, 'hours', 22.06, 'Сборка щитов'),
(3, 'overhead', 'Аренда', 1.000, 5000.00, 5000.00, 'month', 7.35, 'Доля аренды'),
(3, 'overhead', 'Электроэнергия', 2000.000, 0.35, 700.00, 'kwh', 1.03, 'Монтажные работы'),
(3, 'other', 'Упаковка', 150.000, 15.33, 2300.00, 'pcs', 3.38, 'Транспортная упаковка');

-- ============================================
-- КОНЕЦ ФАЙЛА
-- ============================================
