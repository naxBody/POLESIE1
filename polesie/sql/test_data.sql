-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ТЕСТОВЫЕ ДАННЫЕ
-- ============================================
USE `polesie_production`;


SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;



SET FOREIGN_KEY_CHECKS = 1;

-- 1. ЕДИНИЦЫ ИЗМЕРЕНИЯ
INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'),
('Килограмм', 'kg', 'кг'),
('Метр', 'm', 'м'),
('Тонна', 't', 'т'),
('Литр', 'l', 'л'),
('Комплект', 'set', 'компл');

-- 2. РОЛИ ПОЛЬЗОВАТЕЛЕЙ
INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all": true}'),
('Директор', 'director', 'Руководство', '{"all": true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders": ["read", "create"], "products": ["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production": ["read", "create"], "materials": ["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse": ["read", "create"], "materials": ["read", "update"]}');

-- 3. ПОЛЬЗОВАТЕЛИ (пароли в открытом виде)
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2),
('ivanov', 'admin123', 'Иванов Иван', 'ivanov@polesie.by', 3),
('petrov', 'admin123', 'Петров Петр', 'petrov@polesie.by', 4),
('sidorov', 'admin123', 'Сидоров Сидор', 'sidorov@polesie.by', 5);

-- 4. КОНТРАГЕНТЫ
INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3');

-- 5. КАТЕГОРИИ МАТЕРИАЛОВ
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

-- 6. КАТЕГОРИИ ПРОДУКЦИИ
INSERT INTO `product_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные генераторы'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(NULL, 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие');

-- 7. МАТЕРИАЛЫ
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

-- 8. ПРОДУКЦИЯ
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

-- 9. ЗАКАЗЫ
INSERT INTO `orders` (`order_number`, `customer_id`, `status`, `order_date`, `total_amount`, `notes`) VALUES
('ORD-2024-001', 4, 'processing', '2024-01-15', 2450.00, 'Срочный заказ'),
('ORD-2024-002', 5, 'ready', '2024-01-18', 1850.00, 'Отгрузка со склада'),
('ORD-2024-003', 4, 'new', '2024-01-20', 3200.00, 'Новый заказ на двигатели');

-- 10. ПОЗИЦИИ ЗАКАЗОВ
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`) VALUES
(1, 1, 2, 350.00, 700.00),
(1, 2, 3, 480.00, 1440.00),
(1, 11, 10, 15.00, 150.00),
(2, 5, 1, 2500.00, 2500.00),
(2, 12, 5, 25.00, 125.00),
(3, 3, 4, 650.00, 2600.00),
(3, 4, 1, 820.00, 820.00);

-- 11. ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
INSERT INTO `production_tasks` (`task_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `responsible_id`) VALUES
('TASK-2024-001', 1, 10, 10, 'completed', '2024-01-10', '2024-01-12', 3),
('TASK-2024-002', 2, 5, 5, 'completed', '2024-01-13', '2024-01-15', 3),
('TASK-2024-003', 3, 8, 6, 'in_progress', '2024-01-16', '2024-01-20', 3),
('TASK-2024-004', 4, 3, 0, 'planned', '2024-01-22', '2024-01-25', 3),
('TASK-2024-005', 5, 2, 0, 'planned', '2024-01-25', '2024-01-28', 3);

-- 12. МАТЕРИАЛЫ ДЛЯ ЗАДАНИЙ
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`) VALUES
(1, 1, 60, 60),
(1, 7, 100, 100),
(1, 14, 20, 20),
(2, 2, 30, 30),
(2, 8, 75, 75),
(2, 15, 10, 10),
(3, 3, 48, 36),
(3, 9, 24, 18),
(4, 4, 18, 0),
(4, 10, 48, 0);

-- 13. ПРОВЕРКИ КАЧЕСТВА
INSERT INTO `quality_checks` (`task_id`, `product_id`, `check_date`, `inspector_id`, `status`, `defect_description`, `quantity_checked`, `quantity_defective`) VALUES
(1, 1, '2024-01-12 14:00:00', 2, 'pass', NULL, 10, 0),
(2, 2, '2024-01-15 15:30:00', 2, 'pass', NULL, 5, 0),
(3, 3, '2024-01-18 10:00:00', 2, 'pass', NULL, 6, 0),
(3, 3, '2024-01-19 11:00:00', 2, 'fail', 'Превышен уровень вибрации', 2, 2),
(4, 4, '2024-01-20 09:00:00', 2, 'pass', NULL, 3, 0),
(5, 5, '2024-01-22 16:00:00', 2, 'rework', 'Требуется балансировка ротора', 2, 1),
(5, 5, '2024-01-23 10:00:00', 2, 'pass', NULL, 1, 0);

-- 14. СЕРИЙНЫЕ НОМЕРА
INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`) VALUES
(1, 'SN-ADM80A4-2024-0001', '2024-01-12', 1, 'active'),
(1, 'SN-ADM80A4-2024-0002', '2024-01-12', 1, 'active'),
(2, 'SN-ADM90L4-2024-0001', '2024-01-15', 2, 'active'),
(3, 'SN-ADM100L4-2024-0001', '2024-01-18', 3, 'active'),
(5, 'SN-DG5000-2024-0001', '2024-01-22', 5, 'warranty');
