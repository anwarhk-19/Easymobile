<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    private function completedOrder(?Shop $shop = null, ?User $customer = null, float $price = 1000): Order
    {
        $shop ??= $this->shop();
        $customer ??= $this->customer();
        $svc = app(OrderService::class);
        $order = $svc->createRequest($customer, $shop, [['product_id' => $this->product($shop)->id, 'quantity' => 1]], ['fulfilment' => 'pickup']);
        $svc->quote($order, $shop->merchant, ['items' => [['id' => $order->items()->first()->id, 'unit_price' => $price]]]);
        $svc->accept($order, $customer);
        $svc->confirm($order->load('items.product'), $shop->merchant);
        $svc->markReady($order, $shop->merchant);
        $svc->markHandedOver($order, $shop->merchant);

        return $svc->confirmReceipt($order, $customer);
    }

    public function test_reviews_only_after_completion_no_duplicates_and_moderated(): void
    {
        $shop = $this->shop();
        $customer = $this->customer();
        $svc = app(OrderService::class);
        $open = $svc->createRequest($customer, $shop, [['product_id' => $this->product($shop)->id, 'quantity' => 1]], ['fulfilment' => 'pickup']);
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$open->id}/reviews", ['shop_rating' => 5])->assertStatus(422);

        $order = $this->completedOrder($shop, $customer);
        $productId = $order->items->first()->product_id;
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order->id}/reviews", ['shop_rating' => 4, 'shop_comment' => 'Good', 'products' => [['product_id' => $productId, 'rating' => 5]]])->assertCreated();
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order->id}/reviews", ['shop_rating' => 1])->assertStatus(422);

        // Pending reviews don't count until approved.
        $this->assertSame(0, $shop->fresh()->rating_count);
        $review = \App\Models\Review::where('reviewable_type', 'shop')->first();
        $this->actingAsUser($this->admin())->postJson("/api/v1/admin/reviews/{$review->id}/moderate", ['status' => 'approved'])->assertOk();
        $this->assertSame(1, $shop->fresh()->rating_count);
        $this->assertSame('4.00', $shop->fresh()->rating_avg);
    }

    public function test_tickets_are_acknowledged_escalated_and_sla_tracked(): void
    {
        Storage::fake('local');
        $customer = $this->customer();
        $admin = $this->admin();

        $t = $this->actingAsUser($customer)->post('/api/v1/tickets', ['category' => 'wrong_product', 'subject' => 'Wrong cover', 'description' => 'I received the wrong colour cover.',
            'attachments' => [UploadedFile::fake()->image('proof.jpg')]])->assertCreated()->json('ticket');
        $this->assertStringStartsWith('TKT-', $t['reference']);
        $this->assertNotNull($t['acknowledge_due_at']);
        $this->assertFalse($t['is_escalated']);

        // Fraud complaints are escalated to the administrator immediately.
        $fraud = $this->actingAsUser($customer)->postJson('/api/v1/tickets', ['category' => 'fraud', 'subject' => 'Fake product', 'description' => 'The seller sold me a fake charger.'])->json('ticket');
        $this->assertTrue($fraud['is_escalated']);
        $this->assertSame('urgent', $fraud['priority']);
        $this->assertSame(1, $admin->notifications()->count());

        // Unacknowledged tickets breach SLA after one business day.
        $this->travel(3)->days();
        $this->artisan('bmh:escalate-tickets')->assertSuccessful();
        $this->assertTrue(SupportTicket::find($t['id'])->is_escalated);

        // Staff reply acknowledges; internal notes are hidden from the customer.
        $this->actingAsUser($admin)->postJson("/api/v1/admin/tickets/{$t['id']}/messages", ['message' => 'Checking with the shop', 'is_internal' => true])->assertCreated();
        $this->actingAsUser($admin)->postJson("/api/v1/admin/tickets/{$t['id']}/messages", ['message' => 'We will arrange a replacement.'])->assertCreated();
        $this->assertNotNull(SupportTicket::find($t['id'])->acknowledged_at);
        $msgs = $this->actingAsUser($customer)->getJson("/api/v1/tickets/{$t['id']}")->json('ticket.messages');
        $this->assertCount(1, $msgs);

        $this->actingAsUser($admin)->putJson("/api/v1/admin/tickets/{$t['id']}", ['status' => 'resolved'])->assertStatus(422);
        $this->actingAsUser($admin)->putJson("/api/v1/admin/tickets/{$t['id']}", ['status' => 'resolved', 'resolution_notes' => 'Replaced'])->assertOk()->assertJsonPath('ticket.status', 'resolved');

        // Customers cannot read other people's tickets.
        $this->actingAsUser($this->customer())->getJson("/api/v1/tickets/{$t['id']}")->assertStatus(403);
    }

    public function test_settlement_generation_and_refunds(): void
    {
        $shop = $this->shop();
        \App\Models\Commission::create(['scope' => 'global', 'type' => 'percentage', 'value' => 10]);
        $o1 = $this->completedOrder($shop, null, 1000);
        $o2 = $this->completedOrder($shop, null, 500);
        $admin = $this->admin();

        // Refund ₹100 on o2.
        $refund = $this->actingAsUser($admin)->postJson('/api/v1/admin/refunds', ['order_id' => $o2->id, 'amount' => 100, 'reason' => 'Damaged box'])->assertCreated()->json('refund');
        $this->actingAsUser($admin)->postJson('/api/v1/admin/refunds', ['order_id' => $o2->id, 'amount' => 450, 'reason' => 'Too much'])->assertStatus(422);
        $this->actingAsUser($admin)->postJson("/api/v1/admin/refunds/{$refund['id']}/process", ['status' => 'processed', 'method' => 'upi'])->assertOk();
        $this->assertSame('350.00', $o2->fresh()->merchant_payable); // 500 - 50 commission - 100 refund

        $this->actingAsUser($admin)->getJson('/api/v1/admin/settlements/outstanding')->assertOk()->assertJsonPath('0.orders_count', 2);
        $s = $this->actingAsUser($admin)->postJson('/api/v1/admin/settlements', ['shop_id' => $shop->id, 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()])
            ->assertCreated()->json('settlement');
        $this->assertEquals(1500, $s['gross_amount']);
        $this->assertEquals(150, $s['platform_fee']);
        $this->assertEquals(100, $s['refunds']);
        $this->assertEquals(1250, $s['net_payable']);

        // Orders cannot be settled twice.
        $this->actingAsUser($admin)->postJson('/api/v1/admin/settlements', ['shop_id' => $shop->id, 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()])->assertStatus(422);

        $this->actingAsUser($admin)->putJson("/api/v1/admin/settlements/{$s['id']}", ['status' => 'paid'])->assertStatus(422); // reference required
        $this->actingAsUser($admin)->putJson("/api/v1/admin/settlements/{$s['id']}", ['status' => 'paid', 'payment_reference' => 'NEFT123'])->assertOk()->assertJsonPath('settlement.status', 'paid');
        $this->actingAsUser($shop->merchant)->getJson('/api/v1/merchant/settlements')->assertOk()->assertJsonPath('data.0.status', 'paid');
    }

    public function test_admin_can_suspend_users_shops_and_listings_with_audit_log(): void
    {
        $admin = $this->admin();
        $shop = $this->shop();
        $product = $this->product($shop);
        $customer = $this->customer();

        $this->actingAsUser($admin)->postJson("/api/v1/admin/products/{$product->id}/moderate", ['action' => 'suspend', 'reason' => 'Counterfeit'])->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();

        $this->actingAsUser($admin)->postJson("/api/v1/admin/shops/{$shop->id}/status", ['action' => 'suspend', 'reason' => 'Repeated complaints'])->assertOk();
        $this->getJson('/api/v1/shops/'.$shop->slug)->assertNotFound();
        $this->actingAsUser($admin)->postJson("/api/v1/admin/shops/{$shop->id}/status", ['action' => 'reinstate'])->assertOk();

        $this->actingAsUser($admin)->postJson("/api/v1/admin/users/{$customer->id}/suspend", ['reason' => 'Abusive'])->assertOk();
        $this->assertSame('suspended', $customer->fresh()->status);

        $actions = \App\Models\AdminActivityLog::pluck('action')->all();
        $this->assertEqualsCanonicalizing(['product.suspend', 'shop.suspend', 'shop.reinstate', 'user.suspended'], $actions);
        $this->actingAsUser($admin)->getJson('/api/v1/admin/activity-logs')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_admin_dashboard_and_reports_use_real_data(): void
    {
        $this->completedOrder();
        $this->getJson('/api/v1/search?q=zzzz-not-found');
        $admin = $this->admin();

        $this->actingAsUser($admin)->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('stats.completed_orders', 1)->assertJsonPath('stats.active_shops', 1)->assertJsonPath('stats.marketplace_revenue', 1000);

        $r = $this->actingAsUser($admin)->getJson('/api/v1/admin/reports?period=day')->assertOk();
        $this->assertSame(1, $r->json('orders.completed'));
        $this->assertSame('zzzz-not-found', $r->json('no_result_searches.0.query'));
        $this->actingAsUser($admin)->getJson('/api/v1/admin/reports?period=month')->assertOk();
        $this->actingAsUser($admin)->getJson('/api/v1/admin/reports?period=week')->assertOk();
    }

    public function test_taxonomy_commission_template_and_settings_management(): void
    {
        $admin = $this->admin();
        $brand = $this->actingAsUser($admin)->postJson('/api/v1/admin/taxonomy/brands', ['name' => 'Infinix'])->assertCreated()->json('item');
        $this->actingAsUser($admin)->postJson('/api/v1/admin/taxonomy/phone-models', ['name' => 'Hot 40i', 'brand_id' => $brand['id'], 'release_year' => 2024])->assertCreated();
        $this->actingAsUser($admin)->postJson('/api/v1/admin/taxonomy/phone-models', ['name' => 'Hot 40i', 'brand_id' => $brand['id']])->assertStatus(422);
        $this->getJson('/api/v1/phone-models?brand_id='.$brand['id'])->assertJsonCount(1);
        $this->actingAsUser($admin)->getJson('/api/v1/admin/taxonomy/unknown')->assertNotFound();

        $this->actingAsUser($admin)->postJson('/api/v1/admin/commissions', ['scope' => 'global', 'type' => 'percentage', 'value' => 80])->assertStatus(422);
        $this->actingAsUser($admin)->postJson('/api/v1/admin/commissions', ['scope' => 'global', 'type' => 'percentage', 'value' => 3])->assertCreated();

        $this->actingAsUser($admin)->postJson('/api/v1/admin/notification-templates', ['key' => 'product_enquiry', 'channel' => 'whatsapp', 'name' => 'Enquiry', 'body' => 'Hi! Is {product_name} available?'])->assertCreated();
        $product = $this->product($this->shop(), ['name' => 'Redmi Cover']);
        $this->assertStringContainsString(rawurlencode('Hi! Is Redmi Cover available?'), $this->getJson('/api/v1/products/'.$product->slug)->json('whatsapp_link'));
        $this->actingAsUser($admin)->postJson('/api/v1/admin/notification-templates/preview', ['body' => 'Order {order_reference}'])->assertJsonPath('preview', 'Order ORD-2611-ABCDEF');

        $this->actingAsUser($admin)->putJson('/api/v1/admin/settings', ['reviews_auto_approve' => true, 'stock_stale_days' => 5])->assertOk()->assertJsonPath('settings.stock_stale_days', 5);
    }
}
