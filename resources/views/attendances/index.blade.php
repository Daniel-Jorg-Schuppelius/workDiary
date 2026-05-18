@extends('layouts.app')
@section('title', __('Stempelungen') . ' — WorkDiary')
@section('nav-title', __('Stempelungen'))

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $attendances */
        /** @var \App\Models\Attendance|null $current */
        /** @var \Carbon\CarbonInterface $from */
        /** @var \Carbon\CarbonInterface $to */
    @endphp

    <x-page-shell gap="6">
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <a href="{{ route('today.show') }}" class="btn btn-ghost btn-sm">
                        <x-icon name="today" /> {{ __('Heute-Übersicht') }}
                    </a>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('attendance.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['from' => $from->toDateString(), 'to' => $to->toDateString()]"
                     bare>
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
                        <td class="tabular-nums">{{ optional($a->started_at)->format('H:i') }}</td>
                        <td class="tabular-nums">{{ optional($a->ended_at)->format('H:i') ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                        <td class="text-right tabular-nums">
                            @if ($a->duration_minutes !== null)
                                {{ sprintf('%d:%02d', intdiv($a->duration_minutes, 60), $a->duration_minutes % 60) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-sm {{ $a->isOpen() ? 'badge-success' : 'badge-ghost' }}">{{ $a->statusLabel() }}</span>
                        </td>
                        <td class="text-xs text-base-content/60">{{ $a->sourceLabel() }}</td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>' :colspan="7" :title="__('Keine Stempelungen im Zeitraum')" compact />
                @endforelse
            </x-table>
            @if ($attendances->hasPages())
                <div class="p-3">{{ $attendances->links() }}</div>
            @endif
        </x-card>
    </x-page-shell>
@endsection
