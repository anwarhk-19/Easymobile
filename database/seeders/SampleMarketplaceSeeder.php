<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PhoneModel;
use App\Models\Product;
use App\Models\RepairCategory;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\OrderService;
use App\Services\RepairService;
use App\Services\ReviewService;
use App\Services\SupportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Sample Bhatkal shops, products, technicians and transactions for demos and local development. */
class SampleMarketplaceSeeder extends Seeder
{
    private const SHOPS = [
        ['Al-Noor Mobiles', 'Abdul Rahman', '9845000101', 'Main Road', 'Opp. Jama Masjid, Main Road, Bhatkal', true, true, 30, '09:30', '21:30', 13.9861, 74.5559],
        ['City Mobile Point', 'Mohammed Faiz', '9845000102', 'Old Bus Stand', 'Shop 4, Old Bus Stand Complex, Bhatkal', true, true, 25, '09:00', '22:00', 13.9849, 74.5571],
        ['Smart Zone Bhatkal', 'Nihal Shaikh', '9845000103', 'Nawayath Colony', 'Near Anjuman College, Nawayath Colony, Bhatkal', false, true, 0, '10:00', '21:00', 13.9932, 74.5504],
        ['Mobile Galaxy', 'Ravi Naik', '9845000104', 'Shirali', 'NH 66, Shirali Junction, Bhatkal', true, true, 40, '09:00', '20:30', 14.0405, 74.5395],
        ['Sea View Accessories', 'Sameer Kola', '9845000105', 'Bandar Road', 'Bandar Road, near Fish Market, Bhatkal', false, true, 0, '10:00', '22:30', 13.9790, 74.5480],
        ['Murdeshwar Mobile Care', 'Ganesh Shetty', '9845000106', 'Murdeshwar', 'Temple Road, Murdeshwar', true, true, 50, '09:00', '21:00', 14.0943, 74.4848],
    ];

