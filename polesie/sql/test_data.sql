-- ============================================
-- ТЕСТОВЫЕ ДАННЫЕ ДЛЯ БАЗЫ POLESIE_PRODUCTION
-- ============================================
-- Порядок вставки строго соблюден для соблюдения целостности FK

-- 1. ОЧИСТКА ТАБЛИЦ (чтобы избежать дублей при повторном запуске)
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE serial_numbers;
TRUNCATE TABLE quality_checks;
TRUNCATE TABLE production_tasks;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE products;
TRUNCATE TABLE materials;
TRUNCATE TABLE product_categories;
TRUNCATE TABLE material_categories;
TRUNCATE TABLE contractors;
TRUNCATE TABLE base_units;
TRUNCATE TABLE users;
TRUNCATE TABLE user_roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 2. СПРАВОЧНИКИ И КАТЕГОРИИ
-- ============================================

-- Роли пользователей
INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all": true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders": ["read", "create"], "products": ["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production": ["read", "create"], "materials": ["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse": ["read", "create"], "materials": ["read", "update"]}');

-- Пользователи
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role_id`, `email`, `is_active`) VALUES
('admin', '$2y$10$dummyhash...', 'Администратор Системы', 1, 'admin@polesie.by', 1),
('ivanov', '$2y$10$dummyhash...', 'Иван Иванов', 2, 'ivanov@polesie.by', 1),
('petrov', '$2y$10$dummyhash...', 'Петр Петров', 3, 'petrov@polesie.by', 1),
('sidorov', '$2y$10$dummyhash...', 'Сидор Сидоров', 4, 'sidorov@polesie.by', 1);

-- Единицы измерения
INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'),
('Килограмм', 'kg', 'кг'),
('Метр', 'm', 'м'),
('Тонна', 't', 'т'),
('Литр', 'l', 'л'),
('Набор', 'set', 'наб');

-- Контрагенты (Поставщики и Клиенты)
INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Алексей Смирнов', '+375291112233', 'info@stalprom.by', 'Минск, ул. Промышленная 1'),
('ЗАО "ЦветМет"', '200987654', 'supplier', 'Ольга Новик', '+375294445566', 'sales@tsvetmet.by', 'Гомель, ул. Заводская 5'),
('ИП "ЭлектроДеталь"', '300555666', 'supplier', 'Иван Козлов', '+375297778899', 'zakaz@electro.by', 'Брест, пр. Машерова 10'),
('ОАО "БелЭнерго"', '400111222', 'customer', 'Дмитрий Волков', '+375172003040', 'procurement@belenergo.by', 'Минск, пр. Независимости 100'),
('ООО "СтройМонтаж"', '500333444', 'customer', 'Елена Мороз', '+375295006070', 'info@stroymontazh.by', 'Гродно, ул. Строителей 20');

-- Категории материалов (Иерархия)
INSERT INTO `material_categories` (`name`, `parent_id`, `code`) VALUES
('Металлопрокат', NULL, 'METAL'),
('Прутки', 1, 'METAL_BAR'),
('Листовой прокат', 1, 'METAL_SHEET'),
('Чугун', 1, 'METAL_CAST'),
('Электротехника', NULL, 'ELECTRO'),
('Провода', 5, 'ELECTRO_WIRE'),
('Шины', 5, 'ELECTRO_BUS'),
('Крепеж', NULL, 'FASTENER'),
('Подшипники', NULL, 'BEARING'),
('Лаки и краски', NULL, 'PAINT'),
('Упаковка', NULL, 'PACK');

-- Категории продукции
INSERT INTO `product_categories` (`name`, `parent_id`, `code`) VALUES
('Электродвигатели', NULL, 'PROD_MOTOR'),
('Генераторы', NULL, 'PROD_GEN'),
('Трансформаторы', NULL, 'PROD_TR'),
('Щитовое оборудование', NULL, 'PROD_SHIELD'),
('Запчасти', NULL, 'PROD_PART');

-- ============================================
-- 3. МАТЕРИАЛЫ (Сырье)
-- ============================================

INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
-- Прутки (Категория 2)
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "45", "length_m": 6, "surface": "калиброванный", "gost": "10702-78"}', 321.50, 50.00, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"diameter_mm": 10, "steel_grade": "40Х", "length_m": 6, "surface": "горячекатаный", "gost": "2590-2006"}', 150.00, 20.00, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, '{"diameter_mm": 20, "steel_grade": "45", "length_m": 6, "surface": "калиброванный", "gost": "10702-78"}', 500.00, 100.00, 'Склад №1, Секция Б', 1, 4.10, 'BYN'),
('AL-BAR-D16-015', 'Пруток алюминиевый Д16Т Ø15мм', 'Пруток Д16Т Ø15', 2, 3, '{"diameter_mm": 15, "alloy": "Д16Т", "length_m": 3, "density_kg_m3": 2800}', 85.00, 10.00, 'Склад №2, Полка 3', 2, 8.50, 'BYN'),
-- Листовой прокат (Категория 3)
('ST-SHEET-St3-002', 'Лист стальной Ст3 2мм', 'Лист Ст3 2мм', 3, 2, '{"thickness_mm": 2, "width_mm": 1250, "length_mm": 2500, "steel_grade": "Ст3сп5", "gost": "19903-90"}', 1500.00, 200.00, 'Склад №1, Зона листов', 1, 2.80, 'BYN'),
('ST-SHEET-St3-005', 'Лист стальной Ст3 5мм', 'Лист Ст3 5мм', 3, 2, '{"thickness_mm": 5, "width_mm": 1500, "length_mm": 3000, "steel_grade": "Ст3сп5", "gost": "19903-90"}', 2200.00, 300.00, 'Склад №1, Зона листов', 1, 3.10, 'BYN'),
('AL-SHEET-D16-003', 'Лист алюминиевый Д16АТ 3мм', 'Лист Д16АТ 3мм', 3, 2, '{"thickness_mm": 3, "width_mm": 1200, "length_mm": 2400, "alloy": "Д16АТ"}', 400.00, 50.00, 'Склад №2, Зона листов', 2, 9.20, 'BYN'),
-- Чугун (Категория 4)
('CAST-CHUGUN-SCh20', 'Чугун серый СЧ20 (чушка)', 'Чугун СЧ20', 4, 2, '{"grade": "СЧ20", "form": "чушка", "weight_kg": 20, "gost": "1412-85"}', 5000.00, 1000.00, 'Склад сырья, Площадка', 1, 1.90, 'BYN'),
-- Электротехника (Категории 6, 7)
('WIRE-Cu-2.5', 'Провод медный ПВ3 2.5мм²', 'Провод ПВ3 2.5', 6, 3, '{"cross_section_mm2": 2.5, "material": "медь", "insulation": "ПВХ", "color": "синий", "gost": "6323-79"}', 1200.00, 200.00, 'Склад №3, Катушки', 3, 1.50, 'BYN'),
('WIRE-Al-4.0', 'Провод алюминиевый АППВ 4мм²', 'Провод АППВ 4', 6, 3, '{"cross_section_mm2": 4.0, "material": "алюминий", "insulation": "ПВХ", "cores": 2}', 800.00, 100.00, 'Склад №3, Катушки', 3, 0.90, 'BYN'),
('BUS-Cu-20x3', 'Шина медная ШММ 20х3', 'Шина 20х3', 7, 3, '{"width_mm": 20, "thickness_mm": 3, "material": "медь М1", "length_m": 2}', 150.00, 20.00, 'Склад №3, Стеллаж', 2, 15.00, 'BYN'),
-- Крепеж (Категория 8)
('BOLT-M10-50', 'Болт М10х50 ГОСТ 7798', 'Болт М10х50', 8, 1, '{"thread": "M10", "length_mm": 50, "strength_class": "5.8", "coating": "цинк"}', 5000.00, 500.00, 'Склад №4, Ящик А1', 3, 0.15, 'BYN'),
('NUT-M10', 'Гайка М10 ГОСТ 5915', 'Гайка М10', 8, 1, '{"thread": "M10", "strength_class": "5", "coating": "цинк"}', 6000.00, 600.00, 'Склад №4, Ящик А2', 3, 0.08, 'BYN'),
('WASHER-10', 'Шайба плоская 10мм', 'Шайба 10', 8, 1, '{"inner_diameter_mm": 10.5, "outer_diameter_mm": 20, "material": "сталь"}', 10000.00, 1000.00, 'Склад №4, Ящик А3', 3, 0.05, 'BYN'),
-- Подшипники (Категория 9)
('BRG-204', 'Подшипник шариковый 204', 'Пдш 204', 9, 1, '{"inner_d": 20, "outer_d": 47, "width": 14, "type": "radial"}', 300.00, 30.00, 'Склад №5, Стеллаж Б', 2, 4.50, 'BYN'),
('BRG-306', 'Подшипник шариковый 306', 'Пдш 306', 9, 1, '{"inner_d": 30, "outer_d": 72, "width": 19, "type": "radial"}', 150.00, 15.00, 'Склад №5, Стеллаж Б', 2, 8.20, 'BYN'),
-- Лаки (Категория 10)
('VAR-Epoxide', 'Лак эпоксидный ЭП-9114', 'Лак ЭП-9114', 10, 5, '{"type": "эпоксидный", "viscosity": "средняя", "drying_time_h": 24, "volume_l": 10}', 50.00, 5.00, 'Склад химии, Секция В', 3, 45.00, 'BYN'),
-- Упаковка (Категория 11)
('BOX-Cardboard-L', 'Коробка картонная большая', 'Коробка L', 11, 1, '{"dimensions_mm": "600x400x400", "layers": 3, "material": "картон"}', 200.00, 20.00, 'Склад упаковки', 3, 1.20, 'BYN');

-- ============================================
-- 4. ПРОДУКЦИЯ (Готовые изделия)
-- ============================================

INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `base_price`, `currency`, `image`, `is_active`) VALUES
-- Двигатели (Категория 1)
('ADM-80A2', 'Двигатель асинхронный 0.75кВт 3000об/мин', 1, 1, '{"power_kw": 0.75, "rpm": 3000, "voltage_v": 380, "frame_size": "80", "efficiency_class": "IE2", "mounting": "IM1081"}', 250.00, 'BYN', 'img/motor_80.jpg', 1),
('ADM-90L4', 'Двигатель асинхронный 1.5кВт 1500об/мин', 1, 1, '{"power_kw": 1.5, "rpm": 1500, "voltage_v": 380, "frame_size": "90L", "efficiency_class": "IE2", "mounting": "IM1081"}', 320.00, 'BYN', 'img/motor_90.jpg', 1),
('ADM-100L6', 'Двигатель асинхронный 2.2кВт 1000об/мин', 1, 1, '{"power_kw": 2.2, "rpm": 1000, "voltage_v": 380, "frame_size": "100L", "efficiency_class": "IE3", "mounting": "IM1081"}', 450.00, 'BYN', 'img/motor_100.jpg', 1),
('ADM-132M4', 'Двигатель асинхронный 5.5кВт 1500об/мин', 1, 1, '{"power_kw": 5.5, "rpm": 1500, "voltage_v": 380, "frame_size": "132M", "efficiency_class": "IE3", "mounting": "IM1081"}', 780.00, 'BYN', 'img/motor_132.jpg', 1),
-- Генераторы (Категория 2)
('GEN-5kW-Diesel', 'Генератор дизельный 5кВт', 2, 1, '{"power_kw": 5, "fuel_type": "diesel", "start_type": "electric", "phases": 1, "noise_db": 75}', 1200.00, 'BYN', 'img/gen_5kw.jpg', 1),
('GEN-10kW-AVR', 'Генератор бензиновый 10кВт с AVR', 2, 1, '{"power_kw": 10, "fuel_type": "petrol", "start_type": "electric", "phases": 3, "avr": true}', 1800.00, 'BYN', 'img/gen_10kw.jpg', 1),
-- Трансформаторы (Категория 3)
('TR-25kVA', 'Трансформатор сухой 25кВА 380/220', 3, 1, '{"power_kva": 25, "voltage_primary": 380, "voltage_secondary": 220, "phases": 3, "cooling": "air"}', 950.00, 'BYN', 'img/tr_25.jpg', 1),
('TR-63kVA', 'Трансформатор сухой 63кВА 380/220', 3, 1, '{"power_kva": 63, "voltage_primary": 380, "voltage_secondary": 220, "phases": 3, "cooling": "air"}', 1600.00, 'BYN', 'img/tr_63.jpg', 1),
-- Щиты (Категория 4)
('SHIELD-GRE-400A', 'Щит ГРЩ 400А', 4, 1, '{"current_rating_a": 400, "type": "GRSH", "ip_rating": "IP31", "dimensions_mm": "800x600x200"}', 2500.00, 'BYN', 'img/shield_grsh.jpg', 1),
('SHIELD-APU-100A', 'Щит автоматики АПУ 100А', 4, 1, '{"current_rating_a": 100, "type": "APU", "ip_rating": "IP54", "controller": "Siemens S7-1200"}', 3200.00, 'BYN', 'img/shield_apu.jpg', 1),
-- Запчасти (Категория 5)
('PART-Bearing-6309', 'Подшипник 6309 для двигателя', 5, 1, '{"compatible_models": ["ADM-100L", "ADM-112M"], "type": "ball"}', 15.00, 'BYN', 'img/part_brg.jpg', 1),
('PART-Fan-132', 'Вентилятор охлаждения D=300мм', 5, 1, '{"diameter_mm": 300, "compatible_frame": "132", "material": "plastic"}', 25.00, 'BYN', 'img/part_fan.jpg', 1),
('PART-Terminal-Box', 'Коробка клеммная КБР-100', 5, 1, '{"current_rating_a": 100, "poles": 3, "material": "aluminum"}', 40.00, 'BYN', 'img/part_box.jpg', 1);

-- ============================================
-- 5. ОПЕРАЦИОННЫЕ ДАННЫЕ
-- ============================================

-- Заказы от клиентов
INSERT INTO `orders` (`order_number`, `contractor_id`, `status`, `order_date`, `delivery_date`, `total_amount`, `currency`, `notes`) VALUES
('ORD-2023-001', 4, 'completed', '2023-10-01', '2023-10-10', 1500.00, 'BYN', 'Срочный заказ'),
('ORD-2023-002', 5, 'processing', '2023-10-05', '2023-10-20', 4500.00, 'BYN', 'Отгрузка частями'),
('ORD-2023-003', 4, 'new', '2023-10-10', '2023-10-25', 0.00, 'BYN', 'Ожидает подтверждения');

-- Позиции заказов
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price_at_order`, `total_price`) VALUES
(1, 1, 2, 250.00, 500.00),
(1, 4, 1, 780.00, 780.00),
(1, 11, 1, 250.00, 250.00),
(2, 5, 2, 1200.00, 2400.00),
(2, 6, 1, 1800.00, 1800.00),
(3, 2, 5, 320.00, 1600.00);

