{{--
  Beleg-Drilldown der Nachkalkulation (Feature 014, Rang 59c): Zeiteinträge
  einer Report-Zelle (Nacharbeit/Kulanz) — Zugriff nur über signierten Link
  plus Report-Recht. Die Fußzeilen-Summe entspricht dem Zellenwert.
--}}

@extends('layouts.app')
@section('title', __('Nachkalkulation — Belege'))
@section('nav-title', __('Nachkalkulation — Belege'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $kind === 'rework' ? __('Nacharbeit — Belege') : __('Kulanz — Belege') }}</x-slot:title>
        <x-slot:subtitle>{{ $project->name }} · {{ $from }} – {{ $to }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('reports.economics')" show-label>{{ __('Zum Report') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        @if ($entries->isEmpty())
            <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <th>{{ __('Grund') }}</th>
                        <th>{{ __('Beschreibung') }}</th>
                        <th class="text-right">{{ __('Minuten') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($entries as $entry)
                    <tr>
                        <td class="tabular-nums">{{ $entry->date?->fdate() }}</td>
                        <td>{{ $entry->user->name ?? '—' }}</td>
                        <td>{{ ($kind === 'rework' ? $entry->reworkReason?->label : $entry->goodwillReason?->label) ?? '—' }}</td>
                        <td class="max-w-md truncate text-sm">{{ $entry->description ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $entry->minutes }}</td>
                    </tr>
                @endforeach
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="4">{{ __('Summe') }}</td>
                        <td class="text-right tabular-nums">{{ $totalMinutes }}</td>
                    </tr>
                </tfoot>
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
