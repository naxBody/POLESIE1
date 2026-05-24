# Система серийных номеров продукции

## 📋 Обзор

Реализована система учёта выпущенной продукции с индивидуальными серийными номерами, техническими характеристиками, паспортами продуктов и документами (руководства по эксплуатации, сертификаты).

## 🗄️ Структура базы данных

### Таблица `product_serial_numbers`
Хранит информацию о каждом выпущенном изделии:

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Уникальный ID |
| serial_number | VARCHAR(100) | **Индивидуальный серийный номер** (уникальный) |
| product_id | INT | Ссылка на продукт |
| production_order_id | INT | Ссылка на производственный заказ |
| manufacture_date | DATE | Дата выпуска |
| warranty_start | DATE | Начало гарантии |
| warranty_end | DATE | Окончание гарантии |
| status | ENUM | Статус (active, warranty, expired, returned, scrapped) |
| technical_specs | JSON | **Индивидуальные технические характеристики** |
| passport_data | JSON | Данные паспорта |
| manual_file_path | VARCHAR(500) | Путь к руководству |
| notes | TEXT | Примечания |

### Таблица `product_passport_versions`
История изменений паспортов:

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Уникальный ID |
| serial_number_id | INT | Ссылка на серийный номер |
| version_number | INT | Номер версии |
| passport_data | JSON | Данные паспорта на момент версии |
| generated_by | INT | Кто создал версию |
| created_at | TIMESTAMP | Дата создания |

### Таблица `product_documents`
Прикреплённые документы:

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Уникальный ID |
| serial_number_id | INT | Ссылка на серийный номер |
| document_type | ENUM | Тип (manual, certificate, test_report, warranty_card, other) |
| file_name | VARCHAR(255) | Имя файла |
| file_path | VARCHAR(500) | Путь к файлу |
| file_size | INT | Размер файла |
| mime_type | VARCHAR(100) | MIME-тип |
| uploaded_by | INT | Кто загрузил |
| uploaded_at | TIMESTAMP | Дата загрузки |
| description | TEXT | Описание |

## 📁 Файлы

| Файл | Описание |
|------|----------|
| `/workspace/polesie/sql/migrations/001_serial_numbers.sql` | SQL миграция для создания таблиц |
| `/workspace/polesie/modules/production/serial_numbers.php` | Страница управления серийными номерами |
| `/workspace/polesie/modules/production/view_passport.php` | Просмотр и печать паспорта продукта |
| `/workspace/polesie/modules/production/api_passport.php` | API для работы с паспортами и документами |

## 🔧 Установка

### 1. Применение миграции

```bash
mysql -u username -p database_name < /workspace/polesie/sql/migrations/001_serial_numbers.sql
```

Или выполните SQL команды через phpMyAdmin/другой клиент.

### 2. Создание директории для документов

```bash
mkdir -p /workspace/polesie/uploads/documents
chmod 755 /workspace/polesie/uploads/documents
```

### 3. Добавление в меню

Добавьте пункт меню в `/workspace/polesie/includes/sidebar.php`:

```php
<li class="sidebar-menu-item">
    <a href="<?= pageUrl('modules/production/serial_numbers.php') ?>" class="sidebar-menu-link">
        <span class="sidebar-menu-icon">🔢</span>
        <span>Серийные номера</span>
    </a>
</li>
```

## 📖 Использование

### 1. Создание серийного номера

1. Перейдите в раздел **Производство → Серийные номера**
2. Нажмите **+ Добавить**
3. Заполните форму:
   - Серийный номер (уникальный, например: `SN-AIR-080-2-20250123-AB12`)
   - Продукция (выбор из списка)
   - Дата выпуска
   - Гарантия (начало и окончание)
   - Технические характеристики (JSON формат)
   - Примечания

### 2. Автоматическая генерация серийных номеров

Через API можно автоматически генерировать серийные номера:

```javascript
fetch('/polesie/modules/production/api_passport.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=generate_serial&product_id=1&prefix=SN&quantity=10'
})
.then(r => r.json())
.then(data => console.log(data.serials));
// ["SN-AIR-080-2-20250123-AB12", "SN-AIR-080-2-20250123-CD34", ...]
```

### 3. Просмотр и печать паспорта

