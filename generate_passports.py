#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Генератор паспортов продуктов для ОАО «Полесьеэлектромаш»

Создает паспорт для каждого продукта с информацией о необходимых материалах,
их количестве и спецификациях.
"""

import json
from typing import Dict, List, Any


def load_json(filepath: str) -> Dict:
    """Загрузка JSON файла"""
    with open(filepath, 'r', encoding='utf-8') as f:
        return json.load(f)


def save_json(filepath: str, data: Dict):
    """Сохранение JSON файла"""
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def extract_all_products(production_data: Dict) -> List[Dict]:
    """Извлечение всех продуктов из структуры категорий"""
    products = []
    
    def traverse_categories(categories: List[Dict], parent_info: Dict = None):
        for cat in categories:
            current_info = {
                'category_id': cat.get('id'),
                'category_code': cat.get('code'),
                'category_name': cat.get('name_ru'),
                'parent_id': cat.get('parent_id'),
                'level': cat.get('level')
            }
            
            if parent_info:
                current_info.update(parent_info)
            
            # Если есть продукты в категории
            if 'products' in cat:
                for prod in cat['products']:
                    prod_copy = prod.copy()
                    prod_copy['category_info'] = current_info
                    products.append(prod_copy)
            
            # Рекурсивный обход подкатегорий
            if 'subcategories' in cat:
                traverse_categories(cat['subcategories'], current_info)
    
    traverse_categories(production_data.get('categories', []))
    return products


def get_material_by_code(materials_data: Dict, code_pattern: str) -> Dict:
    """Поиск материала по коду или паттерну"""
    for category in materials_data.get('categories', []):
        for subcat in category.get('subcategories', []):
            for material in subcat.get('materials', []):
                if material.get('code_internal') == code_pattern:
                    return material
    return None


def calculate_motor_materials(product: Dict, shaft_height: int, power_kw: float) -> Dict:
    """
    Расчет материалов для электродвигателя на основе мощности и высоты оси вращения
    
    Возвращает словарь с материалами и их количеством
    """
    materials = {}
    
    # Базовые коэффициенты расхода материалов (примерные, нужно уточнять у технологов)
    # Для станины (чугун)
    cast_iron_coeff = 0.8 + (shaft_height / 100) * 0.3
    # Для меди в обмотках
    copper_coeff = 0.05 + (power_kw / 10) * 0.08
    # Для стали электротехнической
    steel_electrical_coeff = 0.6 + (power_kw / 5) * 0.15
    # Для подшипников
    bearings_count = 2 if shaft_height <= 160 else 4
    # Для крепежа
    fasteners_weight = 0.5 + (shaft_height / 100) * 0.2
    # Для лака электроизоляционного
    varnish_liters = 0.3 + (power_kw / 5) * 0.4
    # Для изоляционных материалов
    insulation_kg = 0.2 + (power_kw / 10) * 0.3
    
    materials['cast_iron_housing'] = {
        'material_code': 'CAST-SC-15',
        'name': 'Чугун серый СЧ15 для корпуса',
        'quantity': round(cast_iron_coeff * shaft_height / 50, 2),
        'unit': 'кг',
        'category': 'Металлы'
    }
    
    materials['copper_wire'] = {
        'material_code': 'WIRE-CU-PETV-2.0',
        'name': 'Провод медный обмоточный ПЭТВ',
        'quantity': round(copper_coeff * power_kw * 3, 2),
        'unit': 'кг',
        'category': 'Металлы'
    }
    
    materials['steel_electrical'] = {
        'material_code': 'STEEL-ELECTR-2212',
        'name': 'Сталь электротехническая 2212',
        'quantity': round(steel_electrical_coeff * power_kw * 2, 2),
        'unit': 'кг',
        'category': 'Металлы'
    }
    
    materials['bearings'] = {
        'material_code': 'BRG-6200-series',
        'name': f'Подшипники качения 6200 серии',
        'quantity': bearings_count,
        'unit': 'шт',
        'category': 'Комплектующие'
    }
    
    materials['fasteners'] = {
        'material_code': 'FASTENER-MIX',
        'name': 'Крепеж (болты, гайки, шайбы)',
        'quantity': round(fasteners_weight, 2),
        'unit': 'кг',
        'category': 'Крепеж'
    }
    
    materials['varnish'] = {
        'material_code': 'VAR-PES-55',
        'name': 'Лак электроизоляционный ПЭС-55',
        'quantity': round(varnish_liters, 2),
        'unit': 'л',
        'category': 'Лаки и краски'
    }
    
    materials['insulation'] = {
        'material_code': 'INS-COMBO',
        'name': 'Изоляционные материалы (комбинированные)',
        'quantity': round(insulation_kg, 2),
        'unit': 'кг',
        'category': 'Изоляция'
    }
    
    materials['steel_shaft'] = {
        'material_code': 'ST-BAR-45-STD',
        'name': 'Сталь конструкционная 45 для вала',
        'quantity': round((shaft_height / 100) * 1.5, 2),
        'unit': 'кг',
        'category': 'Металлы'
    }
    
    return materials


def calculate_pump_materials(product: Dict) -> Dict:
    """Расчет материалов для насосов"""
    materials = {}
    
    pump_type = product.get('sku', '').upper()
    
    if 'K' in pump_type:
        # Насосы типа К
        materials['cast_iron_body'] = {
            'material_code': 'CAST-SC-20',
            'name': 'Чугун серый СЧ20 для корпуса насоса',
            'quantity': 15.0,
            'unit': 'кг',
            'category': 'Металлы'
        }
        materials['impeller_bronze'] = {
            'material_code': 'BRONZE-BRZH',
            'name': 'Бронза для рабочего колеса',
            'quantity': 3.5,
            'unit': 'кг',
            'category': 'Металлы'
        }
        materials['mechanical_seal'] = {
            'material_code': 'SEAL-MECH-K',
            'name': 'Уплотнение торцевое',
            'quantity': 1,
            'unit': 'шт',
            'category': 'Комплектующие'
        }
    elif 'TSNL' in pump_type:
        # Насосы типа ЦНСЛ
        materials['cast_iron_body'] = {
            'material_code': 'CAST-SC-20',
            'name': 'Чугун серый СЧ20 для корпуса',
            'quantity': 45.0,
            'unit': 'кг',
            'category': 'Металлы'
        }
        materials['stages'] = {
            'material_code': 'STAGE-TSNL',
            'name': 'Ступени насоса',
            'quantity': 5,
            'unit': 'шт',
            'category': 'Комплектующие'
        }
    else:
        # Бытовые насосы
        materials['housing'] = {
            'material_code': 'MAT-HOUSE-PUMP',
            'name': 'Корпус насоса композитный',
            'quantity': 2.0,
            'unit': 'кг',
            'category': 'Материалы'
        }
    
    return materials


def calculate_casting_materials(product: Dict) -> Dict:
    """Расчет материалов для литейной продукции"""
    materials = {}
    
    sku = product.get('sku', '')
    
    if 'GRATE' in sku or 'CAST' in sku:
        materials['pig_iron'] = {
            'material_code': 'IRON-PIG',
            'name': 'Чугун передельный',
            'quantity': 10.0,
            'unit': 'кг',
            'category': 'Металлы'
        }
        materials['ferroalloy'] = {
            'material_code': 'FERRO-SI-MN',
            'name': 'Ферросплавы (Si, Mn)',
            'quantity': 0.5,
            'unit': 'кг',
            'category': 'Добавки'
        }
        materials['sand_mold'] = {
            'material_code': 'SAND-MOLD',
            'name': 'Песок формовочный',
            'quantity': 5.0,
            'unit': 'кг',
            'category': 'Формовочные материалы'
        }
    
    return materials


def generate_product_passport(product: Dict, materials_db: Dict) -> Dict:
    """
    Генерация паспорта продукта
    
    Структура паспорта:
    - basic_info: основная информация о продукте
    - materials: необходимые материалы с количеством
    - total_weight: общий вес
    - production_notes: примечания к производству
    - quality_requirements: требования к качеству
    """
    sku = product.get('sku', 'UNKNOWN')
    specs = product.get('specs', {})
    
    # Получаем параметры из спецификаций
    shaft_height = specs.get('shaft_height_mm', 80)
    power_min = specs.get('power_kw_min', specs.get('power_kw', 1.0))
    power_max = specs.get('power_kw_max', power_min)
    power_avg = (power_min + power_max) / 2
    
    # Определяем тип продукта и рассчитываем материалы
    category_info = product.get('category_info', {})
    category_code = category_info.get('category_code', '')
    
    materials = {}
    production_notes = []
    quality_requirements = []
    
    # Классификация по типу продукта
    if 'MOTOR' in category_code or 'AIR' in sku or '2AIR' in sku:
        # Электродвигатели
        materials = calculate_motor_materials(product, shaft_height, power_avg)
        production_notes = [
            'Контроль балансировки ротора',
            'Пропитка обмоток лаком с сушкой',
            'Проверка сопротивления изоляции'
        ]
        quality_requirements = [
            'ГОСТ Р 51689-2000',
            'Класс нагревостойкости изоляции F (155°C)',
            'Степень защиты IP54/IP55'
        ]
        
    elif 'PUMP' in category_code or 'PUMP' in sku:
        # Насосы
        materials = calculate_pump_materials(product)
        production_notes = [
            'Гидравлические испытания',
            'Проверка вибрации',
            'Контроль герметичности'
        ]
        quality_requirements = [
            'ГОСТ 10168-2014',
            'Рабочая температура до 85°C'
        ]
        
    elif 'EKCH' in sku or 'EOST' in sku:
        # Электрокотлы
        materials = {
            'heating_element': {
                'material_code': 'TEN-INDUSTRIAL',
                'name': 'ТЭН промышленный',
                'quantity': 3,
                'unit': 'шт',
                'category': 'Комплектующие'
            },
            'control_panel': {
                'material_code': 'PANEL-CONTROL',
                'name': 'Шкаф управления',
                'quantity': 1,
                'unit': 'шт',
                'category': 'Комплектующие'
            },
            'steel_sheet': {
                'material_code': 'ST-SHEET-3',
                'name': 'Лист стальной 3мм',
                'quantity': 8.0,
                'unit': 'кг',
                'category': 'Металлы'
            }
        }
        production_notes = [
            'Электрические испытания ТЭНов',
            'Проверка автоматики безопасности'
        ]
        quality_requirements = [
            'ГОСТ Р 50033-2000',
            'Напряжение 380В'
        ]
        
    elif 'GRATE' in sku or 'CAST' in sku:
        # Литейная продукция
        materials = calculate_casting_materials(product)
        production_notes = [
            'Контроль химического состава',
            'Дефектоскопия отливок',
            'Термообработка'
        ]
        quality_requirements = [
            'ГОСТ 1412-2016',
            'Класс точности CT8-CT10'
        ]
        
    else:
        # Продукция общего назначения
        materials = {
            'base_material': {
                'material_code': 'MAT-BASE-GEN',
                'name': 'Материал базовый',
                'quantity': 5.0,
                'unit': 'кг',
                'category': 'Металлы'
            }
        }
        production_notes = ['Стандартный контроль качества']
        quality_requirements = ['ГОСТ/ТУ согласно документации']
    
    # Расчет общего веса
    total_weight = sum(
        m.get('quantity', 0) 
        for m in materials.values() 
        if m.get('unit') == 'кг'
    )
    
    # Формирование паспорта
    passport = {
        'sku': sku,
        'basic_info': {
            'name_full': product.get('name_full', ''),
            'name_short': product.get('name_short', ''),
            'code_gost': product.get('code_gost', ''),
            'category': category_info.get('category_name', ''),
            'category_code': category_code
        },
        'specifications': specs,
        'materials': materials,
        'total_weight_kg': round(total_weight, 2),
        'production_notes': production_notes,
        'quality_requirements': quality_requirements,
        'is_serial_tracked': product.get('is_serial_tracked', False),
        'warranty_months': product.get('warranty_months', 24)
    }
    
    return passport


def main():
    """Основная функция"""
    print("Загрузка данных...")
    
    # Загружаем данные
    production_data = load_json('/workspace/production.json')
    materials_data = load_json('/workspace/list_materials.json')
    
    print(f"✓ Загружено производство: {len(production_data.get('categories', []))} категорий")
    print(f"✓ Загружено материалов: {len(materials_data.get('categories', []))} категорий")
    
    # Извлекаем все продукты
    all_products = extract_all_products(production_data)
    print(f"✓ Найдено продуктов: {len(all_products)}")
    
    # Генерируем паспорта для всех продуктов
    passports = []
    for product in all_products:
        try:
            passport = generate_product_passport(product, materials_data)
            passports.append(passport)
        except Exception as e:
            print(f"⚠ Ошибка при генерации паспорта для {product.get('sku', 'UNKNOWN')}: {e}")
    
    # Создаем итоговую структуру
    passports_data = {
        'version': '1.0',
        'generated_at': '2025-01-XX',
        'company': production_data.get('company', {}),
        'total_products': len(passports),
        'passports': passports
    }
    
    # Сохраняем результат
    output_file = '/workspace/passports.json'
    save_json(output_file, passports_data)
    print(f"\n✓ Паспорта сохранены в {output_file}")
    print(f"✓ Всего паспортов: {len(passports)}")
    
    # Вывод статистики
    print("\n📊 Статистика:")
    categories_count = {}
    for p in passports:
        cat_code = p['basic_info'].get('category_code', 'OTHER')
        categories_count[cat_code] = categories_count.get(cat_code, 0) + 1
    
    for cat, count in sorted(categories_count.items()):
        print(f"  {cat}: {count} продуктов")


if __name__ == '__main__':
    main()
