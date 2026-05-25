# План производства - документация

## 📋 Обзор
Модуль комплексного планирования производства с минимальным количеством таблиц (6 шт).

## 🗄️ Структура БД (6 таблиц)

### 0. `work_centers` - Рабочие центры
**Вспомогательная таблица** для описания производственных мощностей
- `code` - код центра
- `name` - название (Сборочный цех №1, Упаковочный участок и т.д.)
- `type` - assembly/packaging/quality_control/storage/other
- `capacity_hours` - плановая мощность в часах за смену
- `workers_max` - максимальное количество рабочих
- `hourly_rate` - ставка в час

### 1. `production_plans` - Планы производства
**Основная таблица** со всеми планами
- `plan_number` - номер плана (PLAN-2025-001)
- `product_id` - связь с products
- `plan_date` - дата производства
- `planned_quantity` / `actual_quantity` - план/факт
- `demand_forecast` - прогноз спроса
- `status` - planned/in_progress/completed/cancelled
- `priority` - приоритет 1-3 (высокий/средний/низкий)
- `responsible_id` - ответственный сотрудник

### 2. `production_material_requirements` - Потребность в материалах
**Расчет материалов** на основе норм расхода
- `plan_id` - связь с планом
- `material_id` - связь с materials
- `consumption_rate` - норма расхода на ед.продукции
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
**Агрегированные затраты** на план (один к одному с production_plans)
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
production_plans.responsible_id → users.id
production_plans.order_id → orders.id
production_material_requirements.material_id → materials.id
production_material_requirements.plan_id → production_plans.id
production_schedules.work_center_id → work_centers.id
production_schedules.plan_id → production_plans.id
production_costing.plan_id → production_plans.id
demand_analysis.product_id → products.id
```

## 📊 Функционал страницы plan.php

### KPI карточки
- **Планы на неделю** (общее/в работе/в плане)
- **Дефицит материалов** - количество материалов с нехваткой
- **Себестоимость недели** - общая + средняя на план
- **Загрузка мощностей** - количество производственных смен

### Анализ спроса
- Исторические продажи vs прогноз
- Коэффициент тренда (рост/падение %)
- Сезонный фактор
- План на сегодня vs прогноз спроса
- Статус выполнения плана (%)

### Планы на неделю
- Дата, продукт, количество
- План vs прогноз спроса
- Приоритет (цветные бейджи 1-3)
- Полная себестоимость + за единицу
- Статус выполнения
- Ответственный сотрудник

### Потребность в материалах
- Список всех требуемых материалов
- Сравнение: нужно vs есть на складе
- **Выделение дефицита красным**
- Норма расхода на единицу продукции
- Статус (pending/reserved/consumed/shortage)
- Привязка к продукту и дате плана

### Рабочий график
- Почасовое планирование на 7 дней
- Рабочий центр/цех
- Тип смены (утро/день/ночь с иконками)
- Время начала/окончания
- Количество рабочих
- Эффективность (progress bar %)

### Расчет себестоимости
- **Структура затрат**: материалы/работы/накладные
- Визуальная диаграмма (stacked progress bar)
- Детализация по каждому плану
- Процентное соотношение затрат
- Средняя стоимость за единицу

## 🧮 Вычисления

### Расчет материалов
```sql
required_quantity = consumption_rate × planned_quantity
total_cost = required_quantity × unit_cost
```

### Себестоимость
```
total_cost = material_cost + labor_cost + overhead_cost
cost_per_unit = total_cost / planned_quantity
```
Где:
- labor_cost ≈ 30% от material_cost
- overhead_cost ≈ 15% от material_cost

### Прогноз спроса
```
forecast = historical_avg × trend_coefficient × seasonality_factor
```

### Эффективность смены
```
efficiency = (actual_hours / planned_hours) × 100
```

### Статус выполнения плана
```
plan_fulfillment = (today_plan / forecast_value) × 100
≥90% ✓ зеленый
70-90% ! желтый
<70% ✗ красный
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
- `sql/production_plan_extension.sql` - схема БД (6 таблиц + 3 представления)
- `sql/production_plan_test_data.sql` - тестовые данные
- `modules/production/plan.php` - главная страница плана производства
- `modules/production/PLAN_PRODUCTION_README.md` - эта документация

## 💡 Преимущества схемы
1. **Минимум таблиц** - всего 6 новых (включая work_centers)
2. **Нормализация** - нет дублирования данных
3. **Гибкость** - легко расширять функционал
4. **Производительность** - индексы на ключевых полях
5. **Связность** - внешние ключи с каскадным удалением
6. **Представления** - готовые VIEW для аналитики

## 📈 Представления (Views)
- `v_production_plan_summary` - сводка по планам с себестоимостью
- `v_material_requirements_detail` - детализация материалов с остатками
- `v_work_center_load` - загрузка рабочих центров
