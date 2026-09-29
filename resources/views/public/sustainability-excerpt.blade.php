{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sustainability-excerpt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Öffentlicher Nachhaltigkeitsauszug (MVP-930). Erwartet: $orgName, $snapshot, $targets, $statement, $offsets --}}
@php
    $data = (array) $snapshot->data;
    $t = static fn (float $kg): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kg / 1000, 1, withThousandsSeparator: true);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('sustainability.excerpt.public_title', ['org' => $orgName]) }}</title>
@include('partials.font-bootstrap', ['icons' => false])
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="max-w-2xl mx-auto p-4 flex flex-col gap-4">
    <x-card class="flex flex-col gap-1">
        <h1 class="card-title">{{ __('sustainability.excerpt.public_title', ['org' => $orgName]) }}</h1>
        <p class="text-sm opacity-70">{{ __('sustainability.excerpt.period', ['from' => $snapshot->period_start->format('d.m.Y'), 'to' => $snapshot->period_end->format('d.m.Y')]) }}</p>
    </x-card>
    <x-card :title="__('sustainability.excerpt.emissions')">
        <p class="text-3xl font-semibold tabular-nums">{{ $t((float) ($data['co2e_total_kg'] ?? 0)) }} t CO₂e</p>
        <ul class="mt-2 text-sm">
            @foreach ((array) ($data['co2e_by_scope'] ?? []) as $scope => $kg)
                <li>{{ __('sustainability.excerpt.scope', ['scope' => $scope]) }}: <span class="tabular-nums">{{ $t((float) $kg) }} t</span></li>
            @endforeach
        </ul>
    </x-card>
    @if ($statement !== null)
        <x-card><p class="whitespace-pre-line text-sm">{{ $statement }}</p></x-card>
    @endif
    {{-- Nachweise (MVP-961): getrennt ausgewiesen, nicht mit den Emissionen verrechnet. --}}
    @if ($offsets !== [])
        <x-card :title="__('sustainability.offset.public_title')">
            <ul class="text-sm">
                @foreach ($offsets as $offset)
                    <li>{{ $offset->claim_year }} · {{ $offset->kind->label() }} · {{ $offset->provider }}@if ($offset->standard) ({{ $offset->standard }})@endif: <span class="tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $offset->quantity_t, 3, withThousandsSeparator: true, trimTrailingZeros: true) }} t</span></li>
                @endforeach
            </ul>
            <p class="mt-1 text-xs opacity-70">{{ __('sustainability.offset.separate') }}</p>
        </x-card>
    @endif
    @if ($targets !== [])
        <x-card :title="__('sustainability.excerpt.targets')">
            <ul class="text-sm">
                @foreach ($targets as $target)
                    <li>{{ $target->label }}: {{ $target->baseline_value }} {{ $target->unit }} ({{ $target->baseline_year }}) → {{ $target->target_value }} {{ $target->unit }} ({{ $target->target_year }})</li>
                @endforeach
            </ul>
        </x-card>
    @endif
    <p class="text-xs opacity-70">{{ __('sustainability.excerpt.disclaimer') }}@if (! empty($data['methodology']['factor_sets'])) {{ __('sustainability.excerpt.factors', ['sets' => implode(', ', (array) $data['methodology']['factor_sets'])]) }}@endif</p>
</main>
</body>
</html>
