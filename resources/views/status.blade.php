@php($failed = collect($checks)->where('ok', false)->count())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Status · Bhatkal Mobile Hub</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .sum { padding: 14px 16px; border-radius: 14px; margin: 16px 0; font-weight: 600; }
        .bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .good { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        ul { list-style: none; padding: 0; margin: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        li { padding: 12px 16px; border-top: 1px solid #f1f5f9; }
        li:first-child { border-top: 0; }
        .row { display: flex; gap: 10px; align-items: flex-start; }
        .icon { font-weight: 700; width: 18px; flex: none; }
        .ok .icon { color: #059669; } .fail .icon { color: #dc2626; }
        .hint { margin: 4px 0 0 28px; font-size: 13px; color: #475569; word-break: break-word; }
        code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
        p.meta { color: #64748b; font-size: 13px; margin-top: 16px; }
    </style>
</head>
<body>
<main>
    <h1>Bhatkal Mobile Hub — server status</h1>
    <div>{{ request()->getHost() }} · {{ now()->setTimezone(config('app.business_timezone'))->format('d M Y, H:i') }} IST</div>
    @if (! $installed)
        <div class="sum bad">The site hasn't been set up yet. <a href="{{ rtrim(request()->getBasePath(), '/') }}/install">Run the one-time setup →</a></div>
    @endif
    <div class="sum {{ $failed ? 'bad' : 'good' }}">{{ $failed ? "$failed problem(s) found — fix the items marked ✗ below." : 'All checks passed. The API should be working.' }}</div>
    <ul>
        @foreach ($checks as $c)
            <li class="{{ $c['ok'] ? 'ok' : 'fail' }}">
                <div class="row"><span class="icon">{{ $c['ok'] ? '✓' : '✗' }}</span><span>{{ $c['label'] }}</span></div>
                @if ($c['hint'])<div class="hint">{{ $c['hint'] }}</div>@endif
            </li>
        @endforeach
    </ul>
    <p class="meta">Run the commands over SSH in the site folder. After fixing, reload this page. Code version: {{ $version }}. Hide this page after launch with <code>STATUS_PAGE=false</code> in .env.</p>
</main>
</body>
</html>
