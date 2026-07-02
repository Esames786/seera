<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }} ERP</title>
    @vite('resources/css/erp.css')
</head>
<body>
<div class="layout">
    <x-admin.sidebar/>

    <main class="content">
        <x-admin.topbar>@yield('breadcrumb')</x-admin.topbar>

        <section class="page">
            @if (session('status'))
                <div class="alert success flash">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert flash">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </section>
    </main>
</div>

<x-admin.delete-modal/>

<script>
    (function () {
        const overlay = document.getElementById('delete-modal');
        if (!overlay) return;

        const form = overlay.querySelector('form');
        const message = overlay.querySelector('[data-modal-message]');

        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('.js-delete');
            if (trigger) {
                event.preventDefault();
                form.action = trigger.dataset.deleteUrl;
                message.textContent = 'Are you sure you want to delete "' + (trigger.dataset.deleteName || 'this record') + '"?';
                overlay.classList.add('open');
                return;
            }
            if (event.target.closest('.js-modal-close') || event.target === overlay) {
                overlay.classList.remove('open');
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