    /** [name, category slug, brand, price, discount, stock, compatible models (brand => [models])] */
    private const PRODUCTS = [
        ['Samsung Galaxy A35 5G (8GB/128GB)', 'smartphones', 'Samsung', 30999, 28999, 4, []],
        ['Samsung Galaxy A15 5G (6GB/128GB)', 'smartphones', 'Samsung', 17999, 16499, 6, []],
        ['Redmi Note 13 5G (8GB/256GB)', 'smartphones', 'Xiaomi', 20999, 18999, 5, []],
        ['Redmi 13C (4GB/128GB)', 'smartphones', 'Xiaomi', 9999, 8999, 8, []],
        ['iPhone 15 (128GB)', 'smartphones', 'Apple', 69900, 64900, 2, []],
        ['iPhone 13 (128GB)', 'smartphones', 'Apple', 52900, 47900, 1, []],
        ['Vivo T3 5G (8GB/128GB)', 'smartphones', 'Vivo', 19999, null, 3, []],
        ['OnePlus Nord CE 4 (8GB/128GB)', 'smartphones', 'OnePlus', 24999, 23999, 3, []],
        ['Realme Narzo 70 (6GB/128GB)', 'smartphones', 'Realme', 15999, 14499, 4, []],
        ['Motorola G34 5G (8GB/128GB)', 'smartphones', 'Motorola', 11999, 10999, 5, []],
        ['Samsung 25W USB-C Fast Charger', 'chargers', 'Samsung', 1499, 1199, 12, ['Samsung' => ['Galaxy S24', 'Galaxy A55 5G', 'Galaxy A35 5G', 'Galaxy A15 5G', 'Galaxy M34 5G']]],
        ['Apple 20W USB-C Power Adapter', 'chargers', 'Apple', 1900, 1699, 6, ['Apple' => ['iPhone 16', 'iPhone 15', 'iPhone 14', 'iPhone 13', 'iPhone 12']]],
        ['Mi 33W SonicCharge Adapter', 'chargers', 'Mi', 999, 799, 15, ['Xiaomi' => ['Redmi Note 13', 'Redmi Note 12', 'Redmi 13C']]],
        ['Portronics Adapto 65W GaN Charger', 'chargers', 'Portronics', 2499, 1799, 7, []],
        ['boAt Airdopes 141 TWS Earbuds', 'earphones', 'boAt', 1999, 1299, 20, []],
        ['boAt Bassheads 100 Wired Earphones', 'earphones', 'boAt', 699, 399, 30, []],
        ['Noise Buds VS104 TWS', 'earphones', 'Noise', 1799, 1099, 10, []],
        ['JBL Tune 510BT Wireless Headphones', 'headphones', 'JBL', 4999, 3499, 4, []],
        ['boAt Rockerz 450 Bluetooth Headphones', 'headphones', 'boAt', 3990, 1499, 6, []],
        ['Spigen Ultra Hybrid Case – iPhone 15', 'mobile-covers', 'Spigen', 1999, 1499, 5, ['Apple' => ['iPhone 15']]],
        ['Silicone Back Cover – Galaxy A35 5G', 'mobile-covers', null, 399, 299, 15, ['Samsung' => ['Galaxy A35 5G']]],
        ['Transparent TPU Cover – Redmi Note 13', 'mobile-covers', null, 299, 199, 25, ['Xiaomi' => ['Redmi Note 13', 'Redmi Note 13 Pro']]],
        ['Flip Wallet Cover – Vivo Y28', 'mobile-covers', null, 449, 349, 8, ['Vivo' => ['Y28']]],
        ['Tempered Glass 9H – iPhone 13/14', 'screen-protectors', null, 299, 199, 40, ['Apple' => ['iPhone 13', 'iPhone 14']]],
        ['Tempered Glass 9H – Galaxy A15/A35', 'screen-protectors', null, 249, 149, 35, ['Samsung' => ['Galaxy A15 5G', 'Galaxy A35 5G']]],
        ['Privacy Screen Guard – iPhone 15', 'screen-protectors', 'Spigen', 999, 799, 6, ['Apple' => ['iPhone 15', 'iPhone 16']]],
        ['Ambrane 20000mAh 22.5W Power Bank', 'power-banks', 'Ambrane', 2499, 1599, 9, []],
        ['Mi 10000mAh Pocket Power Bank', 'power-banks', 'Mi', 1499, 1199, 0, []],
        ['Noise ColorFit Pro 5 Smartwatch', 'smartwatches', 'Noise', 4999, 2999, 3, []],
        ['boAt Wave Call 2 Smartwatch', 'smartwatches', 'boAt', 3999, 1499, 5, []],
        ['Type-C to Type-C Braided Cable 1m', 'cables', 'Portronics', 499, 299, 30, []],
        ['Lightning to USB-C Cable 1m', 'cables', 'Apple', 1900, 1500, 4, ['Apple' => ['iPhone 14', 'iPhone 13', 'iPhone 12', 'iPhone 11']]],
        ['Micro-USB Fast Charging Cable', 'cables', null, 199, 149, 50, []],
        ['Car Mobile Holder (Dashboard)', 'other-accessories', 'Portronics', 799, 549, 10, []],
        ['Selfie Stick with Bluetooth Remote', 'other-accessories', null, 599, 399, 12, []],
        ['Memory Card 64GB Class 10', 'other-accessories', null, 699, 549, 18, []],
    ];

    private const TECHNICIANS = [
        ['Imran Mobile Repairs', 'Imran Kazi', 'Imran Mobile Repairs', '9845000201', 'Main Road', 8, true, false, 90, ['Display replacement', 'Battery replacement', 'Charging problems', 'Motherboard repair', 'Water damage'], ['Samsung', 'Apple', 'Xiaomi', 'OnePlus']],
        ['Fix-It Fast Bhatkal', 'Ashfaq Siddiqui', 'Fix-It Fast', '9845000202', 'Nawayath Colony', 5, true, true, 60, ['Display replacement', 'Battery replacement', 'Software issues', 'Camera repair', 'Speaker and microphone problems'], ['Samsung', 'Xiaomi', 'Vivo', 'Oppo', 'Realme']],
        ['Prakash Naik', 'Prakash Naik', null, '9845000203', 'Shirali', 12, false, true, 30, ['Motherboard repair', 'Water damage', 'Network problems', 'Charging problems', 'Other repairs'], ['Samsung', 'Vivo', 'Oppo', 'Motorola', 'Realme']],
        ['iCare Apple Specialists', 'Zaid Mohtesham', 'iCare', '9845000204', 'Old Bus Stand', 6, true, false, 90, ['Display replacement', 'Battery replacement', 'Camera repair', 'Software issues'], ['Apple']],
    ];

