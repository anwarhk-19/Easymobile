<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Commission;
use App\Models\Order;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    public function test_full_order_workflow_from_enquiry_to_completion(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, ['stock_quantity' => 5]);
        $customer = $this->customer();
        $merchant = $shop->merchant;
        Commission::create(['scope' => 'global', 'type' => 'percentage', 'value' => 2, 'is_active' => true]);

        // 1. Enquiry — never becomes an order by itself.
        $enq = $this->actingAsUser($customer)->postJson('/api/v1/enquiries', ['product_id' => $product->id, 'message' => 'Is the black one available?'])
            ->assertCreated()->assertJsonPath('enquiry.status', 'new');
        $this->assertSame(0, Order::count());
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/enquiries/{$enq->json('enquiry.id')}/reply", ['reply' => 'Yes, in stock.'])->assertOk();

        // 2. Order request.
        $order = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'shop_id' => $shop->id, 'enquiry_id' => $enq->json('enquiry.id'), 'fulfilment' => 'delivery',
            'delivery_address' => 'Jali', 'delivery_area_id' => $shop->area_id, 'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.status', 'requested')->json('order');
        $this->assertSame('converted', $enq->json('enquiry.status') === 'new' ? \App\Models\Enquiry::find($enq->json('enquiry.id'))->status : null);

        // Merchant cannot confirm before the customer accepts a quotation.
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/confirm")->assertStatus(422);

        // 3–4. Merchant confirms exact variant, colour, price and delivery fee.
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/quote", [
            'items' => [['id' => $order['items'][0]['id'], 'unit_price' => 1100, 'colour' => 'Black', 'variant' => '25W']],
            'delivery_fee' => 40,
        ])->assertOk()->assertJsonPath('order.status', 'quoted')->assertJsonPath('order.total_amount', '2240.00');

        // 5. Customer accepts.
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order['id']}/accept")->assertOk()->assertJsonPath('order.status', 'accepted');

        // 6. Merchant confirms — stock is reserved and commission snapshotted (2% of product amount only).
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/confirm")->assertOk()
            ->assertJsonPath('order.status', 'confirmed')->assertJsonPath('order.commission_amount', '44.00');
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('product_stock_logs', ['product_id' => $product->id, 'reason' => 'order_confirmed', 'change' => -2]);

        // 7–8. Ready, then delivered.
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/ready")->assertOk();
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/payments", ['method' => 'upi', 'amount' => 2240, 'upi_reference' => '412345678901'])
            ->assertCreated()->assertJsonPath('order.payment_status', 'paid');
        $this->actingAsUser($merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/handover")->assertOk()->assertJsonPath('order.status', 'handed_over');

        // Merchant cannot mark it completed — only the customer confirms receipt.
        $this->actingAsUser($merchant)->postJson("/api/v1/orders/{$order['id']}/confirm-receipt")->assertStatus(403);

        // 9–10. Customer confirms receipt → completed.
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order['id']}/confirm-receipt")->assertOk()
            ->assertJsonPath('order.status', 'completed')->assertJsonPath('order.merchant_payable', '2196.00');

        $statuses = Order::find($order['id'])->histories->pluck('to_status')->unique()->values()->all();
        $this->assertEquals(['requested', 'quoted', 'accepted', 'confirmed', 'ready', 'handed_over', 'completed'], $statuses);
    }

    public function test_cancelling_a_confirmed_order_restores_stock(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, ['stock_quantity' => 3]);
        $customer = $this->customer();
        $order = $this->placeAndConfirm($shop, $product, $customer);
        $this->assertSame(2, $product->fresh()->stock_quantity);

        // Customer can no longer cancel once the shop confirmed; the shop can.
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])->assertStatus(422);
        $this->actingAsUser($shop->merchant)->postJson("/api/v1/merchant/orders/{$order->id}/cancel", ['reason' => 'Customer request'])->assertOk();
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame('0.00', $order->fresh()->merchant_payable);
    }

    public function test_merchant_cannot_access_another_shops_orders(): void
    {
        $order = $this->placeAndConfirm($shop = $this->shop(), $this->product($shop), $this->customer());
        $other = $this->shop();
        $this->actingAsUser($other->merchant)->getJson("/api/v1/merchant/orders/{$order->id}")->assertStatus(403);
        $this->actingAsUser($this->customer())->getJson("/api/v1/orders/{$order->id}")->assertStatus(403);
    }

    public function test_insufficient_stock_blocks_confirmation(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, ['stock_quantity' => 1]);
        $customer = $this->customer();
        $order = $this->actingAsUser($customer)->postJson('/api/v1/orders', ['shop_id' => $shop->id, 'fulfilment' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 3]]])->json('order');
        $this->actingAsUser($shop->merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/quote", ['items' => [['id' => $order['items'][0]['id'], 'unit_price' => 1000]]]);
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order['id']}/accept");
        $this->actingAsUser($shop->merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/confirm")->assertStatus(422);
    }

    public function test_delivery_is_rejected_when_shop_does_not_deliver(): void
    {
        $shop = $this->shop(['delivery_available' => false]);
        $this->actingAsUser($this->customer())->postJson('/api/v1/orders', ['shop_id' => $shop->id, 'fulfilment' => 'delivery', 'delivery_address' => 'x', 'delivery_area_id' => $shop->area_id,
            'items' => [['product_id' => $this->product($shop)->id, 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_category_commission_overrides_global_and_shop_overrides_category(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop);
        Commission::create(['scope' => 'global', 'type' => 'free', 'value' => 0]);
        Commission::create(['scope' => 'category', 'category_id' => Category::where('slug', 'chargers')->value('id'), 'type' => 'percentage', 'value' => 5]);
        $order = $this->placeAndConfirm($shop, $product, $this->customer(), 1000);
        $this->assertSame('50.00', $order->fresh()->commission_amount);

        Commission::create(['scope' => 'shop', 'shop_id' => $shop->id, 'type' => 'flat', 'value' => 20]);
        $order2 = $this->placeAndConfirm($shop, $product, $this->customer(), 1000);
        $this->assertSame('20.00', $order2->fresh()->commission_amount);
    }

    private function placeAndConfirm($shop, $product, $customer, $price = 1200): Order
    {
        $order = $this->actingAsUser($customer)->postJson('/api/v1/orders', ['shop_id' => $shop->id, 'fulfilment' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->json('order');
        $this->actingAsUser($shop->merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/quote", ['items' => [['id' => $order['items'][0]['id'], 'unit_price' => $price]]])->assertOk();
        $this->actingAsUser($customer)->postJson("/api/v1/orders/{$order['id']}/accept")->assertOk();
        $this->actingAsUser($shop->merchant)->postJson("/api/v1/merchant/orders/{$order['id']}/confirm")->assertOk();

        return Order::find($order['id']);
    }
}
