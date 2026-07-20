@extends('layouts.app')
@section('title', __('Externe Auszahlungen'))
@section('nav-title', __('Externe Auszahlungen'))

@section('content')
@php
    $money = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp

<x-page-shell>
    <x-slot:toolbar>
        {{-- Kein eigenes Zeitraum-Element hier: der Header blendet den zentrierten
             Zeitraum automatisch ein (wie bei allen anderen Reports). Ein zweites
             hier wäre doppelt. --}}
        <x-page-toolbar :subtitle="__('An externe Mitarbeiter zu zahlende Beträge im gewählten Zeitraum (:from – :to).', ['from' => $from->fdate(), 'to' => $to->fdate()])" />
    </x-slot:toolbar>

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-end gap-2">
            <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ __('Summe') }}</span>
            <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $total > 0 ? 'text-primary' : 'text-base-content/50' }}">{{ $money($total) }}</span>
        </div>

        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">payments</span>'
                           :title="__('Keine externen Mitarbeiter')"
                           :message="__('Es sind keine Mitarbeiter mit pauschaler oder zeitbasierter Vergütung hinterlegt.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Modell') }}</th>
                        <th>{{ __('Berechnungsbasis') }}</th>
                        <th class="text-right">{{ __('Betrag') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td colspan="3">{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $money($total) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">{{ $row['user']->name }}</td>
                        <td>
                            <span class="badge badge-sm {{ $row['model'] === \App\Enums\User\CompensationModel::NachZeitaufwand ? 'badge-info' : 'badge-warning' }}">
                                {{ $row['model']?->label() }}
                            </span>
                        </td>
                        <td class="text-sm text-base-content/70">{{ $row['basis'] }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        <p class="mt-4 text-xs text-base-content/50">
            {{ __('Pauschalen je Intervall (monatlich × Monate, pro Einsatz × Einsatztage, einmalig) und zeitbasierte Vergütung (erfasste Zeit × Stundensatz). Brutto, ohne Steuer/SV.') }}
        </p>
    </x-card>
</x-page-shell>
@endsection
