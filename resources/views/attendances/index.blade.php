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
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Beginn') }}</th>
                            <th>{{ __('Ende') }}</th>
                            <th class="text-right">{{ __('Pause') }}</th>
                            <th class="text-right">{{ __('Dauer') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Quelle') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            <tr><td colspan="7" class="p-0"><x-empty-state :compact="true" :title="__('Keine Stempelungen im Zeitraum')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $attendances->links() }}</div>
        </x-card>
    </x-page-shell>
@endsection
