<?php

return [
    'length' => 6,
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => 5,
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 30),
    'max_sends_per_phone' => (int) env('OTP_MAX_SENDS_PER_PHONE', 5),
    'max_sends_per_ip' => (int) env('OTP_MAX_SENDS_PER_IP', 20),
    'send_window_seconds' => 3600,
    // Only ever true in local development; returns the OTP in the API response so the app can be tried without an SMS gateway.
    'expose_code' => (bool) env('OTP_EXPOSE_CODE', false) && env('APP_ENV') !== 'production',
];
