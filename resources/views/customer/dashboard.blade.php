@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">{{ __('Willkommen') }}, {{ $user->name }}</h1>
    <p class="text-sm text-base-content/70 mb-6">{{ $customer?->name }}</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('customer.diary.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
            <div class="flex items-center justify-between">
                <span class="material-symbols-outlined">menu_book</span>
                <span class="text-2xl font-semibold">{{ $stats['diary'] }}</span>
            </div>
            <div class="mt-2 text-sm">{{ __('Auftragsbuch-Einträge') }}</div>
        </a>
        <a href="{{ route('customer.time-entries.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
            <div class="flex items-center justify-between">
                <span class="material-symbols-outlined">schedule</span>
                <span class="text-2xl font-semibold">{{ $stats['time_entries'] }}</span>
            </div>
            <div class="mt-2 text-sm">{{ __('Zeiterfassungen') }}</div>
        </a>
        <a href="{{ route('customer.invoices.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
            <div class="flex items-center justify-between">
                <span class="material-symbols-outlined">receipt_long</span>
                <span class="text-2xl font-semibold">{{ $stats['invoices'] }}</span>
            </div>
            <div class="mt-2 text-sm">{{ __('Rechnungen') }}</div>
        </a>
        <a href="{{ route('customer.open-issues.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
            <div class="flex items-center justify-between">
                <span class="material-symbols-outlined">flag</span>
                <span class="text-2xl font-semibold">{{ $stats['open_issues'] }}</span>
            </div>
            <div class="mt-2 text-sm">{{ __('open-issue.title.index') }}</div>
        </a>
    </div>
@endsection
