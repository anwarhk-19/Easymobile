<?php

use App\Models\OtpCode;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Notifications\PlatformNotification;
use App\Services\SupportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bmh:escalate-tickets', function (SupportService $support) {
    $this->info('Escalated '.$support->escalateOverdue().' overdue ticket(s).');
})->purpose('Escalate support tickets not acknowledged within one business day');

Artisan::command('bmh:prune-otps', function () {
    $n = OtpCode::where('expires_at', '<', now()->subDay())->delete();
    $this->info("Pruned {$n} expired OTP code(s).");
})->purpose('Delete expired OTP codes');

Artisan::command('bmh:stock-reminders', function () {
    $days = (int) Setting::get('stock_stale_days');
    $sent = 0;
    Shop::published()->with('merchant')->each(function (Shop $shop) use ($days, &$sent) {
        $stale = Product::where('shop_id', $shop->id)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('stock_confirmed_at')->orWhere('stock_confirmed_at', '<', now()->subDays($days)))->count();
        if ($stale > 0) {
            $shop->merchant?->notify(new PlatformNotification('Confirm your stock', "{$stale} product(s) have not had stock confirmed in {$days}+ days. Customers see this as stale stock.", '/merchant/products?status=stale', 'warning'));
            $sent++;
        }
    });
    $this->info("Sent {$sent} stock reminder(s).");
})->purpose('Remind merchants to confirm stale stock');

Schedule::command('bmh:escalate-tickets')->hourly();
Schedule::command('bmh:prune-otps')->daily();
Schedule::command('bmh:stock-reminders')->dailyAt('03:30'); // 09:00 IST
Schedule::command('queue:prune-failed --hours=168')->daily();
