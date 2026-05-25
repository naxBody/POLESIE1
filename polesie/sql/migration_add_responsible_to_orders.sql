-- Миграция: Добавление колонки responsible_user_id в таблицу orders
-- Дата: 2025-01-XX
-- Описание: Добавляет поле ответственного пользователя в заказы

-- Добавляем колонку responsible_user_id в таблицу orders, если она не существует
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `responsible_user_id` INT;

-- Добавляем внешний ключ на таблицу users
ALTER TABLE `orders` ADD CONSTRAINT `fk_order_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
