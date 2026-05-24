-- ============================================
-- БАЗА ДАННЫХ СИСТЕМЫ УПРАВЛЕНИЯ ПРОИЗВОДСТВОМ
-- ОАО "Полесьеэлектромаш" (Беларусь)
-- ОПТИМИЗИРОВАННАЯ ВЕРСИЯ
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `polesie_production` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

-- ============================================
-- ОЧИСТКА СТАРЫХ ТАБЛИЦ (если существуют)
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS serial_numbers;
DROP TABLE IF EXISTS quality_checks;
DROP TABLE IF EXISTS production_tasks_materials;
DROP TABLE IF EXISTS production_tasks;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS material_categories;
DROP TABLE IF EXISTS contractors;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS base_units;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 1. СПРАВОЧНИКИ
-- ============================================

-- Единицы измерения
CREATE TABLE `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `short_name` VARCHAR(10) NOT NULL,
  `code` VARCHAR(20),
  `type` ENUM('length', 'weight', 'volume', 'piece', 'area') DEFAULT 'piece'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Справочник единиц измерения';

-- Роли пользователей
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON COMMENT 'Права доступа в формате JSON'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Роли пользователей системы';

-- ============================================
-- 2. ПОЛЬЗОВАТЕЛИ И КОНТРАГЕНТЫ
-- ============================================

-- Пользователи
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100),
  `email` VARCHAR(100),
  `role_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Пользователи системы';

-- Контрагенты (поставщики и клиенты)
CREATE TABLE `contractors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `inn` VARCHAR(20),
  `type` ENUM('supplier', 'customer', 'both') DEFAULT 'both',
  `contact_person` VARCHAR(100),
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `address` TEXT,
  `rating` DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Рейтинг надежности'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Контрагенты (поставщики и покупатели)';

-- ============================================
-- 3. КАТЕГОРИИ (ИЕРАРХИЯ)
-- ============================================

-- Категории материалов
CREATE TABLE `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Иерархия категорий материалов';

-- Категории продукции
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Иерархия категорий продукции';

-- ============================================
-- 4. ОСНОВНЫЕ СУЩНОСТИ (МАТЕРИАЛЫ И ТОВАРЫ)
-- ============================================

-- Материалы и сырье
CREATE TABLE `materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Артикул/Код материала',
  `name_full` VARCHAR(200) NOT NULL,
  `name_short` VARCHAR(100),
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON COMMENT 'Гибкие параметры (размеры, ГОСТ, свойства)',
  `current_stock` DECIMAL(10,3) DEFAULT 0.000,
  `min_stock` DECIMAL(10,3) DEFAULT 0.000 COMMENT 'Минимальный неснижаемый запас',
  `location` VARCHAR(100) COMMENT 'Место хранения (склад, ячейка)',
  `supplier_id` INT,
  `last_price` DECIMAL(10,2) DEFAULT 0.00,
  `currency` CHAR(3) DEFAULT 'BYN',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  INDEX `idx_material_category` (`category_id`),
  INDEX `idx_material_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Справочник материалов и сырья';

-- Готовая продукция
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Артикул товара',
  `name` VARCHAR(200) NOT NULL,
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON COMMENT 'Технические характеристики товара',
  `base_price` DECIMAL(10,2) DEFAULT 0.00,
  `currency` CHAR(3) DEFAULT 'BYN',
  `image_url` VARCHAR(255),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  INDEX `idx_product_category` (`category_id`),
  INDEX `idx_product_article` (`article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Каталог готовой продукции';

-- ============================================
-- 5. ОПЕРАЦИОННЫЕ ТАБЛИЦЫ
-- ============================================

-- Заказы клиентов
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `status` ENUM('new', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'new',
  `order_date` DATE NOT NULL,
  `delivery_date` DATE,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `comment` TEXT,
  FOREIGN KEY (`customer_id`) REFERENCES `contractors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Заказы клиентов';

-- Позиции заказов
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Позиции заказов';

-- Производственные задания
CREATE TABLE `production_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_number` VARCHAR(50) NOT NULL UNIQUE,
  `product_id` INT NOT NULL,
  `quantity_plan` DECIMAL(10,3) NOT NULL,
  `quantity_fact` DECIMAL(10,3) DEFAULT 0.00,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `start_date` DATE,
  `end_date` DATE,
  `responsible_id` INT,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Производственные задания';

-- Материалы в производственном задании (списание)
CREATE TABLE `production_tasks_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity_required` DECIMAL(10,3) NOT NULL,
  `quantity_used` DECIMAL(10,3) DEFAULT 0.00,
  FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Нормы расхода материалов на задание';

-- Контроль качества (ОТК)
CREATE TABLE `quality_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` ENUM('material', 'product', 'task') NOT NULL,
  `entity_id` INT NOT NULL,
  `inspector_id` INT,
  `check_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `result` ENUM('passed', 'failed', 'rework') NOT NULL,
  `defect_description` TEXT,
  `serial_number` VARCHAR(100),
  FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Журнал проверок качества';

-- Серийные номера
CREATE TABLE `serial_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `production_task_id` INT,
  `production_date` DATE,
  `status` ENUM('active', 'sold', 'returned', 'scrapped') DEFAULT 'active',
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`production_task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Учет серийных номеров продукции';

COMMIT;
