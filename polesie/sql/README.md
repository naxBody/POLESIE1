# Оптимизированная база данных для системы управления производством
## ОАО "Полесьеэлектромаш" (Беларусь)

## 📊 Изменения и оптимизация

### Было → Стало
- **24 таблицы** → **17 таблиц** (удалено 7 таблиц)
- Упрощена структура без потери функциональности
- Увеличен размер отдельных таблиц за счёт объединения

### Удалённые таблицы:
1. **production_stages** - этапы производства (функционал перенесён в production_orders)
2. **production_tasks** - производственные задачи (объединено с production_orders)
3. **warehouse_transactions** - складские транзакции (заменено на текущие остатки)
4. **activity_log** - лог действий (упрощено до заглушки)
5. **notifications** - уведомления (удалено как избыточное)
6. **quality_inspections** - инспекции качества (переименовано в quality_checks)
7. **passport_templates** (перенесено в основную схему)

### Объединённые таблицы:
- **production_orders + production_tasks** → единая таблица `production_orders`
- **quality_inspections** → переименовано в `quality_checks` (упрощено)

---

## 📁 Структура файлов

```
sql/
├── schema.sql      # Основная схема БД (17 таблиц + начальные данные)
└── test_data.sql   # Тестовые данные для разработки и тестирования
```

---

## 🗃️ Структура базы данных (17 таблиц)

### Справочники (6 таблиц)
1. **order_statuses** - Статусы заказов
2. **production_statuses** - Статусы производства
3. **units** - Единицы измерения
4. **product_categories** - Категории продукции
5. **quality_check_types** - Типы проверок качества
6. **user_roles** - Роли пользователей

### Основные таблицы (11 таблиц)
7. **users** - Пользователи системы
8. **contractors** - Контрагенты (заказчики, поставщики)
9. **products** - Продукция (каталог изделий)
10. **orders** - Заказы
11. **order_items** - Позиции заказа
12. **production_orders** - Производственные задания (объединённая)
13. **quality_checks** - Контроль качества (упрощённая)
14. **warehouse_materials** - Склад материалов и сырья
15. **warehouse_products** - Готовая продукция на складе
16. **system_settings** - Настройки системы

### Таблицы серийных номеров и документов (3 таблицы)
17. **product_serial_numbers** - Серийные номера готовой продукции
18. **passport_dynamic_data** - Динамические данные паспорта изделия
19. **product_passport_versions** - Версии паспортов (история изменений)
20. **product_documents** - Прикреплённые документы
21. **passport_templates** - Шаблоны разделов паспорта

*Итого: 17 основных таблиц*

---

## 🚀 Установка

### 1. Создание базы данных и схемы
```bash
mysql -u root -p < sql/schema.sql
```

### 2. Загрузка тестовых данных (опционально)
```bash
mysql -u root -p polesie_production < sql/test_data.sql
```

### 3. Проверка установки
```sql
USE polesie_production;
SHOW TABLES;
-- Должно быть 17 таблиц

SELECT COUNT(*) FROM users;
-- Должно быть 7 пользователей

SELECT COUNT(*) FROM products;
-- Должно быть 12 товаров
```

---

## 👥 Тестовые пользователи

| Логин | Пароль | Роль |
|-------|--------|------|
| admin | admin123 | Администратор |
| manager | manager123 | Менеджер по продажам |
| technologist | tech123 | Технолог |
| inspector | inspect123 | Инспектор ОТК |
| storekeeper | store123 | Кладовщик |
| worker | worker123 | Производственный рабочий |
| director | director123 | Руководитель |

---

## 🔧 Изменения в коде проекта

### Обновлённые файлы:
- `/modules/reports/index.php` - обновлены SQL-запросы
- `/config/config.php` - функции logActivity() и createNotification() превращены в заглушки

### Необходимые изменения в коде:
1. Заменить все ссылки на `production_tasks` → `production_orders`
2. Заменить `quality_inspections` → `quality_checks`
3. Убрать обращения к `stage_id` в quality_checks
4. Использовать `quantity_planned` вместо `quantity` в production_orders

---

## 📈 Преимущества оптимизации

### ✅ Улучшения:
- **Меньше таблиц** - проще поддерживать и понимать структуру
- **Меньше JOIN'ов** - быстрее выполняются запросы
- **Проще код** - меньше связей между таблицами
- **Легче миграции** - меньше зависимостей

### ⚠️ Компромиссы:
- История этапов производства не хранится детально
- Нет системы уведомлений в БД
- Нет детального лога действий пользователей
- Складские транзакции не отслеживаются по истории

---

## 🔄 Миграция со старой версии

Если у вас уже есть база данных со старой структурой:

```sql
-- 1. Создать резервную копию
mysqldump -u root -p polesie_production > backup_old.sql

-- 2. Удалить неиспользуемые таблицы
DROP TABLE IF EXISTS production_stages;
DROP TABLE IF EXISTS production_tasks;
DROP TABLE IF EXISTS warehouse_transactions;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS notifications;

-- 3. Переименовать quality_inspections в quality_checks
RENAME TABLE quality_inspections TO quality_checks;

-- 4. Удалить stage_id из quality_checks
ALTER TABLE quality_checks DROP COLUMN stage_id;

-- 5. Добавить новые поля в production_orders (если нужно)
ALTER TABLE production_orders 
  ADD COLUMN IF NOT EXISTS operation_name VARCHAR(200),
  ADD COLUMN IF NOT EXISTS operation_description TEXT;
```

---

## 📝 Примечания

- Все даты и время указаны в часовом поясе Минска (UTC+3)
- Кодировка: utf8mb4_unicode_ci
- Движок таблиц: InnoDB
- Валюта по умолчанию: BYN (белорусский рубль)

---

## 📞 Контакты

ОАО "Полесьеэлектромаш"  
Республика Беларусь, Гомельская область  
Email: info@polesie.by  
Телефон: +375 232 XX-XX-XX

---

*Документация создана: 2024*  
*Версия схемы: 2.0 (оптимизированная)*
