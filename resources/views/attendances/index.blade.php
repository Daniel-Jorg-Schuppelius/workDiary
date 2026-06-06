@extends('layouts.app')
@section('title', __('Stempelungen') . ' — WorkDiary')
@section('nav-title', __('Stempelungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $attendances */
        /** @var \App\Models\Attendance|null $current */
        /** @var \Carbon\CarbonInterface $from */
        /** @var \Carbon\CarbonInterface $to */
    @endphp

    <x-index-page overflow="clip" :subtitle="__('Stempelungen und Anwesenheiten der Mitarbeiter einsehen.')">
        <x-slot:actions>
            <x-icon-btn icon="today" size="sm"
                        :href="route('today.show')"
                        show-label>{{ __('Heute-Übersicht') }}</x-icon-btn>
        </x-slot:actions>

        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table table-sort="server"
                     :route="route('attendance.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['from' => $from->toDateString(), 'to' => $to->toDateString()]"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="date">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort="started_at" default>{{ __('Beginn') }}</x-table.th>
                        <x-table.th sort="ended_at">{{ __('Ende') }}</x-table.th>
                        <th class="text-right">{{ __('Pause') }}</th>
                        <x-table.th sort="duration" align="right">{{ __('Dauer') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <x-table.th sort="source">{{ __('Quelle') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($attendances as $a)
                    <tr>
                        <td>{{ optional($a->date)->format('d.m.Y') }}</td>
                        <td class="tabular-nums">{{ $a->started_at?->orgTz()->format('H:i') }}</td>
                        <td class="tabular-nums">{{ $a->ended_at?->orgTz()->format('H:i') ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                        <td class="text-right tabular-nums">
                            @if ($a->duration_minutes !== null)
                                {{ sprintf('%d:%02d', intdiv($a->duration_minutes, 60), $a->duration_minutes % 60) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <x-status-badge size="sm" :tone="$a->isOpen() ? 'success' : 'ghost'">{{ $a->statusLabel() }}</x-status-badge>
                        </td>
                        <td class="text-xs text-base-content/60">{{ $a->sourceLabel() }}</td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>' :colspan="7" :title="__('Keine Stempelungen im Zeitraum')" compact />
                @endforelse
            </x-table>
            <x-pagination :paginator="$attendances" :framed="false" />
        </x-card>
    </x-index-page>
@endsection
