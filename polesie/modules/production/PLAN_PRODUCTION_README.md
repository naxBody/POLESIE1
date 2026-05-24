# План производства - документация

## 📋 Обзор
Модуль комплексного планирования производства с минимальным количеством таблиц (5 шт).

## 🗄️ Структура БД (5 таблиц)

### 1. `production_plans` - Планы производства
**Основная таблица** со всеми планами
- `product_id` - связь с products
- `plan_date` - дата производства
- `planned_quantity` / `actual_quantity` - план/факт
- `demand_forecast` - прогноз спроса
- `status` - planned/in_progress/completed/cancelled
- `priority` - приоритет 1-3

### 2. `production_material_requirements` - Потребность в материалах
**Расчет материалов** на основе норм расхода
- `plan_id` - связь с планом
- `material_id` - связь с materials
- `required_quantity` = норма × planned_quantity
- `reserved_quantity` / `actual_quantity` - зарезервировано/списано
- `unit_cost` / `total_cost` - стоимость
- `status` - pending/reserved/consumed/shortage

### 3. `production_schedules` - Рабочие графики
**Загрузка мощностей** по сменам
- `plan_id` - связь с планом
- `work_center_id` - связь с work_centers
- `schedule_date` - дата смены
- `shift_type` - morning/afternoon/night
- `start_time` / `end_time` - время
- `planned_hours` / `actual_hours` - часы
- `workers_count` - количество рабочих
- `efficiency_percent` - эффективность %

### 4. `production_costing` - Расчет себестоимости
**Агрегированные затраты** на план
- `plan_id` - связь с планом (UNIQUE)
- `material_cost` - материалы
- `labor_cost` - работы
- `overhead_cost` - накладные расходы
- `total_cost` - итого
- `cost_per_unit` - за единицу

### 5. `demand_analysis` - Анализ спроса
**Прогнозы и тренды** для планирования
- `product_id` - связь с products
- `analysis_date` - дата анализа
- `period_type` - daily/weekly/monthly
- `historical_avg` - историческое среднее
- `forecast_value` - прогноз
- `trend_coefficient` - коэффициент тренда
- `seasonality_factor` - сезонность
- `confidence_level` - достоверность %
- `variance_percent` - отклонение %

## 🔗 Связи с существующей БД
```
production_plans.product_id → products.id
production_material_requirements.material_id → materials.id
production_material_requirements.plan_id → production_plans.id
production_schedules.work_center_id → work_centers.id
production_schedules.plan_id → production_plans.id
production_costing.plan_id → production_plans.id
demand_analysis.product_id → products.id
```

## 📊 Функционал страницы plan.php

### KPI карточки
- Планы на неделю (общее/в работе/в плане)
- Дефицит материалов
- Общая себестоимость недели
- Загрузка мощностей (кол-во смен)

### Анализ спроса
- Исторические продажи vs прогноз
- Коэффициент тренда (рост/падение %)
- План на сегодня vs прогноз
- Статус выполнения

### Планы на неделю
- Дата, продукт, количество
- План vs прогноз спроса
- Объем материалов
- Полная себестоимость + за единицу
- Приоритет (флажки 1-3)
- Статус выполнения

### Потребность в материалах
- Список всех требуемых материалов
- Сравнение: нужно vs есть на складе
- **Выделение дефицита** красным
- Привязка к продукту и дате

### Рабочий график
- Почасовое планирование на 7 дней
- Цех/рабочий центр
- Тип смены (утро/день/ночь)
- Время начала/окончания
- Количество рабочих
- Эффективность (progress bar)

### Расчет себестоимости
- **Структура затрат**: материалы/работы/накладные
- Визуальная диаграмма (stacked progress bar)
- Детализация по каждому плану
- Средняя стоимость за единицу
- Экспорт калькуляции

## 🧮 Вычисления

### Расчет материалов
```sql
required_quantity = product.material_consumption_rate × planned_quantity
total_cost = required_quantity × material.unit_cost
```

### Себестоимость единицы
```
cost_per_unit = total_cost / planned_quantity
total_cost = material_cost + labor_cost + overhead_cost
```

### Прогноз спроса
```
forecast = historical_avg × trend_coefficient × seasonality_factor
```

### Эффективность смены
```
efficiency = (actual_hours / planned_hours) × 100
```

## 🚀 Установка

```bash
# 1. Создать таблицы
mysql -u user -p database < sql/production_plan_extension.sql

# 2. Загрузить тестовые данные
mysql -u user -p database < sql/production_plan_test_data.sql

# 3. Открыть страницу
http://localhost/polesie/modules/production/plan.php
```

## 📁 Файлы
- `sql/production_plan_extension.sql` - схема БД (5 таблиц)
- `sql/production_plan_test_data.sql` - тестовые данные
- `modules/production/plan.php` - главная страница
- `modules/production/PLAN_PRODUCTION_README.md` - эта документация

## 💡 Преимущества схемы
1. **Минимум таблиц** - всего 5 новых
2. **Нормализация** - нет дублирования данных
3. **Гибкость** - легко расширять
4. **Производительность** - индексы на ключевых полях
5. **Связность** - внешние ключи с каскадным удалением
