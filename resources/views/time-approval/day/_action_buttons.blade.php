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
