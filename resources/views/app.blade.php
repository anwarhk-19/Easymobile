@php($base = rtrim(request()->getBasePath(), '/'))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f6a5e">
    <meta name="description" content="Bhatkal Mobile Hub — find mobile phones, accessories and trusted repair technicians across Bhatkal.">
    {{-- Install path ("" at a domain root, "/easymobile" in a sub-folder). Read by the SPA for routing and API calls. --}}
    <meta name="app-base" content="{{ $base }}">
    <title>Bhatkal Mobile Hub</title>
    <link rel="manifest" href="{{ $base }}/manifest.webmanifest">
    <link rel="icon" href="{{ $base }}/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ $base }}/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
    <noscript>Bhatkal Mobile Hub needs JavaScript enabled.</noscript>
</body>
</html>
