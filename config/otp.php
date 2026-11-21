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

    /*
     * Test ("master") OTP for trying the live site before an SMS gateway is connected.
     * Default 998001. Set OTP_TEST_CODE=off to disable it, or another 6-digit code to change it. It is accepted ONLY for the phone numbers in
     * OTP_TEST_PHONES (comma-separated). Default: the demo accounts from SampleMarketplaceSeeder.
     * OTP_TEST_PHONES=* allows every number — anyone who knows the code could then sign in as any user,
     * so use * only briefly. Remove OTP_TEST_CODE before real customers use the site.
     */
    'test_code' => preg_match('/^\d{6}$/', (string) (env('OTP_TEST_CODE') ?: '998001')) ? (string) (env('OTP_TEST_CODE') ?: '998001') : null,
    'test_phones' => array_values(array_filter(array_map('trim', explode(',', (string) env('OTP_TEST_PHONES',
        '9900000001,9900000002,9900000003,9900000004,9845000101,9845000102,9845000103,9845000104,9845000105,9845000106,9845000109,9845000201,9845000202,9845000203,9845000204,9845000209'))))),
];
