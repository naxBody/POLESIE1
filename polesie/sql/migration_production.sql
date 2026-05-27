-- ============================================
-- ДОПОЛНИТЕЛЬНЫЕ ТАБЛИЦЫ ДЛЯ ПРОИЗВОДСТВА
-- Маршрутные карты, этапы, план выпуска
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Рабочие центры (ДОБАВЛЕНО для исправления ошибки FK)
CREATE TABLE `work_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица этапов производства (технологические операции)
CREATE TABLE IF NOT EXISTS `production_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stage_name` VARCHAR(100) NOT NULL,
  `stage_code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `sequence_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица маршрутных карт (связь продукции с этапами)
CREATE TABLE IF NOT EXISTS `route_maps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `route_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rm_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Этапы в маршрутной карте
CREATE TABLE IF NOT EXISTS `route_map_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `route_map_id` INT NOT NULL,
  `stage_id` INT NOT NULL,
  `sequence_order` INT NOT NULL,
  `duration_hours` DECIMAL(10,2) DEFAULT 0,
  `work_center_id` INT,
  `description` TEXT,
  CONSTRAINT `fk_rms_route` FOREIGN KEY (`route_map_id`) REFERENCES `route_maps`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rms_stage` FOREIGN KEY (`stage_id`) REFERENCES `production_stages`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rms_workcenter` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Выполнение этапов по производственным заданиям
CREATE TABLE IF NOT EXISTS `production_stage_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `stage_id` INT NOT NULL,
  `route_map_stage_id` INT,
  `status` ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `worker_id` INT,
  `notes` TEXT,
  `quantity_completed` DECIMAL(15,3) DEFAULT 0,
  CONSTRAINT `fk_psp_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psp_stage` FOREIGN KEY (`stage_id`) REFERENCES `production_stages`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_psp_worker` FOREIGN KEY (`worker_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- План выпуска заказов
CREATE TABLE IF NOT EXISTS `production_release_plan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `plan_date` DATE NOT NULL,
  `planned_quantity` DECIMAL(15,3),
  `actual_quantity` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'delayed') DEFAULT 'planned',
  `responsible_id` INT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prp_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prp_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Материалы для заказов (агрегированная потребность)
CREATE TABLE IF NOT EXISTS `order_material_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `required_quantity` DECIMAL(15,3) NOT NULL,
  `available_quantity` DECIMAL(15,3) DEFAULT 0,
  `shortage_quantity` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('available', 'partial', 'shortage') DEFAULT 'available',
  `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_omr_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_omr_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавляем связь производственных заданий с заказами (если нет)
ALTER TABLE `production_tasks` 
ADD COLUMN IF NOT EXISTS `order_id` INT AFTER `id`,
ADD COLUMN IF NOT EXISTS `route_map_id` INT AFTER `order_id`,
ADD CONSTRAINT `fk_pt_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_pt_route` FOREIGN KEY (`route_map_id`) REFERENCES `route_maps`(`id`) ON DELETE SET NULL;

-- Индексы для производительности
CREATE INDEX idx_psp_task ON production_stage_progress(task_id);
CREATE INDEX idx_psp_status ON production_stage_progress(status);
CREATE INDEX idx_prp_order ON production_release_plan(order_id);
CREATE INDEX idx_prp_date ON production_release_plan(plan_date);
CREATE INDEX idx_omr_order ON order_material_requirements(order_id);

SET FOREIGN_KEY_CHECKS = 1;

-- Начальные данные для этапов
INSERT INTO `production_stages` (`stage_name`, `stage_code`, `description`, `sequence_order`) VALUES
('Заготовка материалов', 'CUTTING', 'Раскрой и подготовка материалов', 10),
('Сварка/Пайка', 'WELDING', 'Сварочные или паяльные работы', 20),
('Механическая обработка', 'MACHINING', 'Токарные, фрезерные работы', 30),
('Сборка', 'ASSEMBLY', 'Сборка узлов и изделий', 40),
('Покраска/Покрытие', 'COATING', 'Нанесение защитных покрытий', 50),
('Контроль качества', 'QC', 'Проверка соответствия требованиям', 60),
('Упаковка', 'PACKAGING', 'Подготовка к отгрузке', 70),
('Отгрузка', 'SHIPPING', 'Передача на склад готовой продукции', 80)
ON DUPLICATE KEY UPDATE stage_name=VALUES(stage_name);
