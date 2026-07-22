{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kalender-/Tagesansicht des Dispositionsstatus (Feature 028, Rang 52):
  Zeilen = Mitarbeitende, Zellen = geplante Aufträge mit Status-Tone +
  SLA-Risiko-Marker; Klick öffnet den Auftrag. Kein Drag — Statuswechsel
  bleiben fachliche Aktionen am Auftrag.
--}}

@extends('layouts.app')
@section('title', __('Disposition — Kalender'))
@section('nav-title', __('Disposition — Kalender'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ __('Dispositions-Kalender') }}</x-slot:title>
            <x-slot:subtitle>{{ $from->fdate() }} – {{ $to->fdate() }} · {{ $total }} {{ __('Aufträge') }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="view_column" tone="outline" size="sm" :href="route('dispatch.board')" show-label>{{ __('Zum Board') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($capped)
        <div class="alert alert-info alert-soft text-sm">{{ __('Zeitraum auf 14 Tage gekappt — für längere Zeiträume das Board nutzen.') }}</div>
    @endif

    <x-card>
        <x-table bare>
            <x-slot:head>
                    <tr>
                        <th class="sticky left-0 z-10 bg-base-100">{{ __('Mitarbeiter:in') }}</th>
                        @foreach ($days as $day)
                            <th class="min-w-32 text-center tabular-nums">{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('dd DD.MM.') }}</th>
                        @endforeach
                    </tr>
            </x-slot:head>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="sticky left-0 z-10 bg-base-100 font-medium">{{ $row['name'] }}</td>
                            @foreach ($days as $day)
                                <td class="align-top">
                                    <div class="flex flex-col gap-1">
                                        @foreach ($row['byDay'][$day] ?? [] as $item)
                                            @php
                                                $entry = $item['entry'];
                                                $tone = $item['dispatch']->tone();
                                            @endphp
                                            <a href="{{ route('diary.show', $entry) }}"
                                               @class([
                                                   'badge badge-sm w-full justify-start gap-1 truncate',
                                                   'badge-success' => $tone === 'done',
                                                   'badge-info' => $tone === 'progress',
                                                   'badge-warning' => $tone === 'open',
                                                   'badge-ghost' => $tone === 'neutral',
                                               ])
                                               title="{{ $entry->title }} — {{ $item['dispatch']->label() }}">
                                                @if (in_array($item['sla']->value, ['atRisk', 'breached'], true))
                                                    <span class="material-symbols-outlined text-xs" aria-label="{{ __('SLA-Risiko') }}">warning</span>
                                                @endif
                                                @if ($item['hasHardConflict'])
                                                    <span class="material-symbols-outlined text-xs" aria-label="{{ __('Konflikt') }}">block</span>
                                                @endif
                                                <span class="truncate">{{ $entry->title }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-table.empty :colspan="count($days) + 1" icon='<span class="material-symbols-outlined" aria-hidden="true">event_busy</span>' :title="__('Keine terminierten Aufträge im Zeitraum.')" compact />
                    @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