-- Производственные задания
INSERT INTO `production_tasks` (`task_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date_plan`, `responsible_user_id`) VALUES
('TASK-001', 1, 10, 10, 'completed', '2023-09-20', '2023-09-25', 3),
('TASK-002', 2, 5, 5, 'completed', '2023-09-25', '2023-09-30', 3),
('TASK-003', 4, 3, 2, 'in_progress', '2023-10-01', '2023-10-10', 3),
('TASK-004', 5, 2, 0, 'planned', '2023-10-15', '2023-10-20', 3),
('TASK-005', 7, 1, 0, 'planned', '2023-10-20', '2023-10-25', 3);

-- Проверки качества (ОТК)
INSERT INTO `quality_checks` (`task_id`, `inspector_id`, `check_date`, `result`, `defect_count`, `comments`) VALUES
(1, 4, '2023-09-25', 'passed', 0, 'Все параметры в норме'),
(2, 4, '2023-09-30', 'passed', 0, 'Соответствует чертежу'),
(3, 4, '2023-10-05', 'failed', 1, 'Превышена вибрация на одном двигателе'),
(3, 4, '2023-10-06', 'passed', 0, 'Дефект устранен, повторная проверка OK'),
(4, 4, '2023-10-18', 'pending', 0, 'Ожидается сборка'),
(5, 4, '2023-10-22', 'pending', 0, 'Не начато'),
(1, 4, '2023-09-24', 'process', 0, 'Промежуточный контроль обмотки');

-- Серийные номера
INSERT INTO `serial_numbers` (`product_id`, `serial_number`, `production_date`, `status`, `order_id`) VALUES
(1, 'SN-ADM80-230901', '2023-09-20', 'active', 1),
(1, 'SN-ADM80-230902', '2023-09-20', 'active', 1),
(2, 'SN-ADM90-230905', '2023-09-25', 'active', NULL),
(4, 'SN-ADM132-231001', '2023-10-05', 'active', NULL),
(4, 'SN-ADM132-231002', '2023-10-06', 'active', NULL);
