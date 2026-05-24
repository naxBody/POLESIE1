# Инструкция по внедрению динамических данных паспорта изделия

## Обзор изменений

Реализована возможность редактирования данных паспорта изделия через веб-интерфейс с хранением в базе данных. Теперь можно менять:
- Гарантийные сроки (начало, окончание, продолжительность)
- Даты (изготовления, выпуска)
- Данные организации (название, адрес, телефон, email)
- Наименование изделия (кастомное)
- Описание изделия

## 1. Применение миграции БД

Выполните SQL-миграцию для создания таблицы `passport_dynamic_data`:

```bash
mysql -u your_user -p your_database < /workspace/polesie/sql/migrations/002_passport_dynamic_data.sql
```

Или выполните SQL вручную:

```sql
-- Таблица для хранения редактируемых данных паспорта изделия
CREATE TABLE IF NOT EXISTS `passport_dynamic_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL UNIQUE,
  `warranty_start` DATE,
  `warranty_end` DATE,
  `warranty_months` INT DEFAULT 12,
  `warranty_period` VARCHAR(100),
  `manufacture_date` DATE,
  `release_date` DATE,
  `product_name_custom` VARCHAR(255),
  `product_description` TEXT,
  `company_name` VARCHAR(255),
  `company_address` VARCHAR(500),
  `company_phone` VARCHAR(50),
  `company_email` VARCHAR(100),
  `additional_sections` JSON,
  `custom_fields` JSON,
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_serial` (`serial_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавление поля в product_serial_numbers
ALTER TABLE `product_serial_numbers` 
ADD COLUMN IF NOT EXISTS `has_dynamic_passport` BOOLEAN DEFAULT FALSE;
```

## 2. Изменённые файлы

### `/workspace/polesie/modules/production/api_passport.php`
- Добавлена поддержка получения динамических данных паспорта (action=get)
- Обновлён метод update_passport для сохранения:
  - Дат гарантии (warranty_start, warranty_end, warranty_months)
  - Даты изготовления (manufacture_date)
  - Данных организации (company_name, company_address, company_phone, company_email)
  - Кастомного названия продукта (product_name_custom)
  - Описания продукта (product_description)

### `/workspace/polesie/modules/warehouse/print_passport.php`
- Добавлена загрузка динамических данных из таблицы `passport_dynamic_data`
- При печати используются кастомные данные если они существуют
- Приоритет: динамические данные > данные серийного номера > значения по умолчанию

### `/workspace/polesie/modules/production/view_passport.php`
- Добавлена загрузка динамических данных паспорта
- Расширено модальное окно редактирования с полями:
  - 📅 Даты и гарантия (дата изготовления, гарантийный срок, начало/окончание гарантии)
  - 🏢 Данные организации (название, адрес, телефон, email)
  - 📦 Информация об изделии (кастомное название, описание)
  - ⚙️ Технические характеристики (JSON)
  - Примечания

## 3. Как использовать

### Просмотр паспорта
1. Перейдите в раздел "Производство" → "Серийные номера"
2. Откройте паспорт нужного изделия
3. Нажмите кнопку "✏️ Редактировать паспорт"

### Редактирование данных
В модальном окне доступны следующие поля:

**Даты и гарантия:**
- Дата изготовления
- Гарантийный срок (месяцев)
- Начало гарантии
- Окончание гарантии

**Данные организации:**
- Название организации
- Адрес
- Телефон
- E-mail

**Информация об изделии:**
- Наименование изделия (кастомное) - если оставить пустым, используется стандартное
- Описание изделия

**Технические характеристики:**
- JSON с техническими параметрами

После заполнения нажмите "💾 Сохранить изменения".

### Печать паспорта
1. В просмотре паспорта нажмите "🖨️ Печать"
2. Откроется версия для печати с актуальными данными
3. Все изменённые поля будут отображены из базы данных

## 4. Структура данных

### Таблица `passport_dynamic_data`

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | ID записи |
| serial_number_id | INT | ID серийного номера (внешний ключ) |
| warranty_start | DATE | Начало гарантийного срока |
| warranty_end | DATE | Окончание гарантийного срока |
| warranty_months | INT | Продолжительность гарантии (мес.) |
| manufacture_date | DATE | Дата изготовления |
| product_name_custom | VARCHAR(255) | Кастомное название изделия |
| product_description | TEXT | Описание изделия |
| company_name | VARCHAR(255) | Название организации |
| company_address | VARCHAR(500) | Адрес организации |
| company_phone | VARCHAR(50) | Телефон организации |
| company_email | VARCHAR(100) | Email организации |
| created_by | INT | ID пользователя, создавшего запись |
| created_at | TIMESTAMP | Дата создания |
| updated_at | TIMESTAMP | Дата обновления |

## 5. Логика работы

1. **При сохранении паспорта:**
   - Проверяется наличие записи в `passport_dynamic_data` для данного серийного номера
   - Если запись есть - обновляется
   - Если записи нет - создаётся новая

2. **При просмотре/печати:**
   - Сначала проверяются динамические данные
   - Если их нет - используются данные из `product_serial_numbers`
   - Если и их нет - значения по умолчанию

3. **Версионность:**
   - Все изменения сохраняются в `product_passport_versions`
   - Можно отследить историю изменений паспорта

## 6. Безопасность

- Требуется авторизация
- Необходимы права `production.edit` для редактирования
- Все данные валидируются перед сохранением
- Используется подготовленные выражения для защиты от SQL-инъекций

## 7. Совместимость

Изменения обратно совместимы:
- Существующие паспорта продолжают работать
- Если нет динамических данных, используются старые поля
- Структура печати не изменена

## 8. Тестирование

1. Создайте новый серийный номер
2. Откройте его паспорт
3. Нажмите "Редактировать паспорт"
4. Измените гарантийные сроки и данные организации
5. Сохраните
6. Распечатайте паспорт - должны отобразиться новые данные
7. Проверьте в БД таблицу `passport_dynamic_data`
