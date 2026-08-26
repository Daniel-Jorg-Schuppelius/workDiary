{{--
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Filename     : install.blade.php
 * License      : AGPL-3.0-or-later
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Installation') }} — {{ config('app.name', 'WorkDiary') }}</title>
    @if (is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Fallback, falls die Frontend-Assets noch nicht gebaut wurden (kein npm auf dem Webspace). --}}
        <style>
            :root { color-scheme: light; }
            * { box-sizing: border-box; }
            body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
            a { color: #2563eb; }
            .mx-auto { margin-left: auto; margin-right: auto; }
            .max-w-3xl { max-width: 48rem; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            .py-10 { padding-top: 2.5rem; padding-bottom: 2.5rem; }
            .mb-8 { margin-bottom: 2rem; }
            .text-center { text-align: center; }
            .text-2xl { font-size: 1.5rem; }
            .font-bold { font-weight: 700; }
            .text-sm { font-size: 0.875rem; }
            .card, .alert { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1rem; }
            .alert { background: #fef9c3; border-color: #fde047; }
            .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 0.375rem; background: #2563eb; color: #fff; text-decoration: none; border: 0; cursor: pointer; font-size: 0.9rem; }
            .btn-ghost { background: transparent; color: #2563eb; }
            .input, .select, .textarea { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.9rem; }
            label { display: block; margin: 0.5rem 0 0.25rem; font-size: 0.85rem; font-weight: 600; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 0.4rem 0.6rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
        </style>
        <div style="background:#fef9c3;border-bottom:1px solid #fde047;padding:0.6rem 1rem;text-align:center;font-size:0.85rem;">
            {{ __('Hinweis: Frontend-Assets wurden noch nicht gebaut. Der Assistent funktioniert, ist aber unformatiert. Bauen Sie die Assets lokal mit') }} <code>npm ci &amp;&amp; npm run build</code> {{ __('und laden Sie den Ordner public/build hoch.') }}
        </div>
    @endif
</head>
<body class="min-h-screen bg-base-200">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8 text-center">
            <h1 class="text-2xl font-bold">{{ config('app.name', 'WorkDiary') }} — {{ __('Installation') }}</h1>
            <p class="mt-1 text-sm text-base-content/70">{{ __('Richten Sie Ihre Anwendung in wenigen Schritten ein.') }}</p>
        </header>

        @php
            $labels = [
                'requirements' => __('Voraussetzungen'),
                'application' => __('Anwendung'),
                'database' => __('Datenbank'),
                'admin' => __('Administrator'),
                'mail' => __('E-Mail'),
                'integrations' => __('Integrationen'),
                'finish' => __('Abschluss'),
            ];
            $currentIndex = array_search($step ?? 'requirements', $steps ?? [], true);
        @endphp

        <ul class="steps steps-vertical w-full sm:steps-horizontal mb-8 text-xs sm:text-sm">
            @foreach (($steps ?? []) as $i => $key)
                <li class="step {{ $i <= $currentIndex ? 'step-primary' : '' }}" data-content="{{ $i + 1 }}">
                    <span class="px-1 whitespace-nowrap">{{ $labels[$key] ?? $key }}</span>
                </li>
            @endforeach
        </ul>

        @if (session('success'))
            <div role="status" class="alert alert-success mb-4">
                <x-icon name="check_circle" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error mb-4">
                <x-icon name="error" />
                <div>
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                @yield('install-content')
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-muted">
            {{ __('Schritt :n von :total', ['n' => $currentIndex + 1, 'total' => count($steps ?? [])]) }}
        </p>
    </div>
</body>
</html>
