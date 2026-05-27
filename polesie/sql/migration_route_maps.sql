-- ============================================
-- МИГРАЦИЯ: Маршрутные карты и этапы производства
-- Для системы Полесьеэлектромаш
-- ============================================

USE `polesie_production`;

-- Таблица технологических операций (справочник)
CREATE TABLE IF NOT EXISTS `technology_operations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `default_duration_hours` DECIMAL(5,2) DEFAULT 1.0,
  `work_center_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_to_work_center` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Справочник технологических операций';

-- Таблица маршрутных карт для продукции
CREATE TABLE IF NOT EXISTS `route_maps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `route_number` VARCHAR(50) NOT NULL UNIQUE,
  `version` VARCHAR(20) DEFAULT '1.0',
  `status` ENUM('draft', 'active', 'archived') DEFAULT 'draft',
  `total_duration_hours` DECIMAL(7,2) DEFAULT 0,
  `created_by` INT,
  `approved_by` INT,
  `approved_at` DATETIME,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rm_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rm_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_rm_product` (`product_id`),
  INDEX `idx_rm_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Маршрутные карты продукции';

-- Этапы маршрутной карты (операции в последовательности)
CREATE TABLE IF NOT EXISTS `route_map_operations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `route_map_id` INT NOT NULL,
  `operation_id` INT NOT NULL,
  `sequence_number` INT NOT NULL,
  `work_center_id` INT,
  `planned_duration_hours` DECIMAL(5,2) DEFAULT 1.0,
  `labor_hours` DECIMAL(5,2) DEFAULT 0,
  `workers_required` INT DEFAULT 1,
  `description` TEXT,
  `materials_needed` JSON COMMENT 'Материалы needed для этапа',
  `quality_checks` JSON COMMENT 'Параметры контроля качества',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rmo_route` FOREIGN KEY (`route_map_id`) REFERENCES `route_maps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rmo_operation` FOREIGN KEY (`operation_id`) REFERENCES `technology_operations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rmo_work_center` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE SET NULL,
  INDEX `idx_rmo_route` (`route_map_id`),
  INDEX `idx_rmo_sequence` (`route_map_id`, `sequence_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Операции маршрутной карты';

-- Производственные заказы (связь заказов с производством)
CREATE TABLE IF NOT EXISTS `production_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT,
  `production_number` VARCHAR(50) NOT NULL UNIQUE,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `deadline` DATE NOT NULL,
  `priority` TINYINT DEFAULT 2 COMMENT '1-высокий, 2-средний, 3-низкий',
  `status` ENUM('planned', 'in_progress', 'on_hold', 'completed', 'cancelled') DEFAULT 'planned',
  `route_map_id` INT,
  `responsible_id` INT,
  `start_date` DATE,
  `end_date` DATE,
  `actual_end_date` DATE,
  `progress_percent` DECIMAL(5,2) DEFAULT 0,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_po_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_route` FOREIGN KEY (`route_map_id`) REFERENCES `route_maps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_po_order` (`order_id`),
  INDEX `idx_po_status` (`status`),
  INDEX `idx_po_deadline` (`deadline`),
  INDEX `idx_po_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Производственные заказы';

-- Фактическое выполнение этапов по производственному заказу
CREATE TABLE IF NOT EXISTS `production_order_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_order_id` INT NOT NULL,
  `route_map_operation_id` INT NOT NULL,
  `sequence_number` INT NOT NULL,
  `operation_name` VARCHAR(200) NOT NULL,
  `work_center_id` INT,
  `status` ENUM('pending', 'in_progress', 'completed', 'skipped', 'rejected') DEFAULT 'pending',
  `planned_start` DATETIME,
  `actual_start` DATETIME,
  `planned_end` DATETIME,
  `actual_end` DATETIME,
  `planned_duration_hours` DECIMAL(5,2),
  `actual_duration_hours` DECIMAL(5,2),
  `workers_count` INT,
  `completed_quantity` DECIMAL(15,3) DEFAULT 0,
  `rejected_quantity` DECIMAL(15,3) DEFAULT 0,
  `operator_id` INT,
  `inspector_id` INT,
  `quality_status` ENUM('not_checked', 'passed', 'failed', 'rework') DEFAULT 'not_checked',
  `defect_description` TEXT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pos_production` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_operation` FOREIGN KEY (`route_map_operation_id`) REFERENCES `route_map_operations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pos_work_center` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_pos_production` (`production_order_id`),
  INDEX `idx_pos_status` (`status`),
  INDEX `idx_pos_sequence` (`production_order_id`, `sequence_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Этапы выполнения производственного заказа';

-- Потребность в материалах по производственным заказам (агрегированная)
CREATE TABLE IF NOT EXISTS `production_order_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_order_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `required_quantity` DECIMAL(15,3) NOT NULL,
  `reserved_quantity` DECIMAL(15,3) DEFAULT 0,
  `issued_quantity` DECIMAL(15,3) DEFAULT 0,
  `unit_cost` DECIMAL(15,2) DEFAULT 0,
  `total_cost` DECIMAL(15,2) DEFAULT 0,
  `status` ENUM('pending', 'reserved', 'issued', 'shortage') DEFAULT 'pending',
  `warehouse_doc_id` VARCHAR(50),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pom_production` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pom_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  INDEX `idx_pom_production` (`production_order_id`),
  INDEX `idx_pom_material` (`material_id`),
  INDEX `idx_pom_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Материалы для производственных заказов';

-- ============================================
-- ДАННЫЕ ДЛЯ ТЕСТИРОВАНИЯ
-- ============================================

-- Технологические операции
INSERT INTO `technology_operations` (`code`, `name`, `description`, `default_duration_hours`) VALUES
('OP001', 'Заготовка', 'Раскрой и заготовка материалов', 2.0),
('OP002', 'Сварка', 'Сварочные работы', 3.0),
('OP003', 'Механообработка', 'Токарные и фрезерные работы', 4.0),
('OP004', 'Сборка', 'Сборка узла/изделия', 5.0),
('OP005', 'Покраска', 'Нанесение лакокрасочного покрытия', 2.5),
('OP006', 'Сушка', 'Сушка после покраски', 4.0),
('OP007', 'Контроль качества', 'Проверка ОТК', 1.0),
('OP008', 'Упаковка', 'Упаковка готовой продукции', 1.5);

-- Пример маршрутной карты для продукта (нужно указать реальный product_id)
-- INSERT INTO `route_maps` (`product_id`, `route_number`, `version`, `status`, `total_duration_hours`)
-- SELECT id, 'RT-001', '1.0', 'active', 23.5 FROM products LIMIT 1;

-- Пример операций маршрутной карты
-- INSERT INTO `route_map_operations` (`route_map_id`, `operation_id`, `sequence_number`, `planned_duration_hours`)
-- SELECT rm.id, 1, 1, 2.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 2, 2, 3.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 3, 3, 4.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 4, 4, 5.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 5, 5, 2.5 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 6, 6, 4.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 7, 7, 1.0 FROM route_maps rm WHERE route_number = 'RT-001'
-- UNION ALL SELECT rm.id, 8, 8, 1.5 FROM route_maps rm WHERE route_number = 'RT-001';
