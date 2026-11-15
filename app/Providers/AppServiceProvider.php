<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            // Add real gateway drivers here (e.g. 'msg91' => new Msg91SmsSender(...)).
            default => new LogSmsSender,
        });
    }

    public function boot(): void
    {

        Relation::enforceMorphMap([
            'shop' => Shop::class,
            'technician' => TechnicianProfile::class,
            'product' => Product::class,
            'user' => \App\Models\User::class,
            'order' => \App\Models\Order::class,
            'repair' => \App\Models\RepairRequest::class,
            'review' => \App\Models\Review::class,
            'ticket' => \App\Models\SupportTicket::class,
            'settlement' => \App\Models\Settlement::class,
            'refund' => \App\Models\Refund::class,
            'commission' => \App\Models\Commission::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
            'phone_model' => \App\Models\PhoneModel::class,
            'area' => \App\Models\Area::class,
            'repair_category' => \App\Models\RepairCategory::class,
            'notification_template' => \App\Models\NotificationTemplate::class,
            'setting' => \App\Models\Setting::class,
        ]);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('otp', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(3)->by((string) $r->input('phone'))]);
        RateLimiter::for('otp-verify', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(6)->by('v'.(string) $r->input('phone'))]);
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
    }
}
