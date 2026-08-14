{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dashboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">{{ __('Willkommen') }}, {{ $user->name }}</h1>
    <p class="text-sm text-base-content/70 mb-6">{{ $customer?->name }}</p>

    {{-- Kacheln nur für freigegebene Bereiche (MVP-511); ohne Freigaben ein
         erklärter Leerzustand statt automatischer Vollsicht. --}}
    @if ($stats === [])
        <div class="rounded-box border border-base-300 bg-base-100 p-8 text-center">
            <span class="material-symbols-outlined mb-2 text-4xl text-base-content/40">visibility_off</span>
            <p class="font-medium">{{ __('Für Ihren Zugang sind noch keine Bereiche freigegeben.') }}</p>
            <p class="mt-1 text-sm text-base-content/60">{{ __('Bitte wenden Sie sich an Ihre Ansprechperson, um Inhalte freischalten zu lassen.') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @isset($stats['diary'])
                <a href="{{ route('customer.diary.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined">menu_book</span>
                        <span class="text-2xl font-semibold">{{ $stats['diary'] }}</span>
                    </div>
                    <div class="mt-2 text-sm">{{ __('Auftragsbuch-Einträge') }}</div>
                </a>
            @endisset
            @isset($stats['time_entries'])
                <a href="{{ route('customer.time-entries.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined">schedule</span>
                        <span class="text-2xl font-semibold">{{ $stats['time_entries'] }}</span>
                    </div>
                    <div class="mt-2 text-sm">{{ __('Zeiterfassungen') }}</div>
                </a>
            @endisset
            @isset($stats['invoices'])
                <a href="{{ route('customer.invoices.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <span class="text-2xl font-semibold">{{ $stats['invoices'] }}</span>
                    </div>
                    <div class="mt-2 text-sm">{{ __('Rechnungen') }}</div>
                </a>
            @endisset
            @isset($stats['open_issues'])
                <a href="{{ route('customer.open-issues.index') }}" class="bg-base-100 border border-base-300 rounded p-4 hover:border-primary">
                    <div class="flex items-center justify-between">
                        <span class="material-symbols-outlined">flag</span>
                        <span class="text-2xl font-semibold">{{ $stats['open_issues'] }}</span>
                    </div>
                    <div class="mt-2 text-sm">{{ __('open-issue.title.index') }}</div>
                </a>
            @endisset
        </div>
    @endif
@endsection
