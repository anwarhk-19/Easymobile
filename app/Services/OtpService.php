<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private SmsSender $sms) {}

    /** @return array{expires_in:int, debug_code?:string} */
    public function send(string $phone, string $ip, string $purpose = 'login'): array
    {
        $cfg = config('otp');
        $phoneKey = 'otp-send:phone:'.$phone;
        $ipKey = 'otp-send:ip:'.$ip;

        // Test numbers with a test OTP configured: no SMS and no per-number limit (the per-IP limit still applies).
        if (self::testLoginEnabled() && (in_array('*', $cfg['test_phones'], true) || in_array($phone, $cfg['test_phones'], true))) {
            if (RateLimiter::tooManyAttempts($ipKey, $cfg['max_sends_per_ip'])) {
                throw ValidationException::withMessages(['phone' => 'Too many OTP requests. Try again later.'])->status(429);
            }
            RateLimiter::hit($ipKey, $cfg['send_window_seconds']);

            return ['expires_in' => $cfg['ttl_seconds'], 'test_mode' => true];
        }

        foreach ([[$phoneKey, $cfg['max_sends_per_phone']], [$ipKey, $cfg['max_sends_per_ip']]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'phone' => 'Too many OTP requests. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
                ])->status(429);
            }
        }

        $last = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < $cfg['resend_cooldown_seconds']) {
            throw ValidationException::withMessages([
                'phone' => 'Please wait before requesting another OTP.',
            ])->status(429);
        }

        RateLimiter::hit($phoneKey, $cfg['send_window_seconds']);
        RateLimiter::hit($ipKey, $cfg['send_window_seconds']);

        // Invalidate any previous unused codes for this phone.
        OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = (string) random_int(10 ** ($cfg['length'] - 1), 10 ** $cfg['length'] - 1);
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds($cfg['ttl_seconds']),
            'ip' => $ip,
        ]);

        $this->sms->send($phone, "{$code} is your Bhatkal Mobile Hub verification code. It expires in ".intdiv($cfg['ttl_seconds'], 60).' minutes. Do not share it with anyone.');

        $result = ['expires_in' => $cfg['ttl_seconds']];
        if ($cfg['expose_code']) {
            $result['debug_code'] = $code;
        }

        return $result;
    }

    public function verify(string $phone, string $code): void
    {
        if (self::isTestLogin($phone, $code)) {
            OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            Log::warning("Test OTP used to sign in {$phone}");

            return;
        }

        $otp = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The OTP has expired. Please request a new one.']);
        }
        if ($otp->attempts >= config('otp.max_attempts')) {
            $otp->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Please request a new OTP.']);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The OTP entered is incorrect.']);
        }

        $otp->update(['consumed_at' => now()]);
    }

    /** The configured test OTP, for an allowed test number (see config/otp.php). */
    public static function isTestLogin(string $phone, string $code): bool
    {
        $testCode = config('otp.test_code');
        $phones = config('otp.test_phones', []);

        return $testCode !== null && hash_equals($testCode, $code)
            && (in_array('*', $phones, true) || in_array($phone, $phones, true));
    }

    public static function testLoginEnabled(): bool
    {
        return config('otp.test_code') !== null;
    }
}
