{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : drilldown.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Drilldown (Feature 065, MVP-159): Tickets hinter einem Diagramm-Punkt —
     nur über signierten Link erreichbar (Muster 064/P11). Sichtbare
     Summen-Konsistenz: Trefferzahl gegen den Kennzahlwert des Punktes.
     Liste org-gescopt + paginiert (stehendes Footer-Panel). --}}

@extends('layouts.app')

@section('title', $title)
@section('nav-title', __('Drilldown'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $title }}</x-slot:title>
        <x-slot:subtitle>{{ __('Helpdesk-Bericht') }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('helpdesk.reports.index')" show-label>{{ __('Zum Helpdesk-Bericht') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @unless ($consistent)
        <div class="alert alert-warning">
            <x-icon name="warning" />
            <span>{{ __('Konsistenz-Hinweis: Der Datenpunkt meldet :expected, der Drilldown findet :actual Datensätze (Datenstand kann sich geändert haben).', ['expected' => $expected, 'actual' => $rows->total()]) }}</span>
        </div>
    @endunless

    <x-card>
        @if ($rows->total() === 0)
            <x-empty-state icon="search_off" :title="__('Keine Datensätze zu diesem Punkt.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Ticket') }}</th>
                        <th>{{ __('Queue') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Detail') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            @if ($row['ticket'] !== null)
                                <a href="{{ route('service-tickets.show', $row['ticket']) }}" class="link">{{ $row['ticket']->ticket_no }}</a>
                                <span class="text-base-content/60">{{ \Illuminate\Support\Str::limit($row['ticket']->title, 60, '…') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm text-base-content/60">{{ $row['ticket']?->queue?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $row['ticket']?->status?->label() ?? '—' }}</td>
                        <td class="text-sm text-base-content/60">{{ $row['at'] ?? '—' }}</td>
                        <td class="text-sm">{{ $row['detail'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-base-content/60">{{ __(':count Datensätze insgesamt.', ['count' => $rows->total()]) }}</p>
        @endif
    </x-card>

    <x-pagination :paginator="$rows" standing />
</x-page-shell>
@endsection
