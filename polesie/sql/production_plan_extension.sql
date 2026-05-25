-- ============================================
-- ПЛАН ПРОИЗВОДСТВА: РАСШИРЕНИЕ БАЗЫ ДАННЫХ
-- 5 новых таблиц для комплексного планирования
-- ============================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Удаление старых таблиц (если существуют)
DROP TABLE IF EXISTS `production_costing`;
DROP TABLE IF EXISTS `production_schedules`;
DROP TABLE IF EXISTS `production_material_requirements`;
DROP TABLE IF EXISTS `production_plans`;
DROP TABLE IF EXISTS `demand_analysis`;
DROP TABLE IF EXISTS `work_centers`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ДОПОЛНИТЕЛЬНАЯ ТАБЛИЦА: РАБОЧИЕ ЦЕНТРЫ
-- (нужна для графиков производства)
-- ============================================
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

-- ============================================
-- 1. АНАЛИЗ СПРОСА
-- Прогнозы и тренды для планирования
-- ============================================
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
  CONSTRAINT `fk_da_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_da_product_date` (`product_id`, `analysis_date`),
  INDEX `idx_da_period` (`period_type`, `analysis_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Анализ и прогноз спроса на продукцию';

-- ============================================
-- 2. ПЛАНЫ ПРОИЗВОДСТВА
-- Основная таблица с производственными планами
-- ============================================
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
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX `idx_pp_date` (`plan_date`),
  INDEX `idx_pp_status` (`status`),
  INDEX `idx_pp_product_date` (`product_id`, `plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Планы производства продукции';

-- ============================================
-- 3. ПОТРЕБНОСТЬ В МАТЕРИАЛАХ
-- Расчет материалов на основе норм расхода
-- ============================================
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
  CONSTRAINT `fk_pmr_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmr_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE CASCADE,
  INDEX `idx_pmr_plan` (`plan_id`),
  INDEX `idx_pmr_material` (`material_id`),
  INDEX `idx_pmr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Потребность в материалах для планов производства';

-- ============================================
-- 4. РАБОЧИЕ ГРАФИКИ
-- Загрузка мощностей по сменам
-- ============================================
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
  CONSTRAINT `fk_ps_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_work_center` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers`(`id`) ON DELETE CASCADE,
  INDEX `idx_ps_plan` (`plan_id`),
  INDEX `idx_ps_date` (`schedule_date`),
  INDEX `idx_ps_shift` (`shift_type`, `schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Графики работы производственных мощностей';

-- ============================================
-- 5. РАСЧЕТ СЕБЕСТОИМОСТИ
-- Агрегированные затраты на план
-- ============================================
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
  CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  INDEX `idx_pc_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Расчет себестоимости производства';

-- ============================================
-- ПРЕДСТАВЛЕНИЯ ДЛЯ АНАЛИТИКИ
-- ============================================

-- Общая сводка по планам
CREATE OR REPLACE VIEW `v_production_plan_summary` AS
SELECT 
    pp.id,
    pp.plan_number,
    pp.product_id,
    p.name as product_name,
    p.article as product_article,
    pp.plan_date,
    pp.planned_quantity,
    pp.actual_quantity,
    pp.demand_forecast,
    pp.priority,
    pp.status,
    COALESCE(pc.total_cost, 0) as total_cost,
    COALESCE(pc.cost_per_unit, 0) as cost_per_unit,
    u.full_name as responsible_name
FROM production_plans pp
JOIN products p ON pp.product_id = p.id
LEFT JOIN production_costing pc ON pp.id = pc.plan_id
LEFT JOIN users u ON pp.responsible_id = u.id;

-- Детализация материалов по планам
CREATE OR REPLACE VIEW `v_material_requirements_detail` AS
SELECT 
    pmr.id,
    pmr.plan_id,
    pp.plan_number,
    pp.product_id,
    p.name as product_name,
    pp.plan_date,
    pmr.material_id,
    m.name_full as material_name,
    m.code as material_code,
    m.current_stock,
    pmr.consumption_rate,
    pmr.required_quantity,
    pmr.reserved_quantity,
    pmr.actual_quantity,
    pmr.unit_cost,
    pmr.total_cost,
    pmr.status,
    CASE 
        WHEN pmr.required_quantity > m.current_stock THEN 'shortage'
        ELSE 'ok'
    END as stock_status
FROM production_material_requirements pmr
JOIN production_plans pp ON pmr.plan_id = pp.id
JOIN products p ON pp.product_id = p.id
JOIN materials m ON pmr.material_id = m.id;

-- Загрузка рабочих центров
CREATE OR REPLACE VIEW `v_work_center_load` AS
SELECT 
    ps.id,
    ps.plan_id,
    ps.work_center_id,
    wc.name as work_center_name,
    ps.schedule_date,
    ps.shift_type,
    ps.start_time,
    ps.end_time,
    ps.planned_hours,
    ps.actual_hours,
    ps.workers_count,
    ps.efficiency_percent,
    pp.plan_number,
    p.name as product_name
FROM production_schedules ps
JOIN work_centers wc ON ps.work_center_id = wc.id
JOIN production_plans pp ON ps.plan_id = pp.id
JOIN products p ON pp.product_id = p.id;

-- ============================================
-- ТЕСТОВЫЕ ДАННЫЕ ДЛЯ ДЕМО
-- ============================================

-- Рабочие центры
INSERT INTO `work_centers` (`code`, `name`, `type`, `capacity_hours`, `workers_max`, `hourly_rate`) VALUES
('WC-001', 'Сборочный цех №1', 'assembly', 8.0, 15, 25.00),
('WC-002', 'Сборочный цех №2', 'assembly', 8.0, 12, 25.00),
('WC-003', 'Упаковочный участок', 'packaging', 8.0, 8, 20.00),
('WC-004', 'Контроль качества', 'quality_control', 8.0, 4, 30.00),
('WC-005', 'Склад готовой продукции', 'storage', 8.0, 6, 18.00);

-- Анализ спроса (на основе существующих products)
INSERT INTO `demand_analysis` (`product_id`, `analysis_date`, `period_type`, `historical_avg`, `forecast_value`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `variance_percent`)
SELECT 
    id,
    CURDATE(),
    'weekly',
    ROUND(RAND() * 50 + 10, 0),
    ROUND(RAND() * 60 + 15, 0),
    ROUND(0.9 + RAND() * 0.3, 3),
    ROUND(0.85 + RAND() * 0.3, 3),
    ROUND(75 + RAND() * 20, 2),
    ROUND(-15 + RAND() * 30, 2)
FROM products
WHERE is_active = 1
LIMIT 10;

-- Прогноз на завтра
INSERT INTO `demand_analysis` (`product_id`, `analysis_date`, `period_type`, `historical_avg`, `forecast_value`, `trend_coefficient`, `seasonality_factor`, `confidence_level`, `variance_percent`)
SELECT 
    id,
    DATE_ADD(CURDATE(), INTERVAL 1 DAY),
    'daily',
    ROUND(RAND() * 20 + 5, 0),
    ROUND(RAND() * 25 + 8, 0),
    ROUND(0.95 + RAND() * 0.2, 3),
    1.0,
    ROUND(80 + RAND() * 15, 2),
    ROUND(-10 + RAND() * 20, 2)
FROM products
WHERE is_active = 1
LIMIT 8;

-- Планы производства
INSERT INTO `production_plans` (`plan_number`, `product_id`, `plan_date`, `planned_quantity`, `demand_forecast`, `priority`, `status`, `responsible_id`, `notes`)
VALUES
('PLAN-2025-001', 1, CURDATE(), 25, 22, 1, 'in_progress', 1, 'Срочный заказ'),
('PLAN-2025-002', 2, CURDATE(), 15, 18, 2, 'planned', 2, NULL),
('PLAN-2025-003', 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 30, 28, 1, 'planned', 1, 'Плановое производство'),
('PLAN-2025-004', 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 20, 22, 2, 'planned', 3, NULL),
('PLAN-2025-005', 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 40, 35, 2, 'planned', 2, 'Большая партия'),
('PLAN-2025-006', 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 12, 15, 3, 'planned', 1, NULL),
('PLAN-2025-007', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 18, 20, 1, 'planned', 3, 'Приоритетный заказ'),
('PLAN-2025-008', 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 25, 28, 2, 'planned', 2, NULL),
('PLAN-2025-009', 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 22, 22, 2, 'planned', 1, NULL),
('PLAN-2025-010', 4, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 35, 35, 3, 'planned', 3, 'На склад');

-- Потребность в материалах для планов
-- PLAN-2025-001 (product_id=1, quantity=25)
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 1, id, 
       CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END,
       ROUND(25 * CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END, 2),
       COALESCE(last_price, 10),
       ROUND(25 * CASE WHEN id % 3 = 1 THEN 0.5 WHEN id % 3 = 2 THEN 0.3 ELSE 0.8 END * COALESCE(last_price, 10), 2),
       'reserved'
FROM materials LIMIT 5;

-- PLAN-2025-002 (product_id=2, quantity=15)
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 2, id, 
       CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END,
       ROUND(15 * CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END, 2),
       COALESCE(last_price, 15),
       ROUND(15 * CASE WHEN id % 4 = 1 THEN 0.4 WHEN id % 4 = 2 THEN 0.6 ELSE 0.2 END * COALESCE(last_price, 15), 2),
       'pending'
FROM materials LIMIT 4;

-- PLAN-2025-003 (product_id=3, quantity=30)
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 3, id, 
       0.5,
       ROUND(30 * 0.5, 2),
       COALESCE(last_price, 12),
       ROUND(30 * 0.5 * COALESCE(last_price, 12), 2),
       'pending'
FROM materials LIMIT 6;

-- PLAN-2025-004 (product_id=1, quantity=20)
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 4, id, 
       0.4,
       ROUND(20 * 0.4, 2),
       COALESCE(last_price, 10),
       ROUND(20 * 0.4 * COALESCE(last_price, 10), 2),
       'pending'
FROM materials LIMIT 4;

-- PLAN-2025-005 (product_id=4, quantity=40)
INSERT INTO `production_material_requirements` (`plan_id`, `material_id`, `consumption_rate`, `required_quantity`, `unit_cost`, `total_cost`, `status`)
SELECT 5, id, 
       0.6,
       ROUND(40 * 0.6, 2),
       COALESCE(last_price, 8),
       ROUND(40 * 0.6 * COALESCE(last_price, 8), 2),
       'pending'
FROM materials LIMIT 7;

-- Рабочие графики
INSERT INTO `production_schedules` (`plan_id`, `work_center_id`, `schedule_date`, `shift_type`, `start_time`, `end_time`, `planned_hours`, `workers_count`, `efficiency_percent`)
VALUES
-- Сегодняшние планы
(1, 1, CURDATE(), 'morning', '08:00:00', '17:00:00', 8.0, 8, 95.0),
(1, 3, CURDATE(), 'afternoon', '14:00:00', '22:00:00', 8.0, 4, 90.0),
(2, 2, CURDATE(), 'morning', '08:00:00', '17:00:00', 8.0, 6, 100.0),

-- Завтрашние планы
(3, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 10, 0),
(3, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 3, 0),
(4, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'afternoon', '14:00:00', '22:00:00', 8.0, 7, 0),

-- Планы на следующие дни
(5, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 12, 0),
(5, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 5, 0),
(6, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'afternoon', '14:00:00', '22:00:00', 8.0, 4, 0),

(7, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 9, 0),
(8, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 8, 0),

(9, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 7, 0),
(10, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'morning', '08:00:00', '17:00:00', 8.0, 6, 0);

-- Расчет себестоимости
INSERT INTO `production_costing` (`plan_id`, `material_cost`, `labor_cost`, `overhead_cost`, `total_cost`, `cost_per_unit`)
SELECT 
    pp.id,
    COALESCE(mat.total_material, 0),
    ROUND(COALESCE(mat.total_material, 0) * 0.3, 2),
    ROUND(COALESCE(mat.total_material, 0) * 0.15, 2),
    ROUND(COALESCE(mat.total_material, 0) * 1.45, 2),
    ROUND(ROUND(COALESCE(mat.total_material, 0) * 1.45, 2) / pp.planned_quantity, 2)
FROM production_plans pp
LEFT JOIN (
    SELECT plan_id, SUM(total_cost) as total_material
    FROM production_material_requirements
    GROUP BY plan_id
) mat ON pp.id = mat.plan_id
WHERE pp.id BETWEEN 1 AND 10;

-- Обновление статусов (дефицит для некоторых материалов)
UPDATE `production_material_requirements`
SET status = 'shortage'
WHERE plan_id IN (3, 5) AND material_id IN (SELECT id FROM materials LIMIT 2);

-- Резервирование для первого плана
UPDATE `production_material_requirements`
SET reserved_quantity = required_quantity, status = 'reserved'
WHERE plan_id = 1;
