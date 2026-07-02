<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>@yield('title', 'Login') - {{ config('app.name') }} ERP</title>
    @vite('resources/css/erp.css')
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon">S</span><span>{{ config('app.name') }} Construction ERP</span></div>
        <h1 class="auth-title">@yield('heading')</h1>
        <div class="auth-subtitle">@yield('subheading')</div>

        @if (session('status'))
            <div class="alert success" style="margin-bottom:14px">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert" style="margin-bottom:14px">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </div>
</div>
</body>
</html>
