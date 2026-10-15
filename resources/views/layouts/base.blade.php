<!DOCTYPE html>
<html lang="en" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d9488">
    <title>@yield('title', config('app.name'))</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    {{-- Apply the saved theme before first paint (no flash) --}}
    <script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
</head>
<body class="@yield('body-class')">
    @yield('body')
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
