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
    public const DEFAULT_ADMIN_EMAIL = 'admin@bhatkalmobilehub.in';

    public const DEFAULT_ADMIN_PASSWORD = 'ChangeMe@2026';

    public const DEFAULT_SUPPORT_EMAIL = 'support@bhatkalmobilehub.in';

    public const DEFAULT_SUPPORT_PASSWORD = 'Support@2026';

    public function run(): void
    {
        // Default staff accounts so the site can be tried immediately. The passwords are public (they are in
        // this repository): change them in Admin → My account → Change password. /status warns while they're in use.
        $adminEmail = env('ADMIN_EMAIL') ?: self::DEFAULT_ADMIN_EMAIL;
        if (! User::where('email', $adminEmail)->exists()) {
            User::create(['email' => $adminEmail, 'name' => 'Platform Admin', 'role' => 'admin', 'status' => 'active', 'password' => env('ADMIN_PASSWORD') ?: self::DEFAULT_ADMIN_PASSWORD]);
        }
        if (! User::where('email', self::DEFAULT_SUPPORT_EMAIL)->exists()) {
            User::create(['email' => self::DEFAULT_SUPPORT_EMAIL, 'name' => 'Support Desk', 'role' => 'support', 'status' => 'active', 'password' => self::DEFAULT_SUPPORT_PASSWORD]);
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
