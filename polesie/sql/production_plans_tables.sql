-- ============================================
-- ДОПОЛНИТЕЛЬНЫЕ ТАБЛИЦЫ ДЛЯ ПЛАНА ПРОИЗВОДСТВА
-- Таблицы для комплексного планирования производства
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Рабочие центры (необходимо создать перед остальными таблицами)
CREATE TABLE IF NOT EXISTS `work_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `type` ENUM('assembly', 'packaging', 'quality_control', 'storage', 'other') DEFAULT 'other',
  `capacity_hours` DECIMAL(10,2) DEFAULT 8,
  `workers_max` INT DEFAULT 10,
  `hourly_rate` DECIMAL(15,2) DEFAULT 0,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица планов производства (основная)
CREATE TABLE IF NOT EXISTS `production_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_number` VARCHAR(50) UNIQUE NOT NULL,
  `product_id` INT NOT NULL,
  `plan_date` DATE NOT NULL,
  `planned_quantity` DECIMAL(15,3) NOT NULL,
  `actual_quantity` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `priority` INT DEFAULT 3 COMMENT '1-высокий, 2-средний, 3-низкий',
  `responsible_id` INT,
  `route_map_id` INT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_route` FOREIGN KEY (`route_map_id`) REFERENCES `route_maps`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица расчета себестоимости
CREATE TABLE IF NOT EXISTS `production_costing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `material_cost` DECIMAL(15,2) DEFAULT 0,
  `labor_cost` DECIMAL(15,2) DEFAULT 0,
  `overhead_cost` DECIMAL(15,2) DEFAULT 0,
  `total_cost` DECIMAL(15,2) DEFAULT 0,
  `cost_per_unit` DECIMAL(15,2) DEFAULT 0,
  `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pc_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Потребность в материалах для планов
CREATE TABLE IF NOT EXISTS `production_material_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `required_quantity` DECIMAL(15,3) NOT NULL,
  `reserved_quantity` DECIMAL(15,3) DEFAULT 0,
  `consumed_quantity` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('pending', 'reserved', 'consumed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pmr_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmr_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Рабочий график (смены)
CREATE TABLE IF NOT EXISTS `production_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `work_center_id` INT NOT NULL,
  `schedule_date` DATE NOT NULL,
  `shift_type` ENUM('day', 'night', 'both') DEFAULT 'day',
  `start_time` TIME,
  `end_time` TIME,
  `workers_count` INT DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ps_plan` FOREIGN KEY (`plan_id`) REFERENCES `production_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_workcenter` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Анализ спроса
CREATE TABLE IF NOT EXISTS `demand_analysis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `analysis_date` DATE NOT NULL,
  `period_type` ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT 'daily',
  `historical_avg` DECIMAL(15,3),
  `forecast_value` DECIMAL(15,3),
  `trend_coefficient` DECIMAL(5,4) DEFAULT 1,
  `seasonality_factor` DECIMAL(5,4) DEFAULT 1,
  `confidence_level` DECIMAL(5,2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_da_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы для производительности
CREATE INDEX idx_pp_product ON production_plans(product_id);
CREATE INDEX idx_pp_date ON production_plans(plan_date);
CREATE INDEX idx_pp_status ON production_plans(status);
CREATE INDEX idx_pmr_plan ON production_material_requirements(plan_id);
CREATE INDEX idx_ps_plan ON production_schedules(plan_id);
CREATE INDEX idx_ps_date ON production_schedules(schedule_date);
CREATE INDEX idx_da_product ON demand_analysis(product_id);
CREATE INDEX idx_da_date ON demand_analysis(analysis_date);

SET FOREIGN_KEY_CHECKS = 1;

-- Начальные данные для рабочих центров
INSERT INTO `work_centers` (`name`, `code`, `type`, `description`) VALUES
('Сборочный цех №1', 'ASSEMBLY-01', 'assembly', 'Основной сборочный участок'),
('Упаковочный участок', 'PACKAGING-01', 'packaging', 'Упаковка готовой продукции'),
('Контроль качества', 'QC-01', 'quality_control', 'Проверка качества'),
('Склад готовой продукции', 'STORAGE-01', 'storage', 'Хранение ГП')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Начальные данные для анализа спроса (пример)
INSERT INTO `demand_analysis` (`product_id`, `analysis_date`, `period_type`, `historical_avg`, `forecast_value`, `trend_coefficient`, `seasonality_factor`, `confidence_level`)
SELECT 
    p.id,
    CURDATE(),
    'daily',
    50.0,
    55.0,
    1.05,
    1.02,
    85.0
FROM products p
LIMIT 5
ON DUPLICATE KEY UPDATE forecast_value=VALUES(forecast_value);