    public function run(): void
    {
        if (Shop::exists()) {
            return; // idempotent: don't duplicate sample data
        }
        $areas = Area::pluck('id', 'name');
        $cats = Category::pluck('id', 'slug');
        $brands = Brand::pluck('id', 'name');
        $now = now();

        $shops = [];
        foreach (self::SHOPS as $i => [$name, $owner, $phone, $area, $address, $delivery, $pickup, $fee, $open, $close, $lat, $lng]) {
            $merchant = User::create(['name' => $owner, 'phone' => $phone, 'role' => 'merchant', 'status' => 'active']);
            $merchant->forceFill(['phone_verified_at' => $now])->save();
            $merchant->merchantProfile()->create(['owner_name' => $owner]);
            $shops[] = Shop::create([
                'merchant_id' => $merchant->id, 'name' => $name, 'slug' => Str::slug($name), 'owner_name' => $owner,
                'phone' => $phone, 'whatsapp' => $phone, 'address' => $address, 'area_id' => $areas[$area],
                'latitude' => $lat, 'longitude' => $lng, 'opens_at' => $open, 'closes_at' => $close,
                'open_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] + ($i % 2 ? [6 => 'sun'] : []),
                'description' => "{$name} — trusted mobile phones and accessories in {$area}, Bhatkal. Genuine products with bill and warranty.",
                'pickup_available' => $pickup, 'delivery_available' => $delivery, 'delivery_fee' => $fee,
                'delivery_notes' => $delivery ? 'Same-day delivery within Bhatkal town for orders placed before 6 PM.' : null,
                'status' => 'approved', 'approved_at' => $now, 'is_featured' => $i < 4,
            ]);
        }
        // One shop waiting for approval so the verification queue has data.
        $pendingMerchant = User::create(['name' => 'Yusuf Damudi', 'phone' => '9845000109', 'role' => 'merchant', 'status' => 'active']);
        $pendingMerchant->merchantProfile()->create(['owner_name' => 'Yusuf Damudi']);
        Shop::create(['merchant_id' => $pendingMerchant->id, 'name' => 'New Era Mobiles', 'slug' => 'new-era-mobiles', 'owner_name' => 'Yusuf Damudi',
            'phone' => '9845000109', 'whatsapp' => '9845000109', 'address' => 'Sagar Road, Bhatkal', 'area_id' => $areas['Sagar Road'],
            'opens_at' => '10:00', 'closes_at' => '21:00', 'status' => 'pending', 'pickup_available' => true]);

        foreach (self::PRODUCTS as $i => [$name, $cat, $brand, $price, $discount, $stock, $compat]) {
            // Spread products across shops; popular items are stocked by two shops for price comparison.
            $targets = [$shops[$i % count($shops)]];
            if ($i % 3 === 0) {
                $targets[] = $shops[($i + 2) % count($shops)];
            }
            foreach ($targets as $j => $shop) {
                $p = Product::create([
                    'shop_id' => $shop->id, 'category_id' => $cats[$cat], 'brand_id' => $brand ? ($brands[$brand] ?? null) : null,
                    'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                    'description' => "{$name}. 100% genuine with shop bill. Visit {$shop->name} or order for pickup/delivery.",
                    'sku' => strtoupper(Str::substr(Str::slug($cat, ''), 0, 4)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT).($j ? '-B' : ''),
                    'price' => $price, 'discount_price' => $discount ? $discount + ($j * 200) : null,
                    'stock_quantity' => $stock, 'availability' => $stock === 0 ? 'out_of_stock' : ($stock <= 2 ? 'limited' : 'in_stock'),
                    'stock_confirmed_at' => $now->copy()->subDays(($i * 3) % 12)->subHours($i), 'status' => 'active', 'views_count' => ($i * 37) % 250,
                ]);
                $p->stockLogs()->create(['old_quantity' => 0, 'new_quantity' => $stock, 'change' => $stock, 'reason' => 'initial']);
                foreach ($compat as $b => $models) {
                    $p->compatibleModels()->attach(PhoneModel::where('brand_id', $brands[$b])->whereIn('name', $models)->pluck('id'));
                }
            }
        }

        $repairCats = RepairCategory::pluck('id', 'name');
        $techs = [];
        foreach (self::TECHNICIANS as [$display, $person, $shopName, $phone, $area, $exp, $pickup, $onsite, $warranty, $cats2, $brands2]) {
            $u = User::create(['name' => $person, 'phone' => $phone, 'role' => 'technician', 'status' => 'active']);
            $t = TechnicianProfile::create([
                'user_id' => $u->id, 'name' => $display, 'slug' => Str::slug($display), 'shop_name' => $shopName, 'phone' => $phone, 'whatsapp' => $phone,
                'address' => "{$area}, Bhatkal", 'area_id' => $areas[$area], 'experience_years' => $exp,
                'certifications' => $exp > 7 ? 'Advanced chip-level repair training (Bengaluru)' : null,
                'opens_at' => '10:00', 'closes_at' => '20:30', 'open_days' => ['mon', 'tue', 'wed', 'thu', 'sat', 'sun'],
                'pickup_available' => $pickup, 'onsite_available' => $onsite, 'default_warranty_days' => $warranty,
                'warranty_terms' => "{$warranty}-day warranty on replaced parts and workmanship. Physical/liquid damage after handover is not covered.",
                'bio' => "{$exp}+ years repairing smartphones in Bhatkal. Transparent quotes — no work without your approval.",
                'status' => 'approved', 'approved_at' => $now,
            ]);
            $t->repairCategories()->sync($repairCats->only($cats2)->values());
            $t->brands()->sync($brands->only($brands2)->values());
            $t->serviceAreas()->sync($areas->only([$area, 'Main Road', 'Nawayath Colony', 'Shirali'])->values());
            $techs[] = $t;
        }
        $pt = User::create(['name' => 'Sandeep Mogaveera', 'phone' => '9845000209', 'role' => 'technician', 'status' => 'active']);
        TechnicianProfile::create(['user_id' => $pt->id, 'name' => 'Sandeep Mobile Clinic', 'slug' => 'sandeep-mobile-clinic', 'phone' => '9845000209',
            'address' => 'Venkatapur, Bhatkal', 'area_id' => $areas['Venkatapur'], 'experience_years' => 3, 'opens_at' => '09:00', 'closes_at' => '19:00',
            'warranty_terms' => '30 days on parts.', 'status' => 'pending']);

        $this->sampleTransactions($shops, $techs);
    }

