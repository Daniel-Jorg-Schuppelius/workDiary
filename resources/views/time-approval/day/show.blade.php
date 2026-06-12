{{--
  Created on   : Fri Jun 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Tagesabschluss (MVP-015, docs/tagesabschluss.md §2/§8):
  EINE Seite pro Mitarbeitendem mit den Sektionen
  A) Anwesenheit  B) Pausen  C) Auftrags-/Projektzeiten
  D) Lücken & Warnungen  E) Bilanz  F) Aktionen (sticky auf Mobile).
  Modals laufen über <x-modal :embedded="false"> (_correction_dialog /
  _reopen_dialog) + den generischen data-entry-modal-close-Handler in
  app.js — bewusst ohne eigenes JS-File (Ctrl+Enter-Shortcut aus §8
  zurückgestellt).
--}}

@extends('layouts.app')

@section('title', __('day-close.title_day', ['day' => $day->fdate()]))
@section('nav-title', __('Tagesabschluss'))

@php
    use App\Enums\TimeApproval\DayClosureStatus;
    use App\Services\TimeApproval\DayClosureValidator;

    // Minuten → "H:MM h" (negativ mit Vorzeichen).
    $fmtMin = static function (int $m): string {
        $sign = $m < 0 ? '−' : '';
        $m = abs($m);
        return sprintf('%s%d:%02d h', $sign, intdiv($m, 60), $m % 60);
    };

    $prevDay = $day->subDay()->toDateString();
    $nextDay = $day->addDay()->toDateString();
    $userParam = $isOwnDay ? [] : ['user' => \App\Support\Sqid::encode(\App\Models\User::class, $targetUser->id)];

    $isOpen = $effectiveStatus === DayClosureStatus::Open;
    $isClosedState = $effectiveStatus === DayClosureStatus::Closed;
    $inCorrection = $effectiveStatus === DayClosureStatus::Correction;

    $canCloseNow = $isOwnDay && $isOpen && ! $hasBlocking && ! $isFuture && ! $monthLocked;
    $closeBlockedReason = null;
    if ($isFuture) {
        $closeBlockedReason = __('day-close.errors.close_blocked.future');
    } elseif ($monthLocked) {
        $closeBlockedReason = __('day-close.errors.close_blocked.month_locked');
    } elseif ($hasBlocking) {
        $closeBlockedReason = __('day-close.errors.close_blocked.blocking');
    } elseif (! $isOpen) {
        $closeBlockedReason = __('day-close.errors.close_blocked.not_open');
    }

    $blockingIssues = array_values(array_filter($issues, fn(array $i) => $i['severity'] === DayClosureValidator::SEVERITY_BLOCKING));
    $warningIssues = array_values(array_filter($issues, fn(array $i) => $i['severity'] === DayClosureValidator::SEVERITY_WARNING));
    $breakIssue = collect($issues)->firstWhere('code', DayClosureValidator::CHECK_BREAK_REQUIRED);
    $pendingCorrections = $correctionRequests->filter(fn($r) => $r->isPending());
@endphp

