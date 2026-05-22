@php
    /** @var \App\Models\User|null $portalUser */
    $portalUser = auth('customer')->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Customer-Portal') }} | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-base-200 min-h-screen text-base-content">
    <header class="bg-base-100 border-b border-base-300">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="material-symbols-outlined">support_agent</span>
                <span>{{ __('Customer-Portal') }}</span>
            </a>
            @if ($portalUser)
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('customer.dashboard') }}" class="hover:underline">{{ __('Übersicht') }}</a>
                    <a href="{{ route('customer.diary.index') }}" class="hover:underline">{{ __('Tagebuch') }}</a>
                    <a href="{{ route('customer.time-entries.index') }}" class="hover:underline">{{ __('Zeiten') }}</a>
                    <a href="{{ route('customer.invoices.index') }}" class="hover:underline">{{ __('Rechnungen') }}</a>
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <span class="material-symbols-outlined text-base">logout</span>
                            <span>{{ __('Abmelden') }}</span>
                        </button>
                    </form>
                </nav>
            @endif
        </div>
    </header>
    <main class="max-w-5xl mx-auto px-4 py-6">
        @if (session('status'))
            <div class="alert alert-info mb-4">{{ session('status') }}</div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>