    private function sampleTransactions(array $shops, array $techs): void
    {
        $orders = app(OrderService::class);
        $repairs = app(RepairService::class);
        $reviews = app(ReviewService::class);
        $customers = collect([['Ayesha Banu', '9900000001'], ['Faizan Ahmed', '9900000002'], ['Priya Shanbhag', '9900000003'], ['Rizwan Kola', '9900000004']])
            ->map(function ($c) {
                $u = User::create(['name' => $c[0], 'phone' => $c[1], 'role' => 'customer', 'status' => 'active']);
                $u->customerProfile()->create(['area_id' => Area::inRandomOrder()->value('id')]);

                return $u;
            });

        // Completed orders with reviews.
        foreach ($customers as $k => $customer) {
            $shop = $shops[$k];
            $product = Product::where('shop_id', $shop->id)->where('stock_quantity', '>', 2)->first();
            if (! $product) {
                continue;
            }
            $order = $orders->createRequest($customer, $shop, [['product_id' => $product->id, 'quantity' => 1]], ['fulfilment' => 'pickup']);
            $order->load('items');
            $orders->quote($order, $shop->merchant, ['items' => [['id' => $order->items[0]->id, 'unit_price' => $product->effective_price, 'colour' => 'Black']]]);
            $orders->accept($order, $customer);
            $orders->confirm($order->load('items.product'), $shop->merchant);
            $orders->markReady($order, $shop->merchant);
            $orders->markHandedOver($order, $shop->merchant);
            $orders->recordPayment($order, $shop->merchant, ['method' => $k % 2 ? 'upi' : 'pickup_payment', 'amount' => $order->total_amount, 'upi_reference' => $k % 2 ? '4123456789'.$k : null]);
            $orders->confirmReceipt($order, $customer);
            foreach ($reviews->forOrder($customer, $order->load('items', 'shop'), ['shop_rating' => 4 + ($k % 2), 'shop_comment' => 'Genuine product and quick service. Recommended!']) as $r) {
                $r->update(['status' => 'approved']);
                $reviews->refreshAggregate($r->reviewable);
            }
        }

        // Open order request awaiting the shop.
        $p = Product::where('shop_id', $shops[0]->id)->where('stock_quantity', '>', 0)->skip(1)->first();
        if ($p) {
            $orders->createRequest($customers[1], $shops[0], [['product_id' => $p->id, 'quantity' => 1, 'colour' => 'Blue']], ['fulfilment' => 'delivery', 'delivery_address' => 'Near Darul Uloom, Nawayath Colony', 'delivery_area_id' => Area::where('slug', 'nawayath-colony')->value('id'), 'customer_note' => 'Please confirm if blue colour is available.']);
        }

        // Repair: completed with warranty + review.
        $samsung = Brand::where('name', 'Samsung')->first();
        $r = $repairs->create($customers[0], $techs[0], ['brand_id' => $samsung->id, 'phone_model_id' => PhoneModel::where('name', 'Galaxy A35 5G')->value('id'),
            'repair_category_id' => RepairCategory::where('slug', 'display-replacement')->value('id'), 'issue_description' => 'Screen cracked after a fall, touch not working at the bottom.', 'service_mode' => 'walk_in']);
        $tu = $techs[0]->user;
        $repairs->accept($r, $tu);
        $repairs->recordDiagnosis($r, $tu, 'Display panel cracked, digitizer damaged. Frame intact. No other faults.');
        $repairs->submitQuotation($r, $tu, ['diagnosis' => 'Replace AMOLED display assembly.', 'parts_cost' => 5200, 'labour_cost' => 500, 'parts_type' => 'original', 'estimated_hours' => 3, 'warranty_days' => 90, 'warranty_exclusions' => 'Physical or liquid damage after handover.']);
        $repairs->respondToQuotation($r->fresh(), $customers[0], true);
        $r = $r->fresh();
        $repairs->start($r, $tu);
        $repairs->addPart($r, $tu, ['name' => 'Galaxy A35 AMOLED display assembly', 'parts_type' => 'original', 'quantity' => 1, 'unit_cost' => 5200]);
        $repairs->startTesting($r, $tu);
        $repairs->completeTesting($r, $tu, ['display' => true, 'touch' => true, 'charging' => true, 'camera' => true, 'speaker' => true], true);
        $repairs->confirmHandover($r, $customers[0]);
        $repairs->complete($r->fresh()->load('technician'), $tu, ['payment_method' => 'upi']);
        $rev = $reviews->forRepair($customers[0], $r->fresh(), ['rating' => 5, 'comment' => 'Original display fitted in 3 hours. Very professional.']);
        $rev->update(['status' => 'approved']);
        $reviews->refreshAggregate($techs[0]);

        // Repair awaiting quotation approval.
        $r2 = $repairs->create($customers[2], $techs[1], ['brand_id' => Brand::where('name', 'Xiaomi')->value('id'), 'phone_model_id' => PhoneModel::where('name', 'Redmi Note 12')->value('id'),
            'repair_category_id' => RepairCategory::where('slug', 'battery-replacement')->value('id'), 'issue_description' => 'Battery drains within 3 hours and phone gets hot while charging.', 'service_mode' => 'pickup_drop', 'address' => 'Jali, Bhatkal']);
        $repairs->accept($r2, $techs[1]->user);
        $repairs->submitQuotation($r2, $techs[1]->user, ['diagnosis' => 'Battery health 61%, swollen slightly. Replacement recommended.', 'parts_cost' => 1400, 'labour_cost' => 300, 'parts_type' => 'oem_compatible', 'estimated_hours' => 2, 'warranty_days' => 60]);

        // New repair request.
        $repairs->create($customers[3], $techs[3], ['brand_id' => Brand::where('name', 'Apple')->value('id'), 'phone_model_id' => PhoneModel::where('name', 'iPhone 12')->value('id'),
            'repair_category_id' => RepairCategory::where('slug', 'camera-repair')->value('id'), 'issue_description' => 'Rear camera is blurry and shaking when opening camera app.', 'service_mode' => 'walk_in']);

        // A support ticket.
        app(SupportService::class)->open($customers[1], ['category' => 'delayed_delivery', 'subject' => 'Delivery not yet received', 'description' => 'My order was supposed to arrive yesterday evening but has not come yet.', 'priority' => 'normal']);

        // Search logs, including one with no results.
        foreach ([['type c charger', 6], ['iphone 15 cover', 2], ['nokia 3310 battery', 0], ['galaxy a35 glass', 2], ['pixel 8 back cover', 0]] as [$q, $n]) {
            SearchLog::create(['query' => $q, 'type' => 'all', 'results_count' => $n, 'created_at' => now()->subDays(rand(0, 6))]);
        }
    }
}
