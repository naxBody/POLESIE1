#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Скрипт миграции данных из JSON файлов в базу данных MySQL
ОАО "Полесьеэлектромаш"
"""

import json
import mysql.connector
from mysql.connector import Error
import sys
import os
from typing import Optional

# Конфигурация подключения к БД
DB_CONFIG = {
    'host': 'localhost',
    'database': 'polesie_production',
    'user': 'root',
    'password': '',  # Укажите ваш пароль
    'charset': 'utf8mb4'
}


def connect_to_database() -> Optional[mysql.connector.connection.MySQLConnection]:
    """Подключение к базе данных"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        if connection.is_connected():
            print("✓ Подключение к базе данных успешно")
            return connection
    except Error as e:
        print(f"✗ Ошибка подключения к БД: {e}")
        return None
    return None


def load_json_file(filepath: str) -> dict:
    """Загрузка JSON файла"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"✗ Ошибка загрузки {filepath}: {e}")
        return {}


def migrate_materials(connection, materials_data: dict):
    """Миграция материалов из list_materials.json"""
    cursor = connection.cursor()
    
    print("\n=== Миграция материалов ===")
    
    categories = materials_data.get('categories', [])
    categories_mapping = {}
    
    # Создаем категории материалов
    for category in categories:
        cat_id = category.get('id')
        cat_code = category.get('code')
        cat_name = category.get('name_ru')
        parent_id = category.get('parent_id')
        
        try:
            cursor.execute("""
                INSERT INTO material_categories (code, name, parent_id, level, is_active)
                VALUES (%s, %s, %s, %s, TRUE)
                ON DUPLICATE KEY UPDATE name = VALUES(name)
            """, (cat_code, cat_name, parent_id, category.get('level', 1)))
            
            new_id = cursor.lastrowid
            categories_mapping[cat_id] = new_id
            print(f"  ✓ Категория: {cat_name} (ID: {new_id})")
        except Error as e:
            print(f"  ✗ Ошибка категории {cat_name}: {e}")
    
    connection.commit()
    
    # Создаем подкатегории и материалы
    materials_count = 0
    for category in categories:
        subcategories = category.get('subcategories', [])
        for subcat in subcategories:
            subcat_id = subcat.get('id')
            subcat_code = subcat.get('code')
            subcat_name = subcat.get('name_ru')
            
            try:
                # Вставляем подкатегорию
                cursor.execute("""
                    INSERT INTO material_categories (code, name, parent_id, level, is_active)
                    VALUES (%s, %s, %s, %s, TRUE)
                    ON DUPLICATE KEY UPDATE name = VALUES(name)
                """, (subcat_code, subcat_name, categories_mapping.get(category.get('id')), subcat.get('level', 2)))
                
                new_subcat_id = cursor.lastrowid
                categories_mapping[subcat_id] = new_subcat_id
                
            except Error as e:
                print(f"  ✗ Ошибка подкатегории {subcat_name}: {e}")
                continue
            
            # Вставляем материалы
            materials = subcat.get('materials', [])
            for mat in materials:
                mat_code = mat.get('code_internal')
                mat_name_full = mat.get('name_full')
                mat_name_short = mat.get('name_short')
                base_unit = mat.get('base_unit')
                specs = json.dumps(mat.get('specifications', {}), ensure_ascii=False)
                
                try:
                    # Находим единицу измерения
                    cursor.execute("SELECT id FROM units WHERE short_name = %s OR name = %s", (base_unit, base_unit))
                    unit_result = cursor.fetchone()
                    unit_id = unit_result[0] if unit_result else None
                    
                    cursor.execute("""
                        INSERT INTO materials 
                        (code, name_full, name_short, category_id, base_unit_id, specifications, is_active)
                        VALUES (%s, %s, %s, %s, %s, %s, TRUE)
                        ON DUPLICATE KEY UPDATE name_full = VALUES(name_full)
                    """, (mat_code, mat_name_full, mat_name_short, new_subcat_id, unit_id, specs))
                    
                    materials_count += 1
                    
                except Error as e:
                    print(f"  ✗ Ошибка материала {mat_name_short}: {e}")
    
    connection.commit()
    print(f"\n✓ Материалов импортировано: {materials_count}")
    cursor.close()


def migrate_passports(connection, passports_data: dict):
    """Миграция паспортов изделий из passports.json"""
    cursor = connection.cursor()
    
    print("\n=== Миграция паспортов изделий ===")
    
    company_info = passports_data.get('company', {})
    passports = passports_data.get('passports', [])
    
    # Сохраняем информацию о компании в настройки
    if company_info:
        settings = [
            ('passport_company_name', company_info.get('name', ''), 'string', 'Название компании для паспортов'),
            ('passport_company_address', company_info.get('address', ''), 'string', 'Адрес компании'),
            ('passport_company_phone', company_info.get('phone', ''), 'string', 'Телефон компании'),
            ('passport_company_email', company_info.get('email', ''), 'string', 'Email компании'),
            ('passport_company_unp', company_info.get('unp', ''), 'string', 'УНП компании'),
        ]
        
        for key, value, type_, desc in settings:
            try:
                cursor.execute("""
                    INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
                    VALUES (%s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                """, (key, value, type_, desc))
            except Error as e:
                print(f"  ✗ Ошибка настройки {key}: {e}")
        
        connection.commit()
        print(f"  ✓ Информация о компании сохранена")
    
    # Импортируем паспорта
    passports_count = 0
    for passport in passports:
        sku = passport.get('sku', '')
        basic_info = passport.get('basic_info', {})
        specifications = passport.get('specifications', {})
        warranty = passport.get('warranty', {})
        комплектация = passport.get('комплектация', {})
        certification = passport.get('сертификация', {})
        
        try:
            # Находим продукт по SKU
            cursor.execute("SELECT id FROM products WHERE article = %s", (sku,))
            product_result = cursor.fetchone()
            
            if product_result:
                product_id = product_result[0]
                
                # Сохраняем спецификации как JSON
                specs_json = json.dumps({
                    'specifications': specifications,
                    'warranty': warranty,
                    'комплектация': комплектация,
                    'сертификация': certification
                }, ensure_ascii=False)
                
                cursor.execute("""
                    UPDATE products SET specifications = %s WHERE id = %s
                """, (specs_json, product_id))
                
                passports_count += 1
            else:
                # Продукт не найден, создаем новый
                cursor.execute("""
                    INSERT INTO products (article, name, description, specifications, is_active)
                    VALUES (%s, %s, %s, %s, TRUE)
                """, (
                    sku,
                    basic_info.get('name_full', ''),
                    basic_info.get('category', ''),
                    json.dumps(specifications, ensure_ascii=False)
                ))
                passports_count += 1
                
        except Error as e:
            print(f"  ✗ Ошибка паспорта {sku}: {e}")
    
    connection.commit()
    print(f"\n✓ Паспортов импортировано: {passports_count}")
    cursor.close()


def migrate_production_categories(connection, production_data: dict):
    """Миграция категорий продукции из production.json"""
    cursor = connection.cursor()
    
    print("\n=== Миграция категорий продукции ===")
    
    categories = production_data.get('categories', [])
    
    for cat in categories:
        cat_name = cat.get('name_ru', '')
        cat_code = cat.get('code', '')
        
        try:
            cursor.execute("""
                INSERT INTO product_categories (name, description, is_active)
                VALUES (%s, %s, TRUE)
                ON DUPLICATE KEY UPDATE name = VALUES(name)
            """, (cat_name, cat.get('description_ru', '')))
            
            print(f"  ✓ Категория: {cat_name}")
            
        except Error as e:
            print(f"  ✗ Ошибка категории {cat_name}: {e}")
    
    connection.commit()
    cursor.close()


def optimize_schema(connection):
    """Оптимизация схемы БД - удаление неиспользуемых таблиц"""
    cursor = connection.cursor()
    
    print("\n=== Оптимизация схемы БД ===")
    
    # Таблицы, которые можно удалить (не используются в текущей логике)
    tables_to_drop = [
        'passport_templates',  # Шаблоны не используются динамически
        'product_passport_versions',  # История версий не ведется
        'product_documents',  # Документы хранятся в файловой системе
    ]
    
    # Проверяем существование таблиц перед удалением
    cursor.execute("SHOW TABLES")
    existing_tables = [table[0] for table in cursor.fetchall()]
    
    for table in tables_to_drop:
        if table in existing_tables:
            try:
                cursor.execute(f"DROP TABLE IF EXISTS `{table}`")
                print(f"  ✓ Удалена таблица: {table}")
            except Error as e:
                print(f"  ✗ Ошибка удаления {table}: {e}")
        else:
            print(f"  ℹ Таблица {table} не существует")
    
    connection.commit()
    cursor.close()


def create_additional_tables(connection):
    """Создание дополнительных таблиц для материалов"""
    cursor = connection.cursor()
    
    print("\n=== Создание таблиц для материалов ===")
    
    # Таблица категорий материалов
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS `material_categories` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `code` VARCHAR(50) UNIQUE,
          `name` VARCHAR(200) NOT NULL,
          `parent_id` INT DEFAULT NULL,
          `level` INT DEFAULT 1,
          `is_active` BOOLEAN DEFAULT TRUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
          INDEX `idx_code` (`code`),
          INDEX `idx_parent` (`parent_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Категории и подкатегории материалов';
    """)
    print("  ✓ Таблица material_categories создана/обновлена")
    
    # Таблица материалов
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS `materials` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `code` VARCHAR(50) NOT NULL UNIQUE,
          `name_full` VARCHAR(500) NOT NULL,
          `name_short` VARCHAR(200),
          `category_id` INT,
          `base_unit_id` INT,
          `specifications` JSON,
          `current_stock` DECIMAL(15,3) DEFAULT 0.00,
          `min_stock` DECIMAL(15,3) DEFAULT 0.00,
          `location` VARCHAR(100),
          `supplier_id` INT,
          `last_price` DECIMAL(15,2),
          `currency` CHAR(3) DEFAULT 'BYN',
          `is_active` BOOLEAN DEFAULT TRUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`category_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
          FOREIGN KEY (`base_unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL,
          FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
          INDEX `idx_code` (`code`),
          INDEX `idx_category` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Справочник материалов';
    """)
    print("  ✓ Таблица materials создана/обновлена")
    
    connection.commit()
    cursor.close()


def main():
    """Основная функция миграции"""
    print("=" * 60)
    print("МИГРАЦИЯ ДАННЫХ ИЗ JSON В БАЗУ ДАННЫХ")
    print("ОАО \"Полесьеэлектромаш\"")
    print("=" * 60)
    
    # Загружаем JSON файлы
    print("\nЗагрузка JSON файлов...")
    
    # Вычисляем путь до корня проекта относительно расположения скрипта
    PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    
    materials_data = load_json_file(os.path.join(PROJECT_ROOT, 'list_materials.json'))
    passports_data = load_json_file(os.path.join(PROJECT_ROOT, 'passports.json'))
    production_data = load_json_file(os.path.join(PROJECT_ROOT, 'production.json'))
    
    if not materials_data:
        print("✗ Не удалось загрузить list_materials.json")
        return
    
    # Подключаемся к БД
    connection = connect_to_database()
    if not connection:
        print("\n✗ Не удалось подключиться к базе данных")
        print("Проверьте параметры подключения в скрипте")
        return
    
    try:
        # Оптимизируем схему
        optimize_schema(connection)
        
        # Создаем дополнительные таблицы
        create_additional_tables(connection)
        
        # Мигрируем данные
        if materials_data:
            migrate_materials(connection, materials_data)
        
        if passports_data:
            migrate_passports(connection, passports_data)
        
        if production_data:
            migrate_production_categories(connection, production_data)
        
        print("\n" + "=" * 60)
        print("✓ МИГРАЦИЯ ЗАВЕРШЕНА УСПЕШНО")
        print("=" * 60)
        
    except Exception as e:
        print(f"\n✗ Критическая ошибка: {e}")
        connection.rollback()
    finally:
        if connection.is_connected():
            connection.close()
            print("\n✓ Подключение к БД закрыто")


if __name__ == '__main__':
    main()