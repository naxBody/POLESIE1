-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ПЛАН ПРОИЗВОДСТВА
-- Единый SQL-файл: 5 таблиц + представления + тестовые данные
-- Все проверки на существование записей в справочниках
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. ТАБЛИЦА: АНАЛИЗ СПРОСА (demand_analysis)
-- ============================================
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

-- ============================================
-- 2. ТАБЛИЦА: ПЛАНЫ ПРОИЗВОДСТВА (production_plans)
-- ============================================
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

-- ============================================
-- 3. ТАБЛИЦА: ПОТРЕБНОСТЬ В МАТЕРИАЛАХ (production_material_requirements)
-- ============================================
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

-- ============================================
-- 4. ТАБЛИЦА: ГРАФИКИ ПРОИЗВОДСТВА (production_schedules)
-- ============================================
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

-- ============================================
-- 5. ТАБЛИЦА: КАЛЬКУЛЯЦИЯ СЕБЕСТОИМОСТИ (production_costing)
-- ============================================
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
-- ПРЕДСТАВЛЕНИЯ (VIEWS) ДЛЯ ОТЧЕТОВ
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
-- ПОДГОТОВКА СПРАВОЧНЫХ ДАННЫХ
-- ============================================

-- Добавляем поставщиков (contractors), если их нет
INSERT IGNORE INTO `contractors` (`id`, `name`, `inn`, `kpp`, `ogrn`, `address`, `phone`, `email`, `contractor_type`, `is_active`) VALUES
(1, 'ООО "МедьПром"', '7701234567', '770101001', '1234567890123', 'г. Москва, ул. Промышленная, 1', '+7 (495) 111-11-11', 'info@medprom.ru', 'supplier', TRUE),
(2, 'АО "АлюминийСнаб"', '7702345678', '770201001', '2234567890123', 'г. Екатеринбург, ул. Металлургов, 5', '+7 (343) 222-22-22', 'sales@aluminium.ru', 'supplier', TRUE),
(3, 'ООО "НефтеПродукт"', '7703456789', '770301001', '3234567890123', 'г. Нижний Новгород, ул. Нефтяная, 10', '+7 (831) 333-33-33', 'order@nefteprodukt.ru', 'supplier', TRUE),
(4, 'ПАО "СтальИнвест"', '7704567890', '770401001', '4234567890123', 'г. Челябинск, пр. Металлургов, 25', '+7 (351) 444-44-44', 'zakaz@stalinvest.ru', 'supplier', TRUE),
(5, 'ООО "ЭлектроКомплект"', '7705678901', '770501001', '5234567890123', 'г. Санкт-Петербург, ул. Электротехническая, 8', '+7 (812) 555-55-55', 'info@electrokomplekt.ru', 'supplier', TRUE),
(6, 'ЗАО "КабельТорг"', '7706789012', '770601001', '6234567890123', 'г. Казань, ул. Кабельная, 3', '+7 (843) 666-66-66', 'sales@cabletorg.ru', 'supplier', TRUE),
(7, 'ИП Петров А.С.', '7707890123', NULL, '7234567890123', 'г. Самара, ул. Заводская, 15', '+7 (846) 777-77-77', 'petrov@mail.ru', 'supplier', TRUE);

-- Добавляем материалы, если их нет (используем INSERT IGNORE чтобы не дублировать)
-- Предполагаем, что материалы с id 1-9 должны существовать
INSERT IGNORE INTO `materials` (`id`, `code`, `name_full`, `name_short`, `unit_id`, `category`, `price_avg`, `stock_balance`, `min_stock`, `is_active`) VALUES
(1, 'MED-001', 'Медь обмоточная ММФ-1', 'Медь обмоточная', 1, 'raw_material', 5000.00, 50.000, 10.000, TRUE),
(2, 'ALU-002', 'Алюминий конструкционный АД31', 'Алюминий', 1, 'raw_material', 2250.00, 80.000, 20.000, TRUE),
(3, 'OIL-003', 'Масло трансформаторное ГК', 'Масло трансформаторное', 3, 'raw_material', 14.00, 5000.000, 1000.000, TRUE),
(4, 'STL-004', 'Сталь электротехническая 3406', 'Сталь электротехническая', 1, 'raw_material', 320.00, 500.000, 100.000, TRUE),
(5, 'ISO-005', 'Изоляция лакоткань ЛЭС', 'Изоляционные материалы', 1, 'raw_material', 290.00, 100.000, 25.000, TRUE),
(6, 'ELC-006', 'Комплектующие электрические', 'Комплектующие', 4, 'components', 200.00, 500.000, 100.000, TRUE),
(7, 'CAB-007', 'Кабель силовой ВВГнг 3х185', 'Кабель силовой', 5, 'materials', 1200.00, 200.000, 50.000, TRUE),
(8, 'FST-008', 'Крепеж М12х50 оцинкованный', 'Крепежные изделия', 1, 'materials', 32.00, 1000.000, 200.000, TRUE),
(9, 'BOX-009', 'Корпус металлический ЩРн-IP54', 'Корпуса металлические', 4, 'components', 450.00, 50.000, 10.000, TRUE);

