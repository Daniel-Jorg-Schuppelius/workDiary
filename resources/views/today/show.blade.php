@extends('layouts.app')
@section('title', __('Heute') . ' — WorkDiary')
@section('nav-title', __('Heute'))

@php
    /** @var \Carbon\CarbonInterface $day */
    /** @var \App\Models\Attendance|null $current */
    /** @var \Illuminate\Support\Collection $attendances */
    /** @var \Illuminate\Support\Collection $entries */
    /** @var int $targetMinutes */
    /** @var int $attendanceMinutes */
    /** @var int $entriesMinutes */
    /** @var int $untrackedMinutes */
    /** @var \Illuminate\Support\Collection $byActivity */

    $fmt = function (int $m): string {
        $sign = $m < 0 ? '-' : '';
        $m = abs($m);
        return sprintf('%s%d:%02d h', $sign, intdiv($m, 60), $m % 60);
    };
    $balance = $attendanceMinutes - $targetMinutes;
    $progress = $targetMinutes > 0 ? min(100, (int) round($attendanceMinutes / $targetMinutes * 100)) : 0;
@endphp

@section('content')
    <x-page-shell gap="6">
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="$day->translatedFormat('l, d.m.Y')">
                <x-slot:actions>
                    <x-icon-btn icon="arrow_back" size="sm"
                                :href="route('today.show', ['date' => $day->copy()->subDay()->toDateString()])"
                                show-label>{{ __('Vortag') }}</x-icon-btn>
                    <x-icon-btn icon="today" size="sm"
                                :href="route('today.show')"
                                show-label>{{ __('Heute') }}</x-icon-btn>
                    <x-icon-btn icon="arrow_forward" size="sm"
                                :href="route('today.show', ['date' => $day->copy()->addDay()->toDateString()])"
                                show-label>{{ __('Folgetag') }}</x-icon-btn>
                    <x-icon-btn icon="badge" size="sm"
                                :href="route('attendance.index')"
                                show-label>{{ __('Stempelungen') }}</x-icon-btn>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('admin-time-entries.create', ['date' => $day->toDateString()])"
                                show-label>{{ __('Verwaltungszeit') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('Soll')"        :value="$fmt($targetMinutes)" />
            <x-kpi-tile :label="__('Anwesenheit')" :value="$fmt($attendanceMinutes)" tone="success" />
            <x-kpi-tile :label="__('Erfasst')"     :value="$fmt($entriesMinutes)"    tone="info" />
            <x-kpi-tile :label="__('Unverteilt')"  :value="$fmt($untrackedMinutes)"  :tone="$untrackedMinutes > 0 ? 'warning' : 'neutral'" />
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-widest text-base-content/60">{{ __('Tagesfortschritt') }}</h2>
                <span class="text-xs text-base-content/60">{{ $fmt($attendanceMinutes) }} / {{ $fmt($targetMinutes) }} ({{ $progress }}%) · {{ __('Saldo') }}: <strong class="{{ $balance >= 0 ? 'text-success' : 'text-error' }}">{{ $fmt($balance) }}</strong></span>
            </div>
            <progress class="progress {{ $balance >= 0 ? 'progress-success' : 'progress-warning' }} mt-2 w-full" value="{{ $progress }}" max="100"></progress>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-1 space-y-4">
                @include('attendances._panel', ['current' => $current])

                @if ($byActivity->isNotEmpty())
                    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Aufteilung nach Tätigkeit') }}</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($byActivity as $key => $info)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ \App\Enums\TimeEntry\TimeEntryActivityType::tryFrom((string) $key)?->label() ?? (string) $key }}</span>
                                    <span class="tabular-nums text-base-content/70">{{ $fmt($info['minutes']) }} <span class="text-xs text-base-content/50">({{ $info['count'] }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-2 space-y-4">
                <section class="overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <header class="flex items-center justify-between gap-2 border-b border-base-300 p-3">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stempelungen') }}</h2>
                        <span class="text-xs text-base-content/60">{{ $attendances->count() }}</span>
                    </header>
                    <x-table table-sort="client" bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th sort type="string">{{ __('Beginn') }}</x-table.th>
                                <x-table.th sort type="string">{{ __('Ende') }}</x-table.th>
                                <x-table.th sort type="number" align="right">{{ __('Pause') }}</x-table.th>
                                <x-table.th sort type="duration" align="right">{{ __('Dauer') }}</x-table.th>
                                <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($attendances as $a)
                            <tr>
                                <td class="tabular-nums">{{ optional($a->started_at)->format('H:i') }}</td>
                                <td class="tabular-nums">{{ optional($a->ended_at)->format('H:i') ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                                <td class="text-right tabular-nums" data-sort-value="{{ (int) ($a->duration_minutes ?? 0) }}">{{ $fmt((int) ($a->duration_minutes ?? 0)) }}</td>
                                <td><span class="badge badge-sm {{ $a->isOpen() ? 'badge-success' : 'badge-ghost' }}">{{ $a->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="5"
                                           icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>'
                                           :title="__('Noch keine Stempelung')"
                                           :message="__('Für diesen Tag ist noch keine Stempelung erfasst.')" />
                        @endforelse
                    </x-table>
                </section>

                <section class="overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <header class="flex items-center justify-between gap-2 border-b border-base-300 p-3">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</h2>
                        <span class="text-xs text-base-content/60">{{ $entries->count() }}</span>
                    </header>
                    <x-table table-sort="client" bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th sort type="string">{{ __('Zeit') }}</x-table.th>
                                <x-table.th sort type="string">{{ __('Tätigkeit') }}</x-table.th>
                                <x-table.th sort type="string">{{ __('Projekt / Beschreibung') }}</x-table.th>
                                <x-table.th sort type="number" align="right">{{ __('Min.') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($entries as $e)
                            <tr>
                                <td class="tabular-nums text-xs">
                                    @if ($e->started_at)
                                        {{ $e->started_at->format('H:i') }}–{{ optional($e->ended_at)->format('H:i') ?? '…' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-sm badge-ghost">{{ $e->activity_type?->label() ?? '—' }}</span>
                                </td>
                                <td class="text-sm">
                                    @if ($e->project)
                                        <span class="font-medium">{{ $e->project->name }}</span>
                                    @elseif ($e->activityCategory)
                                        <span class="text-base-content/70">{{ $e->activityCategory->label }}</span>
                                    @endif
                                    @if ($e->description)
                                        <span class="block text-xs text-base-content/60">{{ \Illuminate\Support\Str::limit($e->description, 80) }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $e->minutes }}</td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="4"
                                           icon='<span class="material-symbols-outlined" aria-hidden="true">edit_note</span>'
                                           :title="__('Noch keine Einträge')"
                                           :message="__('Für diesen Tag wurden noch keine Zeiteinträge erfasst.')" />
                        @endforelse
                    </x-table>
                </section>
            </div>
        </div>
    </x-page-shell>
@endsection
