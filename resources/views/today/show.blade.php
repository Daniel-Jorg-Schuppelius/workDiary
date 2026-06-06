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

    // Live-Ticker (Folgeproblem: ohne JS bleiben Anwesenheit/Saldo/Progress
    // bei laufender Stempelung auf dem Render-Zeitpunkt eingefroren).
    $isLive = $current !== null && $current->started_at !== null;
    $renderedAt = now()->toIso8601String();
    $currentStartedAt = $isLive ? $current->started_at->toIso8601String() : null;
@endphp

@section('content')
    <x-page-shell
        x-data="{
            isLive: {{ $isLive ? 'true' : 'false' }},
            baseAttendance: {{ $attendanceMinutes }},
            entriesMin: {{ $entriesMinutes }},
            target: {{ $targetMinutes }},
            renderedAt: new Date('{{ $renderedAt }}').getTime(),
            now: Date.now(),
            get extraMinutes() {
                if (! this.isLive) return 0;
                return Math.max(0, Math.floor((this.now - this.renderedAt) / 60000));
            },
            get attendanceMin() { return this.baseAttendance + this.extraMinutes; },
            get untrackedMin() { return Math.max(0, this.attendanceMin - this.entriesMin); },
            get balance() { return this.attendanceMin - this.target; },
            get progress() {
                return this.target > 0
                    ? Math.min(100, Math.round(this.attendanceMin / this.target * 100))
                    : 0;
            },
            fmt(m) {
                var sign = m < 0 ? '-' : '';
                var abs = Math.abs(m);
                return sign + Math.floor(abs / 60) + ':' + String(abs % 60).padStart(2, '0') + ' h';
            },
        }"
        x-init="if (isLive) { setInterval(() => { now = Date.now(); }, 1000); }">
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

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-kpi-tile :label="__('Soll')" :value="$fmt($targetMinutes)" />

            {{-- Anwesenheit-Tile: tickert live mit dem Header-Stempel-Timer mit. --}}
            <div class="rounded-box border border-success/40 bg-base-100 px-4 py-3 shadow-xs">
                <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ __('Anwesenheit') }}</p>
                <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-success"
                   x-text="fmt(attendanceMin)">{{ $fmt($attendanceMinutes) }}</p>
            </div>

            <x-kpi-tile :label="__('Erfasst')" :value="$fmt($entriesMinutes)" tone="info" />

            {{-- Unverteilt-Tile: leitet sich aus Anwesenheit ab, also auch live. --}}
            <div class="rounded-box border bg-base-100 px-4 py-3 shadow-xs"
                 :class="untrackedMin > 0 ? 'border-warning/40' : 'border-base-300'">
                <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ __('Unverteilt') }}</p>
                <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold"
                   :class="untrackedMin > 0 ? 'text-warning' : 'text-base-content'"
                   x-text="fmt(untrackedMin)">{{ $fmt($untrackedMinutes) }}</p>
            </div>

            @include('attendances._panel', ['current' => $current])
        </section>

        <x-card as="section">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-widest text-base-content/60">{{ __('Tagesfortschritt') }}</h2>
                <span class="text-xs text-base-content/60">
                    <span x-text="fmt(attendanceMin)">{{ $fmt($attendanceMinutes) }}</span>
                    / {{ $fmt($targetMinutes) }}
                    (<span x-text="progress">{{ $progress }}</span>%)
                    · {{ __('Saldo') }}:
                    <strong :class="balance >= 0 ? 'text-success' : 'text-error'"
                            x-text="fmt(balance)">{{ $fmt($balance) }}</strong>
                </span>
            </div>
            <progress class="progress mt-2 w-full"
                      :class="balance >= 0 ? 'progress-success' : 'progress-warning'"
                      :value="progress"
                      max="100"
                      value="{{ $progress }}"></progress>
        </x-card>

        @if ($byActivity->isNotEmpty())
            <x-card as="section">
                <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Aufteilung nach Tätigkeit') }}</h2>
                <ul class="mt-2 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($byActivity as $key => $info)
                        <li class="flex items-center justify-between gap-2 rounded-box bg-base-200/70 px-3 py-2">
                            <span>{{ \App\Enums\TimeEntry\TimeEntryActivityType::tryFrom((string) $key)?->label() ?? (string) $key }}</span>
                            <span class="tabular-nums text-base-content/70">{{ $fmt($info['minutes']) }} <span class="text-xs text-base-content/50">({{ $info['count'] }})</span></span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card as="section" padding="p-0" class="overflow-hidden">
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
                        <td class="tabular-nums">{{ $a->started_at?->ftime() }}</td>
                        <td class="tabular-nums">{{ $a->ended_at?->ftime() ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) ($a->duration_minutes ?? 0) }}">{{ $fmt((int) ($a->duration_minutes ?? 0)) }}</td>
                        <td><x-status-badge :tone="$a->isOpen() ? 'success' : 'ghost'">{{ $a->statusLabel() }}</x-status-badge></td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5"
                                   icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>'
                                   :title="__('Noch keine Stempelung')"
                                   :message="__('Für diesen Tag ist noch keine Stempelung erfasst.')" />
                @endforelse
            </x-table>
        </x-card>

        <x-card as="section" padding="p-0" class="overflow-hidden">
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
                                {{ $e->started_at->ftime() }}–{{ $e->ended_at?->ftime() ?? '…' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <x-status-badge tone="ghost">{{ $e->activity_type?->label() ?? '—' }}</x-status-badge>
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
        </x-card>
    </x-page-shell>
@endsection
