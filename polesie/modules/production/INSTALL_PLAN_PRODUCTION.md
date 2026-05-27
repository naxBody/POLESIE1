# 🛠️ ИНСТРУКЦИЯ ПО УСТАНОВКЕ ПЛАНА ПРОИЗВОДСТВА

## ❗ Проблема
Ошибка `Table 'polesie_production.production_plans' doesn't exist` возникает потому, что в базе данных отсутствуют необходимые таблицы для модуля "План производства".

## ✅ Решение

### Шаг 1: Запустите SQL скрипт создания таблиц

**Вариант А - Через phpMyAdmin:**
1. Откройте phpMyAdmin (http://localhost/phpmyadmin)
2. Выберите базу данных `polesie_production`
3. Перейдите на вкладку "SQL"
4. Скопируйте и выполните содержимое файла: `polesie/sql/production_plans_tables.sql`

**Вариант Б - Через командную строку MySQL:**
```bash
cd C:\xampp\htdocs\POLESIE1\polesie
mysql -u root -p polesie_production < sql/production_plans_tables.sql
```

**Вариант В - Через консоль XAMPP:**
1. Откройте панель управления XAMPP
2. Нажмите кнопку "Shell" рядом с MySQL
3. Выполните команду:
```bash
mysql -u root polesie_production < "C:/xampp/htdocs/POLESIE1/polesie/sql/production_plans_tables.sql"
```

### Шаг 2: Проверьте создание таблиц

Выполните SQL запрос:
```sql
SHOW TABLES LIKE 'production_%';
```

Должны появиться таблицы:
- `production_plans` - основные планы производства
- `production_costing` - расчет себестоимости
- `production_material_requirements` - потребность в материалах
- `production_schedules` - рабочие графики
- `work_centers` - рабочие центры
- `demand_analysis` - анализ спроса

### Шаг 3: Проверка работы

1. Откройте браузер
2. Перейдите на страницу: `http://localhost/POLESIE1/polesie/modules/production/plan.php`
3. Ошибка должна исчезнуть, должны отобразиться данные

## 📋 Что создают таблицы?

| Таблица | Назначение |
|---------|------------|
| `work_centers` | Рабочие центры (цеха, участки) |
| `production_plans` | Планы производства по продуктам и датам |
| `production_costing` | Расчет себестоимости планов |
| `production_material_requirements` | Потребность в материалах для планов |
| `production_schedules` | Графики работы смен |
| `demand_analysis` | Прогнозы спроса для планирования |

## 🔍 Если данные не отображаются

### Проверьте наличие продуктов в базе:
```sql
SELECT COUNT(*) FROM products;
```

Если продуктов нет - создайте тестовые данные через интерфейс или SQL.

### Проверьте наличие данных в таблицах:
```sql
SELECT * FROM production_plans LIMIT 5;
SELECT * FROM work_centers;
SELECT * FROM demand_analysis LIMIT 5;
```

### Добавьте тестовый план вручную:
```sql
INSERT INTO production_plans 
(plan_number, product_id, plan_date, planned_quantity, status, priority)
SELECT 
    'PLAN-2025-001',
    id,
    CURDATE(),
    100,
    'planned',
    2
FROM products LIMIT 1;
```

## 📁 Файлы модуля

- `sql/production_plans_tables.sql` - **СКРИПТ СОЗДАНИЯ ТАБЛИЦ** (выполнить обязательно!)
- `modules/production/plan.php` - страница "План производства"
- `modules/production/release_plan.php` - страница "План выпуска заказов"
- `modules/production/PLAN_PRODUCTION_README.md` - подробная документация

## ⚠️ Важно!

1. **Сначала создайте основную схему БД** (`sql/database.sql`), если еще не создана
2. **Затем создайте таблицы производства** (`sql/migration_production.sql`)
3. **В последнюю очередь создайте таблицы плана** (`sql/production_plans_tables.sql`)

## 🆘 Контакты

Если проблема сохраняется:
1. Проверьте логи ошибок PHP в `C:\xampp\apache\logs\error.log`
2. Убедитесь, что подключение к БД настроено в `config/config.php`
3. Проверьте, что пользователь БД имеет права на создание таблиц
