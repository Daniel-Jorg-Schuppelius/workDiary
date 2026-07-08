{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : drilldown.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Drilldown (Feature 064, P11): Datensätze hinter einem Diagramm-Punkt —
     nur über signierten Link erreichbar. Sichtbare Summen-Konsistenz:
     Trefferzahl gegen den Kennzahlwert des Punktes. --}}

@extends('layouts.app')

@section('title', $title . ' — ' . $project->name)
@section('nav-title', __('Drilldown'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $title }}</x-slot:title>
        <x-slot:subtitle>{{ $project->name }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('agile.reports.flow', $project)" show-label>{{ __('Zum Fluss-Bericht') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @unless ($consistent)
        <div class="alert alert-warning">
            <x-icon name="warning" />
            <span>{{ __('Konsistenz-Hinweis: Der Datenpunkt meldet :expected, der Drilldown findet :actual Datensätze (Datenstand kann sich geändert haben).', ['expected' => $expected, 'actual' => count($rows)]) }}</span>
        </div>
    @endunless

    <x-card>
        @if (count($rows) === 0)
            <x-empty-state icon="search_off" :title="__('Keine Datensätze zu diesem Punkt.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Element') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Detail') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['title'] ?? '—' }}</td>
                        <td class="text-sm text-base-content/60">{{ $row['at'] }}</td>
                        <td class="text-sm">{{ $row['detail'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-base-content/60">{{ __(':count Datensätze — entspricht dem Kennzahlwert.', ['count' => count($rows)]) }}</p>
        @endif
    </x-card>
</x-page-shell>
@endsection
