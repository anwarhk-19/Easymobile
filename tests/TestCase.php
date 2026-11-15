<?php

namespace Tests;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PhoneModel;
use App\Models\Product;
use App\Models\RepairCategory;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, CatalogueSeeder::class]);
        $this->withHeader('Accept', 'application/json');
    }

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    protected function admin(): User
    {
        return User::factory()->staff('admin')->create();
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user, [$user->role]);

        return $this;
    }

    protected function shop(array $attrs = []): Shop
    {
        $merchant = User::factory()->role('merchant')->create();

        return Shop::create($attrs + [
            'merchant_id' => $merchant->id, 'name' => $name = 'Test Shop '.Str::random(4), 'slug' => Str::slug($name),
            'owner_name' => $merchant->name, 'phone' => '9845011111', 'whatsapp' => '9845011111', 'address' => 'Main Road, Bhatkal',
            'area_id' => Area::first()->id, 'opens_at' => '09:00', 'closes_at' => '21:00', 'status' => 'approved',
            'pickup_available' => true, 'delivery_available' => true, 'delivery_fee' => 30,
        ]);
    }

    protected function product(Shop $shop, array $attrs = []): Product
    {
        return Product::create($attrs + [
            'shop_id' => $shop->id, 'category_id' => Category::where('slug', 'chargers')->value('id'), 'brand_id' => Brand::where('name', 'Samsung')->value('id'),
            'name' => $name = 'Samsung 25W Charger '.Str::random(3), 'slug' => Str::slug($name).'-'.Str::random(4), 'sku' => 'SKU-'.Str::random(5),
            'price' => 1500, 'discount_price' => 1200, 'stock_quantity' => 5, 'availability' => 'in_stock', 'status' => 'active', 'stock_confirmed_at' => now(),
        ]);
    }

    protected function technician(array $attrs = []): TechnicianProfile
    {
        $user = User::factory()->role('technician')->create();
        $t = TechnicianProfile::create($attrs + [
            'user_id' => $user->id, 'name' => $name = 'Tech '.Str::random(4), 'slug' => Str::slug($name), 'phone' => '9845022222',
            'address' => 'Bhatkal', 'area_id' => Area::first()->id, 'warranty_terms' => '90 days on parts', 'status' => 'approved',
            'pickup_available' => true,
        ]);
        $t->repairCategories()->sync(RepairCategory::pluck('id'));
        $t->brands()->sync(Brand::pluck('id'));

        return $t;
    }

    protected function repairPayload(TechnicianProfile $t, array $overrides = []): array
    {
        $model = PhoneModel::where('name', 'Galaxy A35 5G')->first();

        return $overrides + [
            'technician_id' => $t->id, 'brand_id' => $model->brand_id, 'phone_model_id' => $model->id,
            'repair_category_id' => RepairCategory::where('slug', 'display-replacement')->value('id'),
            'issue_description' => 'The screen is cracked and touch does not work.', 'service_mode' => 'walk_in',
        ];
    }
}
