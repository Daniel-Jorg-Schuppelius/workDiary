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
    <div class="w-full space-y-6 px-4 py-4 xl:px-8">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-base-content/60">{{ $day->translatedFormat('l, d.m.Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin-time-entries.create', ['date' => $day->toDateString()]) }}" data-entry-modal-trigger class="btn btn-sm btn-primary"><x-icon name="add" /> {{ __('Verwaltungszeit') }}</a>
                <a href="{{ route('today.show', ['date' => $day->copy()->subDay()->toDateString()]) }}" class="btn btn-sm btn-ghost">← {{ __('Vortag') }}</a>
                <a href="{{ route('today.show') }}" class="btn btn-sm btn-ghost">{{ __('Heute') }}</a>
                <a href="{{ route('today.show', ['date' => $day->copy()->addDay()->toDateString()]) }}" class="btn btn-sm btn-ghost">{{ __('Folgetag') }} →</a>
                <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-ghost"><x-icon name="badge" /> {{ __('Stempelungen') }}</a>
            </div>
        </div>

        {{-- Soll / Ist / Saldo --}}
        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Soll') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold tabular-nums">{{ $fmt($targetMinutes) }}</p>
            </div>
            <div class="rounded-box border border-success/40 bg-success/5 p-4">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Anwesenheit') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold tabular-nums">{{ $fmt($attendanceMinutes) }}</p>
            </div>
            <div class="rounded-box border border-info/40 bg-info/5 p-4">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Erfasst') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold tabular-nums">{{ $fmt($entriesMinutes) }}</p>
            </div>
            <div class="rounded-box border {{ $untrackedMinutes > 0 ? 'border-warning/40 bg-warning/5' : 'border-base-300 bg-base-100' }} p-4">
                <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Unverteilt') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-3xl font-bold tabular-nums">{{ $fmt($untrackedMinutes) }}</p>
            </div>
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
                                    <span class="capitalize">{{ $key ?: __('unbekannt') }}</span>
                                    <span class="tabular-nums text-base-content/70">{{ $fmt($info['minutes']) }} <span class="text-xs text-base-content/50">({{ $info['count'] }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-2 space-y-4">
                <section class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <header class="flex items-center justify-between gap-2 border-b border-base-300 p-3">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stempelungen') }}</h2>
                        <span class="text-xs text-base-content/60">{{ $attendances->count() }}</span>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>{{ __('Beginn') }}</th><th>{{ __('Ende') }}</th><th class="text-right">{{ __('Pause') }}</th><th class="text-right">{{ __('Dauer') }}</th><th>{{ __('Status') }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($attendances as $a)
                                    <tr>
                                        <td class="tabular-nums">{{ optional($a->started_at)->format('H:i') }}</td>
                                        <td class="tabular-nums">{{ optional($a->ended_at)->format('H:i') ?? '—' }}</td>
                                        <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                                        <td class="text-right tabular-nums">{{ $fmt((int) ($a->duration_minutes ?? 0)) }}</td>
                                        <td><span class="badge badge-sm {{ $a->isOpen() ? 'badge-success' : 'badge-ghost' }}">{{ $a->statusLabel() }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-4 text-center text-sm text-base-content/60">{{ __('Noch keine Stempelung heute.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <header class="flex items-center justify-between gap-2 border-b border-base-300 p-3">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</h2>
                        <span class="text-xs text-base-content/60">{{ $entries->count() }}</span>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>{{ __('Zeit') }}</th><th>{{ __('Tätigkeit') }}</th><th>{{ __('Projekt / Beschreibung') }}</th><th class="text-right">{{ __('Min.') }}</th></tr>
                            </thead>
                            <tbody>
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
                                            <span class="badge badge-sm badge-ghost capitalize">{{ $e->activity_type ?? '—' }}</span>
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
                                    <tr><td colspan="4" class="py-4 text-center text-sm text-base-content/60">{{ __('Noch keine Einträge heute.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
