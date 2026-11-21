<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Setup · Bhatkal Mobile Hub</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 560px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 22px 0 8px; }
        p.lead { color: #475569; margin: 0 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; }
        label { display: block; font-weight: 600; font-size: 14px; margin: 12px 0 4px; }
        label:first-child { margin-top: 0; }
        input[type=text], input[type=email], input[type=password], input[type=url], input[type=number] { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; background: #fff; }
        input:focus { outline: 2px solid #14a38b; border-color: #14a38b; }
        .hint { font-size: 12.5px; color: #64748b; margin-top: 3px; }
        .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 500; margin-top: 12px; }
        .check input { margin-top: 4px; }
        button { width: 100%; margin-top: 20px; padding: 13px; border: 0; border-radius: 12px; background: #0d8573; color: #fff; font: 600 16px system-ui, sans-serif; }
        button:disabled { opacity: .6; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
        .errors li { margin: 2px 0; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 12px; padding: 14px; }
        .ok li { margin: 4px 0; }
        a.btn { display: block; text-align: center; margin-top: 12px; padding: 12px; border-radius: 12px; background: #0d8573; color: #fff; text-decoration: none; font-weight: 600; }
        a.btn.alt { background: #fff; color: #0d8573; border: 1px solid #0d8573; }
        code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 6px 4px; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
<main>
    @if ($done)
        <h1>✅ Setup complete</h1>
        <p class="lead">Bhatkal Mobile Hub is ready. This setup page is now locked and can't be used again.</p>
        <div class="ok"><ul>@foreach ($steps as $s)<li>{{ $s }}</li>@endforeach</ul></div>

        <h2>Admin sign-in</h2>
        <div class="card">
            <table>
                <tr><td>Page</td><td><b>{{ rtrim($data['app_url'], '/') }}/staff</b></td></tr>
                <tr><td>Email</td><td><b>{{ $data['admin_email'] }}</b></td></tr>
                <tr><td>Password</td><td>@if ($data['default_password'])<b>ChangeMe@2026</b>@else the one you entered @endif</td></tr>
                <tr><td>Support</td><td><b>support@bhatkalmobilehub.in</b> / <b>Support@2026</b></td></tr>
            </table>
        </div>

        @if (! empty($data['test_otp']))
            <h2>Test logins (OTP {{ $data['test_otp'] }})</h2>
            <div class="card">
                <table>
                    <tr><td>Customer</td><td><b>9900000001</b></td></tr>
                    <tr><td>Shop owner</td><td><b>9845000101</b></td></tr>
                    <tr><td>Technician</td><td><b>9845000201</b></td></tr>
                </table>
                <div class="hint">Sign in at {{ rtrim($data['app_url'], '/') }}/login. These numbers exist only if you added demo data. Before real customers use the site, set <code>OTP_TEST_CODE=off</code> in .env.</div>
            </div>
        @endif

        <a class="btn" href="{{ rtrim($data['app_url'], '/') }}/">Open the site</a>
        <a class="btn alt" href="{{ rtrim($data['app_url'], '/') }}/status">Check server status</a>
    @else
        <h1>Set up Bhatkal Mobile Hub</h1>
        <p class="lead">One-time setup. Takes about a minute. You need the MySQL database details from <b>hPanel → Databases → MySQL Databases</b> (create a database there first if you haven't).</p>

        @if ($errors)
            <div class="errors"><ul>@foreach ($errors as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Installing… please wait'">
            <h2>Website</h2>
            <div class="card">
                <label for="app_url">Site address</label>
                <input id="app_url" type="url" name="app_url" required value="{{ $old['app_url'] ?? '' }}">
                <div class="hint">Usually filled in correctly already, e.g. https://easymobile.devpulsetech.com</div>
            </div>

            <h2>Database (from hPanel)</h2>
            <div class="card">
                <label for="db_database">Database name</label>
                <input id="db_database" type="text" name="db_database" required placeholder="u123456789_bmh" value="{{ $old['db_database'] ?? '' }}" autocapitalize="off" autocorrect="off">
                <label for="db_username">Database username</label>
                <input id="db_username" type="text" name="db_username" required placeholder="u123456789_bmh" value="{{ $old['db_username'] ?? '' }}" autocapitalize="off" autocorrect="off">
                <label for="db_password">Database password</label>
                <input id="db_password" type="password" name="db_password" autocomplete="off">
                <label for="db_host">Database host</label>
                <input id="db_host" type="text" name="db_host" required value="{{ $old['db_host'] ?? 'localhost' }}" autocapitalize="off">
                <div class="hint">Keep <code>localhost</code> on Hostinger.</div>
                <label for="db_port">Port</label>
                <input id="db_port" type="number" name="db_port" required value="{{ $old['db_port'] ?? '3306' }}">
            </div>

            <h2>Admin account</h2>
            <div class="card">
                <div class="hint" style="margin-top:0">Leave blank to use the default login <b>admin@bhatkalmobilehub.in</b> / <b>ChangeMe@2026</b> (change it afterwards in Admin → My account).</div>
                <label for="admin_email">Admin email</label>
                <input id="admin_email" type="email" name="admin_email" placeholder="admin@bhatkalmobilehub.in" value="{{ $old['admin_email'] ?? '' }}" autocapitalize="off">
                <label for="admin_password">Admin password</label>
                <input id="admin_password" type="password" name="admin_password" minlength="10" autocomplete="new-password" placeholder="ChangeMe@2026">
                <label for="admin_password_confirmation">Repeat admin password</label>
                <input id="admin_password_confirmation" type="password" name="admin_password_confirmation" minlength="10" autocomplete="new-password">
                <div class="hint">If you set your own: at least 10 characters.</div>
            </div>

            <h2>Testing (optional)</h2>
            <div class="card">
                <label class="check"><input type="checkbox" name="demo_data" value="1" @checked($old['demo_data'] ?? false)> Add demo shops, products, technicians and customers</label>
                <label for="test_otp">Test OTP (6 digits)</label>
                <input id="test_otp" type="text" name="test_otp" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="e.g. 246810" value="{{ $old['test_otp'] ?? '' }}">
                <div class="hint">Lets you sign in as the demo accounts without SMS. Clear it to turn test logins off.</div>
            </div>

            <button type="submit">Install</button>
        </form>
    @endif
</main>
</body>
</html>