@section('content')
    <x-index-page :subtitle="$isOwnDay
            ? __('day-close.subtitle.own')
            : __('day-close.subtitle.other', ['name' => $targetUser->name])"
                  :badge="$effectiveStatus->label()" :badgeTone="$effectiveStatus->tone()">
        <x-slot:actions>
            <div class="join">
                <x-icon-btn icon="chevron_left" tone="ghost" size="sm" class="join-item"
                            :href="route('day-close.show', array_merge(['date' => $prevDay], $userParam))"
                            :aria-label="__('day-close.action.prev_day')" />
                <x-icon-btn icon="today" tone="ghost" size="sm" class="join-item"
                            :href="route('day-close.show', $userParam)"
                            show-label>{{ __('day-close.action.today') }}</x-icon-btn>
                <x-icon-btn icon="chevron_right" tone="ghost" size="sm" class="join-item"
                            :href="route('day-close.show', array_merge(['date' => $nextDay], $userParam))"
                            :aria-label="__('day-close.action.next_day')" />
            </div>
            <form method="GET" action="{{ route('day-close.show') }}" class="flex items-center gap-1">
                @foreach ($userParam as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
                @endforeach
                <input type="date" name="date" value="{{ $day->toDateString() }}"
                       class="input input-sm input-bordered" aria-label="{{ __('day-close.action.pick_date') }}" />
                <x-icon-btn icon="search" tone="ghost" size="sm" type="submit"
                            :aria-label="__('day-close.action.show_day')" />
            </form>
        </x-slot:actions>

        @if (session('error'))
            <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
        @endif
        @if (session('status'))
            <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div role="alert" class="alert alert-warning"><span>{{ $errors->first() }}</span></div>
        @endif

        @if ($monthLocked)
            <div role="alert" class="alert alert-info">
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                <span>{{ __('day-close.hint.month_locked') }}</span>
            </div>
        @endif

        {{-- A) Anwesenheit ------------------------------------------------ --}}
        <x-card as="section">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                    {{ __('day-close.section.attendance') }}
                </h2>
                {{-- „Jetzt stempeln" (§2.1): reicht an die bestehende Stempeluhr
                     (attendance.clock-in/-out) durch statt eigener Mechanik. --}}
                @if ($isOwnDay && $isToday && $isOpen && ! $closure->attendance_locked)
                    @if ($openAttendance)
                        <form method="POST" action="{{ route('attendance.clock-out') }}">
                            @csrf
                            <x-icon-btn icon="logout" tone="warning" size="sm" type="submit"
                                        show-label>{{ __('day-close.action.clock_out') }}</x-icon-btn>
                        </form>
                    @else
                        <form method="POST" action="{{ route('attendance.clock-in') }}">
                            @csrf
                            <x-icon-btn icon="login" tone="primary" size="sm" type="submit"
                                        show-label>{{ __('day-close.action.clock_in') }}</x-icon-btn>
                        </form>
                    @endif
                @endif
            </div>

            @if ($attendances->isEmpty())
                <p class="text-sm opacity-70">{{ __('day-close.hint.no_attendance') }}</p>
            @else
                <ol class="space-y-2 text-sm">
                    @foreach ($attendances as $a)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="tabular-nums font-medium">
                                {{ $a->started_at?->ftime() }}
                                –
                                @if ($a->ended_at)
                                    {{ $a->ended_at->ftime() }}
                                @else
                                    <span class="text-warning">{{ __('day-close.status.attendance_open') }}</span>
                                @endif
                            </span>
                            <x-status-badge tone="ghost" size="sm">{{ $a->source->label() }}</x-status-badge>
                            @if ($a->break_minutes_total > 0)
                                <span class="opacity-70">{{ __('day-close.hint.break_recorded', ['min' => $a->break_minutes_total]) }}</span>
                            @endif
                            @if ($a->ended_at)
                                <span class="tabular-nums opacity-70">{{ $fmtMin((int) $a->duration_minutes) }}</span>
                            @endif
                            @if ($a->note)
                                <span class="opacity-70 truncate">{{ $a->note }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($closure->attendance_locked)
                <div role="alert" class="alert alert-info mt-3">
                    <span class="material-symbols-outlined" aria-hidden="true">lock_clock</span>
                    <span>{{ __('day-close.hint.attendance_locked') }}</span>
                </div>
            @endif
            <p class="mt-3 text-xs opacity-70">
                {{ __('day-close.hint.attendance_correction_only') }}
            </p>
        </x-card>

        {{-- B) Pausen ------------------------------------------------------ --}}
        <x-card as="section">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <span class="material-symbols-outlined" aria-hidden="true">pause</span>
                {{ __('day-close.section.breaks') }}
            </h2>
            <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.recorded_break') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['breaks']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.required_break') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['required_break']) }}</div>
                </div>
            </div>
            @if ($breakIssue)
                <div role="alert" class="alert alert-error mt-3">
                    <span class="material-symbols-outlined" aria-hidden="true">block</span>
                    <span>{{ $validator->messageFor($breakIssue) }}</span>
                </div>
            @endif
        </x-card>

        {{-- C) Auftrags-/Projektzeiten -------------------------------------- --}}
        <x-card as="section">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <span class="material-symbols-outlined" aria-hidden="true">assignment</span>
                    {{ __('day-close.section.entries') }}
                </h2>
                {{-- „Zeit buchen" (§2.3): öffnet das bestehende Buchungs-Modal
                     (Projekt-Picker → Zeiteintrag) über den entry-modal-Loader. --}}
                @if ($isOwnDay && $isOpen && ! $monthLocked)
                    <x-icon-btn icon="more_time" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('time-entries.create')"
                                show-label>{{ __('day-close.action.book_time') }}</x-icon-btn>
                @endif
            </div>

            @if ($entries->isEmpty())
                <p class="text-sm opacity-70">{{ __('day-close.hint.no_entries') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="text-right">{{ __('day-close.field.duration') }}</th>
                                <th>{{ __('day-close.field.project') }}</th>
                                <th>{{ __('day-close.field.activity') }}</th>
                                <th>{{ __('day-close.field.comment') }}</th>
                                <th class="text-center">{{ __('day-close.field.billable') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="text-right tabular-nums">{{ $fmtMin((int) $entry->minutes) }}</td>
                                    <td>
                                        @if ($entry->project)
                                            <a href="{{ route('projects.show', $entry->project) }}" class="link link-hover">
                                                {{ $entry->project->name }}
                                            </a>
                                        @else
                                            <span class="opacity-50">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $entry->activity_type?->label() }}</td>
                                    <td class="max-w-60 truncate">
                                        @if (filled($entry->description))
                                            {{ $entry->description }}
                                        @elseif ($entry->billable)
                                            <span class="text-warning">{{ __('day-close.status.comment_missing') }}</span>
                                        @else
                                            <span class="opacity-50">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($entry->billable)
                                            <span class="material-symbols-outlined text-success text-base" aria-hidden="true">check</span>
                                            <span class="sr-only">{{ __('day-close.status.billable') }}</span>
                                        @else
                                            <span class="opacity-50">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- D) Lücken & Warnungen (⛔ vor ⚠, §2.4) --------------------------- --}}
        <x-card as="section">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <span class="material-symbols-outlined" aria-hidden="true">report</span>
                {{ __('day-close.section.issues') }}
            </h2>
            @if (empty($issues))
                <div role="alert" class="alert alert-success">
                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                    <span>{{ __('day-close.hint.no_issues') }}</span>
                </div>
            @else
                <ul class="space-y-2">
                    @foreach ($blockingIssues as $issue)
                        <li role="alert" class="alert alert-error">
                            <span class="material-symbols-outlined" aria-hidden="true">block</span>
                            <span>{{ $validator->messageFor($issue) }}</span>
                        </li>
                    @endforeach
                    @foreach ($warningIssues as $issue)
                        <li role="alert" class="alert alert-warning">
                            <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                            <span>{{ $validator->messageFor($issue) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- E) Bilanz (§2.5) ------------------------------------------------ --}}
        <x-card as="section">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                <span class="material-symbols-outlined" aria-hidden="true">analytics</span>
                {{ __('day-close.section.balance') }}
            </h2>
            <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.target') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['target']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.gross') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['gross']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.break') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['breaks']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.net') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['net']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.booked') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['booked']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.diff') }}</div>
                    <div @class(['font-medium tabular-nums', 'text-warning' => abs($aggregates['diff']) > 5])>{{ $fmtMin($aggregates['diff']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.day_balance') }}</div>
                    <div @class(['font-medium tabular-nums', 'text-warning' => abs($aggregates['day_balance']) > 120])>{{ $fmtMin($aggregates['day_balance']) }}</div>
                </div>
                <div>
                    <div class="text-xs opacity-70">{{ __('day-close.field.month_balance') }}</div>
                    <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['month_balance']) }}</div>
                </div>
            </div>
        </x-card>

        {{-- Korrekturanträge (§5) ------------------------------------------ --}}
        @if ($correctionRequests->isNotEmpty())
            <x-card as="section">
                <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                    <span class="material-symbols-outlined" aria-hidden="true">rule</span>
                    {{ __('day-close.section.corrections') }}
                </h2>
                <ul class="space-y-3 text-sm">
                    @foreach ($correctionRequests as $cr)
                        <li class="flex flex-wrap items-start justify-between gap-2 rounded-box border border-base-300 p-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <x-status-badge :tone="$cr->status->tone()" size="sm">{{ $cr->status->label() }}</x-status-badge>
                                    <span class="text-xs opacity-70">{{ $cr->created_at?->fdatetime() }} · {{ $cr->requestedBy?->name }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line">{{ $cr->reason }}</p>
                                @if ($cr->decision_note)
                                    <p class="mt-1 text-xs opacity-70">{{ __('day-close.field.decision') }}: {{ $cr->decision_note }} ({{ $cr->decidedBy?->name }})</p>
                                @endif
                            </div>
                            @if ($cr->isPending() && $closure->exists)
                                @can('approveCorrection', $closure)
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('day-close.correction.approve', $cr) }}">
                                            @csrf
                                            <x-icon-btn icon="check" tone="success" size="sm" type="submit"
                                                        show-label>{{ __('day-close.action.approve') }}</x-icon-btn>
                                        </form>
                                        <form method="POST" action="{{ route('day-close.correction.reject', $cr) }}">
                                            @csrf
                                            <x-icon-btn icon="close" tone="warning" size="sm" type="submit"
                                                        show-label>{{ __('day-close.action.reject') }}</x-icon-btn>
                                        </form>
                                    </div>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        {{-- F) Aktionen (sticky CTA auf Mobile, §8) -------------------------- --}}
        <div class="sticky bottom-0 z-10 -mx-2 rounded-box border border-base-300 bg-base-100/95 p-3 shadow-xs backdrop-blur lg:static lg:mx-0">
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($isOwnDay && $isOpen && ! $monthLocked)
                    <form method="POST" action="{{ route('day-close.save') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $day->toDateString() }}" />
                        <x-icon-btn icon="save" tone="ghost" size="sm" type="submit"
                                    show-label>{{ __('day-close.action.save') }}</x-icon-btn>
                    </form>
                @endif

                @if ($isOwnDay && ! $monthLocked && ($isOpen || $inCorrection))
                    <span @class(['tooltip tooltip-left' => ! $canCloseNow]) @if (! $canCloseNow && $closeBlockedReason) data-tip="{{ $closeBlockedReason }}" @endif>
                        <form method="POST" action="{{ route('day-close.close') }}">
                            @csrf
                            <input type="hidden" name="date" value="{{ $day->toDateString() }}" />
                            <button type="submit" class="btn btn-sm btn-primary" @disabled(! $canCloseNow)>
                                <span class="material-symbols-outlined text-base" aria-hidden="true">task_alt</span>
                                {{ __('day-close.action.close_day') }}
                            </button>
                        </form>
                    </span>
                @endif

                @if ($isOwnDay && $isClosedState && ! $monthLocked && $pendingCorrections->isEmpty())
                    @can('requestCorrection', $closure)
                        <button type="button" class="btn btn-sm btn-warning"
                                onclick="document.getElementById('day-correction-dialog').showModal()">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">edit_note</span>
                            {{ __('day-close.action.request_correction') }}
                        </button>
                    @endcan
                @endif

                @if ($closure->exists && $isClosedState && ! $monthLocked)
                    @can('reopen', $closure)
                        <button type="button" class="btn btn-sm btn-ghost"
                                onclick="document.getElementById('day-reopen-dialog').showModal()">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">lock_open</span>
                            {{ __('day-close.action.reopen') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        @include('time-approval.day._correction_dialog')
        @include('time-approval.day._reopen_dialog')
    </x-index-page>
@endsection
