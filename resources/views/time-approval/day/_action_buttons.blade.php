{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _action_buttons.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Tagesabschluss-Aktions-Buttons (MVP-015 §2.6): Speichern / Abschließen /
  Korrektur anfordern / Reopen — OHNE Wrapper und ohne Dialoge, damit der Host
  sie frei platzieren kann (Toolbar auf „Heute", sticky CTA-Leiste auf der
  Tagesabschluss-Seite). Die Dialoge (_correction_dialog/_reopen_dialog) muss der
  Host separat einbinden.
  Erwartet aus dem Host-Scope: $isOwnDay, $effectiveStatus, $monthLocked,
  $hasBlocking, $isFuture, $closure, $day, $correctionRequests.
--}}
@php
    $isOpen = $effectiveStatus === \App\Enums\TimeApproval\DayClosureStatus::Open;
    $isClosedState = $effectiveStatus === \App\Enums\TimeApproval\DayClosureStatus::Closed;
    $inCorrection = $effectiveStatus === \App\Enums\TimeApproval\DayClosureStatus::Correction;

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

    $pendingCorrections = $correctionRequests->filter(fn($r) => $r->isPending());
@endphp

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
            <x-button type="submit" tone="primary" :disabled="! $canCloseNow" icon="task_alt">{{ __('day-close.action.close_day') }}</x-button>
        </form>
    </span>
@endif

@if ($isOwnDay && $isClosedState && ! $monthLocked && $pendingCorrections->isEmpty())
    @can('requestCorrection', $closure)
        <x-button type="button" tone="warning"
                data-open-dialog="day-correction-dialog" icon="edit_note">{{ __('day-close.action.request_correction') }}</x-button>
    @endcan
@endif

@if ($closure->exists && $isClosedState && ! $monthLocked)
    @can('reopen', $closure)
        <x-button type="button" tone="ghost"
                data-open-dialog="day-reopen-dialog" icon="lock_open">{{ __('day-close.action.reopen') }}</x-button>
    @endcan
@endif