1. В списке серийных номеров нажмите **📄 Паспорт**
2. Откроется страница с полной информацией:
   - Основная информация о продукте
   - Технические характеристики
   - Прикреплённые документы
   - История изменений
3. Нажмите **🖨️ Печать** для печати в формате A4

### 4. Загрузка документов

В странице паспорта:
1. Нажмите **✏️ Редактировать паспорт**
2. Внизу формы выберите тип документа:
   - 📘 Руководство по эксплуатации
   - 📜 Сертификат
   - 📊 Отчёт о тестировании
   - 🛡️ Гарантийный талон
3. Выберите файл (PDF, DOC, DOCX, JPG, PNG)
4. Добавьте описание
5. Нажмите **Загрузить**

### 5. Редактирование технических характеристик

Технические характеристики хранятся в JSON формате:

```json
{
    "power": "3.0 кВт",
    "voltage": "380В",
    "current": "6.5А",
    "rpm": "3000 об/мин",
    "efficiency": "85%",
    "weight": "45 кг",
    "ip_rating": "IP54"
}
```

Можно редактировать прямо в форме или через API.

## 📊 Статусы серийных номеров

| Статус | Описание | Цвет |
|--------|----------|------|
| `active` | Активен, используется | 🟢 Зелёный |
| `warranty` | На гарантии | 🔵 Синий |
| `expired` | Гарантия истекла | 🟡 Жёлтый |
| `returned` | Возврат от клиента | 🔴 Красный |
| `scrapped` | Списан | ⚫ Серый |

## 🔍 Фильтрация и поиск

На странице серийных номеров доступны фильтры:
- Поиск по серийному номеру или названию продукта
- Фильтр по статусу
- Фильтр по продукту

## 📝 Примеры использования API

### Получение данных о серийном номере

```javascript
fetch('/polesie/modules/production/api_passport.php?action=get&id=1')
    .then(r => r.json())
    .then(data => console.log(data));
```

### Обновление паспорта

```javascript
const formData = new FormData();
formData.append('action', 'update_passport');
formData.append('serial_id', '1');
formData.append('technical_specs', JSON.stringify({
    "power": "3.0 кВт",
    "voltage": "380В"
}));
formData.append('notes', 'Обновлены характеристики после тестирования');

fetch('/polesie/modules/production/api_passport.php', {
    method: 'POST',
    body: formData
});
```

### Загрузка документа

```javascript
const formData = new FormData();
formData.append('action', 'upload_document');
formData.append('serial_id', '1');
formData.append('document_type', 'manual');
formData.append('description', 'Руководство по эксплуатации');
formData.append('document_file', fileInput.files[0]);

fetch('/polesie/modules/production/api_passport.php', {
    method: 'POST',
    body: formData
});
```

## 🖨️ Печать паспорта

Страница печати оптимизирована для формата A4:
- Скрываются элементы навигации
- Чёрно-белый стиль для экономии чернил
- Все технические характеристики
- Список прикреплённых документов
- Даты создания и обновления

Для печати откройте страницу паспорта и нажмите **🖨️ Печать** или добавьте `?print=1` к URL.

## 📐 Формат серийного номера

Рекомендуемый формат: `PREFIX-ARTICLE-YYYYMMDD-RANDOM`

Примеры:
- `SN-AIR-080-2-20250123-AB12`
- `SN-PUMP-50-20250124-CD34`
- `MOT-3PH-100-20250125-EF56`

Где:
- `PREFIX` - префикс (SN, MOT, и т.д.)
- `ARTICLE` - артикул продукта
- `YYYYMMDD` - дата выпуска
- `RANDOM` - случайный код (4 символа)

## 🔒 Права доступа

| Действие | Требуемое право |
|----------|----------------|
| Просмотр списка | `production.read` |
| Создание | `production.create` |
| Редактирование | `production.edit` |
| Удаление | `production.delete` |
| Загрузка документов | `production.edit` |

## 📞 Интеграция с производством

Серийные номера можно создавать автоматически при завершении производственного заказа:

```php
// В обработчике завершения production_orders
$stmt = $pdo->prepare("
    INSERT INTO product_serial_numbers 
    (serial_number, product_id, production_order_id, manufacture_date, status)
    VALUES (?, ?, ?, CURDATE(), 'active')
");
```

---

*Версия: 1.0*  
*Дата: 2025-01-XX*
