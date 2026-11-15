<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    public function test_shop_registration_requires_documents_and_admin_approval(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = User::factory()->role('merchant')->create();
        $payload = ['name' => 'Al Noor', 'owner_name' => 'Abdul', 'phone' => '9845000999', 'address' => 'Main Road', 'area_id' => Area::first()->id,
            'opens_at' => '09:00', 'closes_at' => '21:00', 'accept_agreement' => '1', 'maps_url' => 'https://maps.app.goo.gl/abc123'];

        $this->actingAsUser($merchant)->post('/api/v1/merchant/shop', $payload)->assertStatus(422)->assertJsonValidationErrors('documents');

        $res = $this->actingAsUser($merchant)->post('/api/v1/merchant/shop', $payload + [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'documents' => [['type' => 'trade_licence', 'file' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf')]],
        ])->assertCreated()->assertJsonPath('shop.status', 'pending');
        $shop = Shop::find($res->json('shop.id'));
        $doc = $shop->documents->first();
        Storage::disk('local')->assertExists($doc->path); // private disk
        $this->assertDatabaseHas('merchant_agreements', ['shop_id' => $shop->id]);

        // Not public until approved.
        $this->getJson('/api/v1/shops/'.$shop->slug)->assertNotFound();
        // Another merchant cannot download the verification document.
        $this->actingAsUser(User::factory()->role('merchant')->create())->get('/api/v1/shop-documents/'.$doc->id)->assertStatus(403);

        $admin = $this->admin();
        $this->actingAsUser($admin)->postJson("/api/v1/admin/shops/{$shop->id}/status", ['action' => 'reject'])->assertStatus(422);
        $this->actingAsUser($admin)->postJson("/api/v1/admin/shops/{$shop->id}/status", ['action' => 'approve'])->assertOk();
        $this->getJson('/api/v1/shops/'.$shop->slug)->assertOk();
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'shop.approve', 'subject_id' => $shop->id, 'user_id' => $admin->id]);
        $this->assertSame(1, $merchant->notifications()->count());

        $this->actingAsUser($admin)->get('/api/v1/shop-documents/'.$doc->id)->assertOk();
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'document.viewed']);
    }

    public function test_product_crud_stock_logs_and_freshness(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $m = $shop->merchant;
        $res = $this->actingAsUser($m)->post('/api/v1/merchant/products', [
            'name' => 'Type-C Cable', 'category_id' => Category::where('slug', 'cables')->value('id'), 'sku' => 'CAB-1',
            'price' => 499, 'discount_price' => 299, 'stock_quantity' => 10, 'availability' => 'in_stock',
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertCreated();
        $id = $res->json('product.id');
        $this->assertCount(2, $res->json('product.images'));

        // Duplicate SKU in the same shop is rejected; discount must be lower than price.
        $this->actingAsUser($m)->postJson('/api/v1/merchant/products', ['name' => 'X', 'category_id' => 1, 'sku' => 'CAB-1', 'price' => 100, 'discount_price' => 200, 'stock_quantity' => 1, 'availability' => 'in_stock'])
            ->assertStatus(422)->assertJsonValidationErrors(['sku', 'discount_price']);

        $this->travel(10)->days();
        $this->assertSame('stale', Product::find($id)->stock_freshness['level']);
        $this->actingAsUser($m)->postJson("/api/v1/merchant/products/{$id}/stock", ['quantity' => 0, 'reason' => 'sale_offline'])
            ->assertOk()->assertJsonPath('product.availability', 'out_of_stock')->assertJsonPath('product.stock_freshness.level', 'fresh');
        $this->assertCount(2, $this->actingAsUser($m)->getJson("/api/v1/merchant/products/{$id}/stock-logs")->json('data'));

        // Another merchant cannot edit it.
        $this->actingAsUser($this->shop()->merchant)->postJson("/api/v1/merchant/products/{$id}/toggle")->assertStatus(403);

        $this->actingAsUser($m)->postJson("/api/v1/merchant/products/{$id}/toggle")->assertJsonPath('product.status', 'inactive');
        $this->actingAsUser($m)->deleteJson("/api/v1/merchant/products/{$id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    public function test_dashboard_metrics_reflect_real_data(): void
    {
        $shop = $this->shop();
        $this->product($shop);
        $this->product($shop, ['availability' => 'out_of_stock', 'stock_quantity' => 0]);
        $this->actingAsUser($this->customer())->postJson('/api/v1/enquiries', ['shop_id' => $shop->id, 'message' => 'Do you have iPhone 15 covers?'])->assertCreated();

        $this->actingAsUser($shop->merchant)->getJson('/api/v1/merchant/dashboard')->assertOk()
            ->assertJsonPath('stats.total_products', 2)->assertJsonPath('stats.out_of_stock_products', 1)->assertJsonPath('stats.new_enquiries', 1);
    }
}
