#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Генерация SQL данных для продукции и материалов ОАО "Полесьеэлектромаш"
"""

import json

def load_json(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        return json.load(f)

def escape_sql_string(s):
    if s is None:
        return 'NULL'
    return "'" + str(s).replace("'", "''") + "'"

def generate_product_categories(production_data):
    """Генерация категорий продукции"""
    sql = []
    categories = {}
    
    for cat in production_data.get('categories', []):
        cat_id = cat.get('id')
        cat_code = cat.get('code', '')
        cat_name = cat.get('name_ru', '')
        
        # Добавляем категорию верхнего уровня
        sql.append(f"INSERT INTO `product_categories` (`code`, `name`, `parent_id`, `level`, `description`) VALUES ({escape_sql_string(cat_code)}, {escape_sql_string(cat_name)}, NULL, 1, NULL);")
        categories[cat_id] = {'sql_id': cat_id, 'name': cat_name}
        
        # Подкатегории
        for subcat in cat.get('subcategories', []):
            sub_id = subcat.get('id')
            sub_code = subcat.get('code', '')
            sub_name = subcat.get('name_ru', '')
            
            sql.append(f"INSERT INTO `product_categories` (`code`, `name`, `parent_id`, `level`, `description`) VALUES ({escape_sql_string(sub_code)}, {escape_sql_string(sub_name)}, {cat_id}, 2, NULL);")
            categories[sub_id] = {'sql_id': sub_id, 'name': sub_name, 'parent': cat_id}
    
    return '\n'.join(sql), categories

def generate_products(production_data, categories):
    """Генерация продукции"""
    sql = []
    product_id = 0
    
    for cat in production_data.get('categories', []):
        for subcat in cat.get('subcategories', []):
            category_id = subcat.get('id')
            
            for product in subcat.get('products', []):
                product_id += 1
                sku = product.get('sku', '')
                code_gost = product.get('code_gost', '')
                name_full = product.get('name_full', '')
                name_short = product.get('name_short', '')
                specs = product.get('specs', {})
                
                power_min = specs.get('power_kw_min')
                power_max = specs.get('power_kw_max')
                if power_min is None and 'power_kw' in specs:
                    power_min = specs.get('power_kw')
                    power_max = specs.get('power_kw')
                
                rpm = specs.get('rpm')
                shaft_height = specs.get('shaft_height_mm')
                voltage = specs.get('voltage_v')
                frequency = specs.get('frequency_hz', 50)
                efficiency = specs.get('efficiency_class')
                protection = specs.get('protection_class')
                mounting = specs.get('mounting_versions')
                climate = specs.get('climate_versions')
                
                is_serial = product.get('is_serial_tracked', True)
                warranty = product.get('warranty_months', 24)
                
                # Формируем SQL вставку
                mounting_json = json.dumps(mounting, ensure_ascii=False) if mounting else 'NULL'
                climate_json = json.dumps(climate, ensure_ascii=False) if climate else 'NULL'
                
                sql.append(f"""INSERT INTO `products` (`sku`, `code_gost`, `name`, `name_short`, `category_id`, `power_kw_min`, `power_kw_max`, `rpm`, `shaft_height_mm`, `voltage_v`, `frequency_hz`, `efficiency_class`, `protection_class`, `mounting_versions`, `climate_versions`, `is_serial_tracked`, `warranty_months`) VALUES (
{escape_sql_string(sku)}, 
{escape_sql_string(code_gost)}, 
{escape_sql_string(name_full)}, 
{escape_sql_string(name_short)}, 
{category_id}, 
{power_min if power_min else 'NULL'}, 
{power_max if power_max else 'NULL'}, 
{rpm if rpm else 'NULL'}, 
{shaft_height if shaft_height else 'NULL'}, 
{escape_sql_string(voltage) if voltage else 'NULL'}, 
{frequency if frequency else 'NULL'}, 
{escape_sql_string(efficiency) if efficiency else 'NULL'}, 
{escape_sql_string(protection[0]) if protection and isinstance(protection, list) else escape_sql_string(protection) if protection else 'NULL'}, 
{mounting_json}, 
{climate_json}, 
{1 if is_serial else 0}, 
{warranty}
);""")
    
    return '\n'.join(sql)

def generate_material_categories(materials_data):
    """Генерация категорий материалов"""
    sql = []
    categories = {}
    
    for cat in materials_data.get('categories', []):
        cat_id = cat.get('id')
        cat_code = cat.get('code', '')
        cat_name = cat.get('name_ru', '')
        
        sql.append(f"INSERT INTO `material_categories` (`code`, `name`, `parent_id`, `level`, `description`) VALUES ({escape_sql_string(cat_code)}, {escape_sql_string(cat_name)}, NULL, 1, NULL);")
        categories[cat_id] = {'sql_id': cat_id, 'name': cat_name}
        
        for subcat in cat.get('subcategories', []):
            sub_id = subcat.get('id')
            sub_code = subcat.get('code', '')
            sub_name = subcat.get('name_ru', '')
            
            sql.append(f"INSERT INTO `material_categories` (`code`, `name`, `parent_id`, `level`, `description`) VALUES ({escape_sql_string(sub_code)}, {escape_sql_string(sub_name)}, {cat_id}, 2, NULL);")
            categories[sub_id] = {'sql_id': sub_id, 'name': sub_name, 'parent': cat_id}
    
    return '\n'.join(sql), categories

def generate_materials(materials_data, categories):
    """Генерация материалов"""
    sql = []
    
    # Сопоставление единиц измерения
    unit_map = {
        'кг': 4,  # Килограмм
        'м': 3,   # Метр
        'шт': 1,  # Штука
        'л': 6,   # Литр
    }
    
    for cat in materials_data.get('categories', []):
        for subcat in cat.get('subcategories', []):
            category_id = subcat.get('id')
            
            for mat in subcat.get('materials', []):
                code = mat.get('code_internal', '')
                name_full = mat.get('name_full', '')
                name_short = mat.get('name_short', '')
                base_unit = mat.get('base_unit', 'кг')
                specs = mat.get('specifications', {})
                
                material_grade = specs.get('material_grade', '')
                standard_doc = specs.get('standard_doc', '')
                product_form = specs.get('product_form', '')
                
                current_stock = mat.get('warehouse_quantity', 0)
                min_stock = mat.get('min_quantity', 0)
                is_critical = mat.get('is_critical', False)
                requires_cert = mat.get('requires_cert', True)
                
                unit_id = unit_map.get(base_unit, 4)
                specs_json = json.dumps(specs, ensure_ascii=False) if specs else 'NULL'
                
                sql.append(f"""INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `material_grade`, `standard_doc`, `product_form`, `specifications`, `current_stock`, `min_stock`, `is_critical`, `requires_cert`) VALUES (
{escape_sql_string(code)}, 
{escape_sql_string(name_full)}, 
{escape_sql_string(name_short)}, 
{category_id}, 
{unit_id}, 
{escape_sql_string(material_grade) if material_grade else 'NULL'}, 
{escape_sql_string(standard_doc) if standard_doc else 'NULL'}, 
{escape_sql_string(product_form) if product_form else 'NULL'}, 
{specs_json}, 
{current_stock}, 
{min_stock}, 
{1 if is_critical else 0}, 
{1 if requires_cert else 0}
);""")
    
    return '\n'.join(sql)

def main():
    # Загрузка данных
    production_data = load_json('/workspace/production.json')
    materials_data = load_json('/workspace/polesie/list_materials.json')
    
    # Генерация SQL
    output_sql = []
    output_sql.append("-- ============================================")
    output_sql.append("-- ДАННЫЕ ПРОДУКЦИИ И МАТЕРИАЛОВ")
    output_sql.append("-- ОАО \"Полесьеэлектромаш\"")
    output_sql.append("-- ============================================\n")
    
    # Категории продукции
    output_sql.append("-- Категории продукции")
    prod_cat_sql, prod_categories = generate_product_categories(production_data)
    output_sql.append(prod_cat_sql)
    output_sql.append("")
    
    # Продукция
    output_sql.append("-- Продукция")
    prod_sql = generate_products(production_data, prod_categories)
    output_sql.append(prod_sql)
    output_sql.append("")
    
    # Категории материалов
    output_sql.append("-- Категории материалов")
    mat_cat_sql, mat_categories = generate_material_categories(materials_data)
    output_sql.append(mat_cat_sql)
    output_sql.append("")
    
    # Материалы
    output_sql.append("-- Материалы")
    mat_sql = generate_materials(materials_data, mat_categories)
    output_sql.append(mat_sql)
    output_sql.append("")
    
    output_sql.append("COMMIT;")
    
    # Запись в файл
    with open('/workspace/polesie/sql/polesie_data.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(output_sql))
    
    print("SQL файл с данными успешно создан: polesie_data.sql")

if __name__ == '__main__':
    main()