-- Добавляем продукты, если их нет
INSERT IGNORE INTO `products` (`id`, `article`, `name`, `category`, `unit_id`, `price_retail`, `cost_price`, `is_active`) VALUES
(1, 'TMG-10-630', 'Трансформатор ТМГ-10-630 кВА', 'transformer', 4, 450000.00, 320000.00, TRUE),
(2, 'KSO-393-10', 'Ячейка КСО-393-10 кВ', 'switchgear', 4, 280000.00, 190000.00, TRUE),
(3, 'SHRn-24', 'Щит распределительный ЩРн-24', 'distribution_board', 4, 85000.00, 55000.00, TRUE);

-- Добавляем пользователей, если их нет
INSERT IGNORE INTO `users` (`id`, `username`, `full_name`, `email`, `role`, `department_id`, `is_active`) VALUES
(1, 'director', 'Иванов Иван Иванович', 'director@polesie.ru', 'director', NULL, TRUE),
(2, 'foreman_wind', 'Петров Петр Петрович', 'foreman@polesie.ru', 'foreman', NULL, TRUE),
(3, 'foreman_assembly', 'Сидоров Сидор Сидорович', 'assembly@polesie.ru', 'foreman', NULL, TRUE),
(4, 'engineer_qc', 'Кузнецов Кузьма Кузьмич', 'qc@polesie.ru', 'engineer', NULL, TRUE),
(5, 'foreman_prep', 'Волков Волк Волкович', 'prep@polesie.ru', 'foreman', NULL, TRUE);

-- ============================================
-- ТЕСТОВЫЕ ДАННЫЕ
-- ============================================

-- Добавляем анализ спроса
INSERT INTO `demand_analysis` (`product_id`, `period_start`, `period_end`, `forecast_quantity`, `actual_quantity`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `notes`) VALUES
(1, '2025-01-01', '2025-01-31', 150.000, 142.000, 1.0500, 1.0200, 92.50, 'Прогноз на январь 2025'),
(1, '2025-02-01', '2025-02-28', 160.000, 0.000, 1.0650, 0.9800, 88.30, 'Прогноз на февраль 2025'),
(2, '2025-01-01', '2025-01-31', 80.000, 75.000, 1.0300, 1.0100, 90.00, 'Прогноз на январь 2025'),
(3, '2025-01-01', '2025-01-31', 200.000, 195.000, 1.0800, 1.0500, 94.20, 'Прогноз на январь 2025');

