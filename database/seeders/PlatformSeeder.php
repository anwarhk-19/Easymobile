<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Commission;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD') ?: null;
        if (app()->isProduction() && ! $adminPassword) {
            throw new \RuntimeException('Set ADMIN_PASSWORD in .env before seeding production.');
        }
        $adminEmail = env('ADMIN_EMAIL') ?: 'admin@bhatkalmobilehub.in';
        if (! User::where('email', $adminEmail)->exists()) {
            User::create(['email' => $adminEmail, 'name' => 'Platform Admin', 'role' => 'admin', 'status' => 'active', 'password' => $adminPassword ?? 'ChangeMe@2026']);
        }
        // Demo support operator only outside production; create real operators from Admin → Users.
        if (! app()->isProduction() && ! User::where('email', 'support@bhatkalmobilehub.in')->exists()) {
            User::create(['email' => 'support@bhatkalmobilehub.in', 'name' => 'Support Desk', 'role' => 'support', 'status' => 'active', 'password' => 'Support@2026']);
        }

        // Pilot: free listings for everyone, small per-order commission on smartphones only.
        Commission::firstOrCreate(['scope' => 'global'], ['type' => 'free', 'value' => 0, 'is_active' => true, 'notes' => 'Pilot: free merchant listings']);
        Commission::firstOrCreate(['scope' => 'category', 'category_id' => Category::where('slug', 'smartphones')->value('id')],
            ['type' => 'percentage', 'value' => 1.5, 'is_active' => true, 'notes' => 'Per-order commission on smartphone sales']);

        $names = [
            'product_enquiry' => 'Product enquiry (customer → shop)', 'shop_enquiry' => 'Shop enquiry (customer → shop)',
            'order_confirmation' => 'Order confirmation (shop → customer)', 'order_followup' => 'Order follow-up (customer → shop)',
            'repair_enquiry' => 'Repair enquiry (customer → technician)', 'repair_followup' => 'Repair follow-up (customer → technician)',
        ];
        foreach (TemplateRenderer::DEFAULTS['whatsapp'] as $key => $body) {
            NotificationTemplate::firstOrCreate(['key' => $key, 'channel' => 'whatsapp'], ['name' => $names[$key] ?? $key, 'body' => $body, 'is_active' => true]);
        }
        foreach (Setting::DEFAULTS as $k => $v) {
            if (! Setting::where('key', $k)->exists()) {
                Setting::put($k, $v);
            }
        }
    }
}
