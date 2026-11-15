<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Commission;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\PhoneModel;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Settlement;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
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
        // Behind Hostinger's HTTPS (and any CDN) make generated URLs and asset links use https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }


        Relation::enforceMorphMap([
            'shop' => Shop::class,
            'technician' => TechnicianProfile::class,
            'product' => Product::class,
            'user' => User::class,
            'order' => Order::class,
            'repair' => RepairRequest::class,
            'review' => Review::class,
            'ticket' => SupportTicket::class,
            'settlement' => Settlement::class,
            'refund' => Refund::class,
            'commission' => Commission::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'phone_model' => PhoneModel::class,
            'area' => Area::class,
            'repair_category' => RepairCategory::class,
            'notification_template' => NotificationTemplate::class,
            'setting' => Setting::class,
        ]);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('otp', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(3)->by((string) $r->input('phone'))]);
        RateLimiter::for('otp-verify', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(6)->by('v'.(string) $r->input('phone'))]);
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
    }
}
