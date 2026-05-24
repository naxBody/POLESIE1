# Оптимизированная база данных ОАО "Полесьеэлектромаш"

## Обзор изменений

### Что было сделано:

1. **Оптимизирована структура базы данных** - удалены лишние таблицы, упрощена схема
2. **Созданы нормализованные таблицы** для продукции, материалов и категорий
3. **Загружены реальные данные** из JSON файлов в базу данных

### Новая структура БД (15 таблиц):

#### Справочники (7 таблиц):
- `units` - Единицы измерения
- `order_statuses` - Статусы заказов
- `production_statuses` - Статусы производства
- `user_roles` - Роли пользователей
- `quality_check_types` - Типы проверок качества
- `product_categories` - Категории продукции (иерархия)
- `material_categories` - Категории материалов (иерархия)

#### Основные таблицы (8 таблиц):
- `users` - Пользователи системы
- `contractors` - Контрагенты
- `products` - Продукция (каталог изделий)
- `materials` - Материалы и сырьё
- `orders` - Заказы
- `order_items` - Позиции заказа
- `production_orders` - Производственные задания
- `product_serial_numbers` - Серийные номера продукции
- `quality_checks` - Контроль качества
- `system_settings` - Настройки системы

### Удалены избыточные таблицы:
- `warehouse_materials` (объединено с `materials`)
- `warehouse_products` (управление через `product_serial_numbers`)
- `passport_dynamic_data` (данные в `product_serial_numbers.passport_data`)
- `product_passport_versions` (избыточно при наличии JSON)
- `product_documents` (хранение путей в `product_serial_numbers`)

## Загруженные данные

### Продукция (64 товара):
- Электродвигатели асинхронные трехфазные (АИР, 2АИР, специального назначения, взрывозащищенные)
- Электродвигатели однофазные (АИРЕ, АИС)
- Электронасосы (ГНОМ, консольные, бытовые)
- Чугунное литье (колосниковые решетки, изделия)
- Алюминиевое литье
- Отливки по чертежам заказчика

### Материалы (50 позиций):
- Металлы и металлопрокат (сталь, чугун, алюминий)
- Электротехнические материалы (медный провод, электросталь)
- Крепёжные изделия (болты, гайки, шайбы, винты)
- Подшипники
- Изоляционные материалы
- Химикаты, ЛКМ, СОЖ
- Упаковка и маркировка
- Расходные материалы и инструмент

## Файлы

| Файл | Описание |
|------|----------|
| `polesie_production_optimized.sql` | Схема базы данных + начальные справочники |
| `polesie_data.sql` | Данные продукции и материалов |
| `polesie_full.sql` | Полный файл (схема + данные) |
| `generate_data.py` | Скрипт генерации SQL из JSON |

## Установка

```bash
# Создать базу данных и загрузить схему с данными
mysql -u root -p < polesie_full.sql

# Или раздельно:
mysql -u root -p < polesie_production_optimized.sql
mysql -u root -p polesie_production < polesie_data.sql
```

## Примеры запросов

### Получить все категории продукции с количеством товаров:
```sql
SELECT 
    pc.id,
    pc.name,
    pc.level,
    COUNT(p.id) as product_count
FROM product_categories pc
LEFT JOIN products p ON p.category_id = pc.id
GROUP BY pc.id, pc.name, pc.level
ORDER BY pc.level, pc.name;
```

### Получить продукцию с характеристиками:
```sql
SELECT 
    p.sku,
    p.code_gost,
    p.name,
    p.power_kw_min,
    p.power_kw_max,
    p.rpm,
    p.efficiency_class,
    pc.name as category
FROM products p
JOIN product_categories pc ON p.category_id = pc.id
WHERE p.is_active = TRUE
ORDER BY pc.name, p.sku;
```

### Получить материалы по категориям:
```sql
SELECT 
    m.code,
    m.name_full,
    m.material_grade,
    m.standard_doc,
    m.current_stock,
    m.min_stock,
    mc.name as category,
    u.short_name as unit
FROM materials m
JOIN material_categories mc ON m.category_id = mc.id
JOIN units u ON m.base_unit_id = u.id
WHERE m.is_active = TRUE
ORDER BY mc.name, m.name_full;
```

### Проверка остатков материалов:
```sql
SELECT 
    m.name_short,
    m.current_stock,
    m.min_stock,
    CASE 
        WHEN m.current_stock <= m.min_stock THEN '⚠️ Требуется заказ'
        ELSE '✓ В норме'
    END as status
FROM materials m
WHERE m.is_active = TRUE
ORDER BY 
    CASE WHEN m.current_stock <= m.min_stock THEN 0 ELSE 1 END,
    m.current_stock / m.min_stock;
```

## Контакты предприятия

**ОАО "Полесьеэлектромаш"**
- Адрес: 225644, Брестская область, г. Лунинец, ул. Красная, 179
- Телефон: +375 1647 2-78-09
- Email: polesie@polesieelectromash.by
- Сайт: https://polesieelectromash.by
- УНП: 200106183
