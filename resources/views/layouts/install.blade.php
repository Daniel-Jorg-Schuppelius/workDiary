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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
    <div class="mx-auto max-w-3xl px-4 py-10">
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

        <ul class="steps steps-vertical w-full sm:steps-horizontal mb-8">
            @foreach (($steps ?? []) as $i => $key)
                <li class="step {{ $i <= $currentIndex ? 'step-primary' : '' }}">{{ $labels[$key] ?? $key }}</li>
            @endforeach
        </ul>

        @if (session('success'))
            <div class="alert alert-success mb-4">
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error mb-4">
                <span class="material-symbols-outlined" aria-hidden="true">error</span>
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

        <p class="mt-6 text-center text-xs text-base-content/50">
            {{ __('Schritt :n von :total', ['n' => $currentIndex + 1, 'total' => count($steps ?? [])]) }}
        </p>
    </div>
</body>
</html>
