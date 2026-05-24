# Руководство по миграции данных из JSON в базу данных

## Обзор изменений

### Оптимизация базы данных

В рамках оптимизации были удалены следующие неиспользуемые таблицы:
- `passport_templates` - шаблоны паспортов (не используются динамически)
- `product_passport_versions` - история версий паспортов (не ведется)
- `product_documents` - документы хранятся в файловой системе

### Новые таблицы для работы с материалами

Добавлены 2 новые таблицы для хранения данных из JSON файлов:

1. **material_categories** - категории и подкатегории материалов
   - Иерархическая структура категорий
   - Поддержка многоуровневой вложенности
   - Коды категорий для быстрого поиска

2. **materials** - расширенный справочник материалов
   - Полная информация о материалах из JSON
   - Спецификации в формате JSON
   - Привязка к складским остаткам
   - Информация о поставщиках и ценах

### Итоговая структура БД (20 таблиц)

#### Справочники (6 таблиц)
1. `order_statuses` - статусы заказов
2. `production_statuses` - статусы производства
3. `units` - единицы измерения
4. `product_categories` - категории продукции
5. `quality_check_types` - типы проверок качества
6. `user_roles` - роли пользователей

#### Основные таблицы (11 таблиц)
7. `users` - пользователи системы
8. `contractors` - контрагенты
9. `products` - продукция
10. `orders` - заказы
11. `order_items` - позиции заказов
12. `production_orders` - производственные задания
13. `quality_checks` - контроль качества
14. `warehouse_materials` - склад материалов
15. `warehouse_products` - склад готовой продукции
16. `system_settings` - настройки системы
17. `product_serial_numbers` - серийные номера

#### Таблицы серийных номеров и паспортов (3 таблицы)
18. `product_serial_numbers` - серийные номера продукции
19. `passport_dynamic_data` - динамические данные паспортов

#### Таблицы материалов (2 таблицы)
20. `material_categories` - категории материалов
21. `materials` - справочник материалов

## Инструкция по миграции

### Шаг 1: Подготовка

1. Убедитесь, что у вас есть доступ к MySQL/MariaDB серверу
2. Установите необходимые Python пакеты:
```bash
pip install mysql-connector-python
```

### Шаг 2: Настройка подключения

Откройте файл `migrate_json_to_db.py` и отредактируйте параметры подключения:

```python
DB_CONFIG = {
    'host': 'localhost',
    'database': 'polesie_production',
    'user': 'root',
    'password': 'ВАШ_ПАРОЛЬ',  # Укажите пароль
    'charset': 'utf8mb4'
}
```

### Шаг 3: Создание базы данных

Выполните SQL скрипт создания схемы:

```bash
mysql -u root -p < polesie/sql/schema.sql
```

Или через PHPMyAdmin/другой клиент:
1. Откройте файл `polesie/sql/schema.sql`
2. Выполните весь скрипт

### Шаг 4: Запуск миграции

Запустите скрипт миграции:

```bash
cd polesie/sql
python3 migrate_json_to_db.py
```

### Шаг 5: Проверка результатов

После успешной миграции проверьте данные:

```sql
USE polesie_production;

-- Проверка категорий материалов
SELECT COUNT(*) as categories_count FROM material_categories;

-- Проверка материалов
SELECT COUNT(*) as materials_count FROM materials;

-- Просмотр структуры категорий
SELECT 
    mc.id,
    mc.code,
    mc.name,
    mc.level,
    parent.name as parent_name
FROM material_categories mc
LEFT JOIN material_categories parent ON mc.parent_id = parent.id
ORDER BY mc.level, mc.code;

-- Просмотр материалов по категориям
SELECT 
    mc.name as category,
    COUNT(m.id) as materials_count
FROM material_categories mc
LEFT JOIN materials m ON mc.id = m.category_id
GROUP BY mc.id, mc.name
ORDER BY materials_count DESC;
```

## Что переносится из JSON файлов

### Из `list_materials.json`
- ✅ Все категории и подкатегории материалов (8 категорий, 22 подкатегории)
- ✅ Все материалы (50 материалов)
- ✅ Спецификации материалов (в JSON поле)
- ✅ Единицы измерения
- ✅ Коды и наименования

### Из `passports.json`
- ✅ Информация о компании (в system_settings)
- ✅ Паспорта изделий (72 шт.)
- ✅ Технические характеристики (в JSON поле products.specifications)
- ✅ Гарантийная информация
- ✅ Сертификация

### Из `production.json`
- ✅ Категории продукции

## Структура данных после миграции

### material_categories
```
id | code | name | parent_id | level | is_active
---|------|------|-----------|-------|----------
1  | MET  | Металлы и металлопрокат | NULL | 1 | TRUE
11 | MET_STEEL_STRUCTURAL | Сталь конструкционная | 1 | 2 | TRUE
```

### materials
```
id | code | name_full | name_short | category_id | specifications | current_stock
---|------|-----------|------------|-------------|----------------|---------------
1  | ST-BAR-45-010 | Пруток стальной круглый 45 ГОСТ 1050 | Пруток 45 Ø10 | 11 | {...} | 321.52
```

## Решение проблем

### Ошибка подключения к БД
- Проверьте правильность логина/пароля
- Убедитесь, что MySQL сервер запущен
- Проверьте права доступа пользователя

### Ошибка импорта материалов
- Убедитесь, что таблица `units` заполнена
- Проверьте кодировку файлов (UTF-8)

### Дубликаты данных
Скрипт использует `ON DUPLICATE KEY UPDATE`, поэтому повторный запуск безопасен.

## Дополнительные возможности

### Добавление новых материалов вручную

```sql
INSERT INTO materials (code, name_full, name_short, category_id, base_unit_id, current_stock)
VALUES ('NEW-MATERIAL-001', 'Новый материал', 'Новый мат.', 11, 1, 100.0);
```

### Обновление остатков

```sql
UPDATE materials 
SET current_stock = current_stock + 50 
WHERE code = 'ST-BAR-45-010';
```

### Экспорт материалов обратно в JSON

```sql
SELECT 
    mc.name as category,
    m.code,
    m.name_full,
    m.name_short,
    m.specifications
FROM materials m
JOIN material_categories mc ON m.category_id = mc.id
ORDER BY mc.name, m.code
FOR JSON PATH;
```

## Контакты

По вопросам миграции обращайтесь:
- Техническая поддержка ОАО "Полесьеэлектромаш"
- Email: support@polesieelectromash.by