-- Добавляем производственные планы
INSERT INTO `production_plans` (`plan_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `demand_analysis_id`, `notes`) VALUES
('PLAN-2025-001', 1, 100.000, 45.000, 'in_progress', '2025-01-15', '2025-01-31', 1, 'План производства трансформаторов ТМГ-10'),
('PLAN-2025-002', 2, 50.000, 0.000, 'approved', '2025-01-20', '2025-02-10', 3, 'План производства КСО-393'),
('PLAN-2025-003', 3, 150.000, 0.000, 'draft', '2025-02-01', '2025-02-28', 4, 'План производства щитов ЩРн');

-- Добавляем потребность в материалах для PLAN-2025-001
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(1, 1, 0.15000, 15.000, 8.500, 6.500, 32500.00, 1, TRUE, 'Медь для обмоток - дефицит'),
(1, 2, 0.08000, 8.000, 12.000, 0.000, 18000.00, 2, FALSE, 'Алюминий в наличии'),
(1, 3, 25.00000, 2500.000, 1800.000, 700.000, 35000.00, 3, FALSE, 'Трансформаторное масло'),
(1, 4, 1.00000, 100.000, 120.000, 0.000, 25000.00, 4, FALSE, 'Сталь электротехническая'),
(1, 5, 0.50000, 50.000, 30.000, 20.000, 14500.00, 1, TRUE, 'Изоляционные материалы');

-- Добавляем потребность в материалах для PLAN-2025-002
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(2, 4, 1.50000, 75.000, 40.000, 35.000, 24000.00, 4, TRUE, 'Сталь для корпуса КСО'),
(2, 6, 2.00000, 100.000, 100.000, 0.000, 20000.00, 5, FALSE, 'Комплектующие электрические'),
(2, 7, 0.25000, 12.500, 5.000, 7.500, 9000.00, 6, TRUE, 'Кабель силовой'),
(2, 8, 5.00000, 250.000, 300.000, 0.000, 8000.00, 3, FALSE, 'Крепежные изделия');

-- Добавляем потребность в материалах для PLAN-2025-003
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `norm_per_unit`, `quantity_required`, `quantity_available`, `quantity_to_purchase`, `estimated_cost`, `supplier_id`, `is_critical`, `notes`) VALUES
(3, 4, 0.80000, 120.000, 80.000, 40.000, 24000.00, 4, FALSE, 'Сталь для щитов'),
(3, 6, 1.50000, 225.000, 200.000, 25.000, 12000.00, 5, FALSE, 'Автоматические выключатели'),
(3, 9, 1.00000, 150.000, 100.000, 50.000, 9000.00, 7, FALSE, 'Корпуса металлические');

-- Добавляем графики производства для PLAN-2025-001
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

-- Добавляем графики для PLAN-2025-002
INSERT INTO `production_schedules` (`plan_id`, `work_center`, `schedule_date`, `shift`, `hour_start`, `hour_end`, `planned_quantity`, `fact_quantity`, `worker_count`, `responsible_id`, `status`, `downtime_minutes`, `efficiency_percent`, `notes`) VALUES
(2, 'Заготовительный участок', '2025-01-20', 'day', '08:00:00', '17:00:00', 20.000, 0.000, 3, 5, 'planned', 0, 100.00, 'Начало производства КСО'),
(2, 'Сборочный участок', '2025-01-22', 'day', '08:00:00', '17:00:00', 15.000, 0.000, 4, 3, 'planned', 0, 100.00, 'Сборка ячеек'),
(2, 'Участок испытаний', '2025-01-25', 'day', '08:00:00', '17:00:00', 10.000, 0.000, 2, 4, 'planned', 0, 100.00, 'Высоковольтные испытания');

-- Добавляем калькуляцию себестоимости для PLAN-2025-001
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `percentage`, `notes`) VALUES
(1, 'material', 'Медь обмоточная', 15.000, 5000.0000, 75000.00, 'кг', 0.00, 'Основной материал'),
(1, 'material', 'Алюминий', 8.000, 2250.0000, 18000.00, 'кг', 0.00, 'Конструкционный материал'),
(1, 'material', 'Масло трансформаторное', 2500.000, 14.0000, 35000.00, 'л', 0.00, 'Изоляционная среда'),
(1, 'material', 'Сталь электротехническая', 100.000, 250.0000, 25000.00, 'кг', 0.00, 'Магнитопровод'),
(1, 'material', 'Изоляционные материалы', 50.000, 290.0000, 14500.00, 'кг', 0.00, 'Изоляция'),
(1, 'labor', 'Основные рабочие', 800.000, 35.0000, 28000.00, 'час', 0.00, 'ФОТ основных рабочих'),
(1, 'labor', 'Вспомогательный персонал', 200.000, 25.0000, 5000.00, 'час', 0.00, 'Подсобные работы'),
(1, 'labor', 'Инженерно-технические работники', 100.000, 20.0000, 2000.00, 'час', 0.00, 'ИТР'),
(1, 'overhead', 'Аренда помещений', 1.000, 8000.0000, 8000.00, 'месяц', 0.00, 'Производственные помещения'),
(1, 'overhead', 'Амортизация оборудования', 1.000, 6000.0000, 6000.00, 'месяц', 0.00, 'Станки и линии'),
(1, 'overhead', 'Коммунальные услуги', 1.000, 4000.0000, 4000.00, 'месяц', 0.00, 'Электроэнергия, вода'),
(1, 'energy', 'Электроэнергия технологическая', 5000.000, 0.3500, 1750.00, 'кВт*ч', 0.00, 'Технологические нужды'),
(1, 'other', 'Упаковка и маркировка', 100.000, 25.0000, 2500.00, 'шт', 0.00, 'Тара и упаковка');

-- Добавляем калькуляцию для PLAN-2025-002
INSERT INTO `production_costing` (`plan_id`, `cost_type`, `cost_item`, `amount`, `unit_cost`, `total_cost`, `allocation_base`, `percentage`, `notes`) VALUES
(2, 'material', 'Сталь конструкционная', 75.000, 320.0000, 24000.00, 'кг', 0.00, 'Корпус КСО'),
(2, 'material', 'Комплектующие электрические', 100.000, 200.0000, 20000.00, 'компл', 0.00, 'Разъединители, приводы'),
(2, 'material', 'Кабель силовой', 12.500, 1200.0000, 15000.00, 'м', 0.00, 'Внутренние соединения'),
(2, 'material', 'Крепежные изделия', 250.000, 32.0000, 8000.00, 'кг', 0.00, 'Болты, гайки, шайбы'),
(2, 'material', 'Лакокрасочные материалы', 30.000, 450.0000, 13500.00, 'кг', 0.00, 'Покрытие корпуса'),
(2, 'material', 'Изоляторы', 150.000, 30.0000, 4500.00, 'шт', 0.00, 'Опорные изоляторы'),
(2, 'labor', 'Основные рабочие', 600.000, 30.0000, 18000.00, 'час', 0.00, 'Сборка КСО'),
(2, 'labor', 'Сварщики', 150.000, 40.0000, 6000.00, 'час', 0.00, 'Сварочные работы'),
(2, 'overhead', 'Аренда', 1.000, 5000.0000, 5000.00, 'месяц', 0.00, 'Доля аренды'),
(2, 'overhead', 'Амортизация', 1.000, 4000.0000, 4000.00, 'месяц', 0.00, 'Износ оборудования'),
(2, 'overhead', 'Накладные расходы', 1.000, 3000.0000, 3000.00, 'месяц', 0.00, 'Общепроизводственные'),
(2, 'energy', 'Электроэнергия', 3000.000, 0.3500, 1050.00, 'кВт*ч', 0.00, 'Сварка, освещение');

-- Обновляем проценты в калькуляции (пересчет)
UPDATE `production_costing` pc
JOIN (
    SELECT plan_id, SUM(total_cost) as total_sum
    FROM production_costing
    GROUP BY plan_id
) totals ON pc.plan_id = totals.plan_id
SET pc.percentage = ROUND((pc.total_cost / totals.total_sum) * 100, 2);

-- Пересчитываем итоги в планах производства
UPDATE `production_plans` pp
JOIN (
    SELECT plan_id,
           SUM(CASE WHEN cost_type = 'material' THEN total_cost ELSE 0 END) as mat_cost,
           SUM(CASE WHEN cost_type = 'labor' THEN total_cost ELSE 0 END) as labor_cost,
           SUM(CASE WHEN cost_type IN ('overhead', 'energy', 'other') THEN total_cost ELSE 0 END) as overhead_cost,
           SUM(total_cost) as total
    FROM production_costing
    GROUP BY plan_id
) costs ON pp.id = costs.plan_id
SET
    pp.total_material_cost = costs.mat_cost,
    pp.total_labor_cost = costs.labor_cost,
    pp.total_overhead_cost = costs.overhead_cost,
    pp.total_cost = costs.total,
    pp.cost_per_unit = CASE WHEN pp.quantity_plan > 0 THEN costs.total / pp.quantity_plan ELSE 0 END;

-- ============================================
-- КОНЕЦ ФАЙЛА
-- ============================================

SET FOREIGN_KEY_CHECKS = 1;
