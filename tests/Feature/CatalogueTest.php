<?php

namespace Tests\Feature;

use App\Models\PhoneModel;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    public function test_only_approved_shops_and_active_listings_are_visible(): void
    {
        $shop = $this->shop();
        $visible = $this->product($shop, ['name' => 'Visible Charger']);
        $this->product($shop, ['name' => 'Inactive Charger', 'status' => 'inactive']);
        $this->product($shop, ['name' => 'Suspended Charger', 'is_suspended' => true]);
        $pending = $this->shop(['status' => 'pending']);
        $this->product($pending, ['name' => 'Pending Shop Charger']);

        $names = collect($this->getJson('/api/v1/products?q=charger')->assertOk()->json('data'))->pluck('name');
        $this->assertEquals(['Visible Charger'], $names->all());

        $this->getJson("/api/v1/products/{$visible->slug}")->assertOk()->assertJsonPath('product.name', 'Visible Charger')
            ->assertJsonPath('product.stock_freshness.level', 'fresh');
        $this->getJson('/api/v1/shops/'.$pending->slug)->assertNotFound();
    }

    public function test_filters_by_price_model_area_and_availability(): void
    {
        $shop = $this->shop();
        $a35 = PhoneModel::where('name', 'Galaxy A35 5G')->first();
        $cheap = $this->product($shop, ['name' => 'Cheap Cover', 'price' => 300, 'discount_price' => null]);
        $cheap->compatibleModels()->attach($a35);
        $this->product($shop, ['name' => 'Pricey Cover', 'price' => 3000, 'discount_price' => null, 'availability' => 'out_of_stock', 'stock_quantity' => 0]);

        $this->assertCount(1, $this->getJson('/api/v1/products?max_price=500')->json('data'));
        $this->assertSame('Cheap Cover', $this->getJson('/api/v1/products?phone_model_id='.$a35->id)->json('data.0.name'));
        $this->assertCount(1, $this->getJson('/api/v1/products?availability=available')->json('data'));
        $this->assertSame('Pricey Cover', $this->getJson('/api/v1/products?sort=price_desc')->json('data.0.name'));
        $this->assertCount(2, $this->getJson('/api/v1/products?area_id='.$shop->area_id)->json('data'));
        // Searching by model name finds compatible accessories.
        $this->assertSame('Cheap Cover', $this->getJson('/api/v1/products?q=a35')->json('data.0.name'));
    }

    public function test_no_result_searches_are_logged_with_suggestions(): void
    {
        $res = $this->getJson('/api/v1/search?q=nokia 3310 battery')->assertOk();
        $this->assertSame(0, $res->json('total'));
        $this->assertNotEmpty($res->json('suggestions.tips'));
        $this->assertDatabaseHas('search_logs', ['query' => 'nokia 3310 battery', 'results_count' => 0]);
    }

    public function test_product_page_has_whatsapp_click_to_chat_link(): void
    {
        $product = $this->product($this->shop(), ['name' => 'boAt Airdopes 141']);
        $link = $this->getJson('/api/v1/products/'.$product->slug)->json('whatsapp_link');

        $this->assertStringStartsWith('https://wa.me/919845011111?text=', $link);
        $this->assertStringContainsString(rawurlencode('Product: boAt Airdopes 141'), $link);
    }

    public function test_home_and_meta_endpoints(): void
    {
        $this->product($this->shop(['is_featured' => true]));
        $this->technician();
        $this->getJson('/api/v1/home')->assertOk()->assertJsonCount(1, 'featured_shops')->assertJsonCount(1, 'top_technicians');
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonCount(10, 'categories')->assertJsonPath('cities.0', 'Bhatkal');
    }
}
