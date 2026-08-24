{{--
  Created on   : Fri Jun 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Tagesabschluss (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §2/§8):
  EINE Seite pro Mitarbeitendem mit den Sektionen
  A) Anwesenheit  B) Auftrags-/Projektzeiten  C) Lücken & Warnungen
  D) Bilanz (inkl. Pausen Ist/Soll)  E) Aktionen (sticky auf Mobile).
  Modals laufen über <x-modal :embedded="false"> (_correction_dialog /
  _reopen_dialog) + den generischen data-entry-modal-close-Handler in
  app.js — bewusst ohne eigenes JS-File (Ctrl+Enter-Shortcut aus §8
  zurückgestellt).
--}}

@extends('layouts.app')

@section('title', __('day-close.title_day', ['day' => $day->fdate()]))
@section('nav-title', __('Tagesabschluss'))

@php
    // Minuten → "H:MM h" (negativ mit Vorzeichen). Wird von den gemeinsamen
    // Workflow-Partial _balance aus dem Scope übernommen.
    $fmtMin = static function (int $m): string {
        $sign = $m < 0 ? '−' : '';
        $m = abs($m);
        return sprintf('%s%d:%02d h', $sign, intdiv($m, 60), $m % 60);
    };

    $prevDay = $day->subDay()->toDateString();
    $nextDay = $day->addDay()->toDateString();
    $userParam = $isOwnDay ? [] : ['user' => \App\Support\Sqid::encode(\App\Models\User::class, $targetUser->id)];

    // Wird von den hier verbliebenen Inline-Sektionen A) Anwesenheit (Stempeln)
    // und B) Zeiteinträge („Zeit buchen") genutzt. Die übrigen Status-Flags
    // berechnen die gemeinsamen Partials (_actions) selbst.
    $isOpen = $effectiveStatus === \App\Enums\TimeApproval\DayClosureStatus::Open;
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

        @if (session('status'))
            <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
        @endif
        <x-validation-errors first tone="warning" />

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


        {{-- B) Auftrags-/Projektzeiten -------------------------------------- --}}
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
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th class="text-right">{{ __('day-close.field.duration') }}</th>
                                <th>{{ __('day-close.field.project') }}</th>
                                <th>{{ __('day-close.field.activity') }}</th>
                                <th>{{ __('day-close.field.comment') }}</th>
                                <th class="text-center">{{ __('day-close.field.billable') }}</th>
                            </tr>
                    </x-slot:head>
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
                                    <td class="max-w-60">
                                        <span class="block truncate">
                                            @if (filled($entry->description))
                                                {{ $entry->description }}
                                            @elseif ($entry->billable)
                                                <span class="text-warning">{{ __('day-close.status.comment_missing') }}</span>
                                            @else
                                                <span class="opacity-50">—</span>
                                            @endif
                                        </span>
                                        @if ($entry->tags->isNotEmpty())
                                            <span class="mt-0.5 flex flex-wrap gap-1">
                                                @foreach ($entry->tags as $tag)
                                                    <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->name }}</span>
                                                @endforeach
                                            </span>
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
                </x-table>
            @endif
        </x-card>

        {{-- C) Lücken & Warnungen (⛔ vor ⚠, §2.4) --------------------------- --}}
        @include('time-approval.day._issues')

        {{-- D) Bilanz inkl. Pausen (§2.5) ----------------------------------- --}}
        @include('time-approval.day._balance')

        {{-- Korrekturanträge (§5) ------------------------------------------ --}}
        @include('time-approval.day._corrections')

        {{-- F) Aktionen (sticky CTA auf Mobile, §8) + Dialoge ---------------- --}}
        @include('time-approval.day._actions')
    </x-index-page>
@endsection
