<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PhoneModel;
use App\Models\RepairCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogueSeeder extends Seeder
{
    public const AREAS = ['Main Road', 'Old Bus Stand', 'Nawayath Colony', 'Jali', 'Shirali', 'Tenginagundi', 'Moodbhatkal', 'Mundalli',
        'Venkatapur', 'Bandar Road', 'Sagar Road', 'Chowthani', 'Murdeshwar'];

    public const CATEGORIES = [
        ['Smartphones', 'smartphone'], ['Chargers', 'plug'], ['Earphones', 'earbuds'], ['Headphones', 'headphones'],
        ['Mobile covers', 'case'], ['Screen protectors', 'shield'], ['Power banks', 'battery'], ['Smartwatches', 'watch'],
        ['Cables', 'cable'], ['Other accessories', 'box'],
    ];

    public const REPAIR_CATEGORIES = [
        ['Display replacement', 'display'], ['Battery replacement', 'battery'], ['Charging problems', 'plug'],
        ['Speaker and microphone problems', 'speaker'], ['Software issues', 'code'], ['Motherboard repair', 'chip'],
        ['Water damage', 'water'], ['Camera repair', 'camera'], ['Network problems', 'signal'], ['Other repairs', 'tool'],
    ];

    public const MODELS = [
        'Samsung' => [['Galaxy S24', 2024], ['Galaxy S23 FE', 2023], ['Galaxy A55 5G', 2024], ['Galaxy A35 5G', 2024], ['Galaxy A15 5G', 2023], ['Galaxy M34 5G', 2023], ['Galaxy F15 5G', 2024]],
        'Apple' => [['iPhone 16', 2024], ['iPhone 15', 2023], ['iPhone 14', 2022], ['iPhone 13', 2021], ['iPhone 12', 2020], ['iPhone 11', 2019]],
        'Xiaomi' => [['Redmi Note 13 Pro', 2024], ['Redmi Note 13', 2024], ['Redmi 13C', 2023], ['Redmi Note 12', 2023], ['Xiaomi 14', 2024]],
        'Vivo' => [['V30', 2024], ['Y28', 2024], ['T3 5G', 2024], ['Y17s', 2023]],
        'Oppo' => [['Reno 11', 2024], ['A79 5G', 2023], ['A18', 2023], ['F25 Pro', 2024]],
        'Realme' => [['12 Pro', 2024], ['Narzo 70', 2024], ['C55', 2023], ['11x 5G', 2023]],
        'OnePlus' => [['12', 2024], ['Nord CE 4', 2024], ['Nord CE 3 Lite', 2023], ['11R', 2023]],
        'Motorola' => [['Edge 50 Fusion', 2024], ['G84 5G', 2023], ['G34 5G', 2024]],
        'iQOO' => [['Z9', 2024], ['Neo 9 Pro', 2024]],
        'Nothing' => [['Phone (2a)', 2024], ['Phone (2)', 2023]],
        'Google' => [['Pixel 8a', 2024], ['Pixel 7a', 2023]],
        'boAt' => [], 'Ambrane' => [], 'Spigen' => [], 'Portronics' => [], 'Noise' => [], 'JBL' => [], 'Mi' => [],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $a) {
            Area::updateOrCreate(['slug' => Str::slug($a)], ['name' => $a, 'city' => 'Bhatkal', 'pincode' => $a === 'Murdeshwar' ? '581350' : '581320', 'is_active' => true]);
        }
        foreach (self::CATEGORIES as $i => [$name, $icon]) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => $icon, 'sort_order' => $i + 1, 'is_active' => true]);
        }
        foreach (self::REPAIR_CATEGORIES as $i => [$name, $icon]) {
            RepairCategory::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => $icon, 'sort_order' => $i + 1, 'is_active' => true]);
        }
        foreach (self::MODELS as $brandName => $models) {
            $brand = Brand::updateOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName, 'is_active' => true]);
            foreach ($models as [$model, $year]) {
                PhoneModel::updateOrCreate(['brand_id' => $brand->id, 'name' => $model], ['slug' => Str::slug($brandName.' '.$model), 'release_year' => $year, 'is_active' => true]);
            }
        }
    }
}
