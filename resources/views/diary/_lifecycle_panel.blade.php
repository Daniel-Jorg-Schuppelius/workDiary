{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _lifecycle_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $toneMap = ['done' => 'success', 'progress' => 'primary', 'open' => 'info', 'alert' => 'warning', 'neutral' => 'ghost'];
    $eventStatusLabels = [
        'planned' => __('Geplant'),
        'accepted' => __('Angenommen'),
        'in_progress' => __('In Bearbeitung'),
        'waiting_customer' => __('Wartet auf Rückmeldung'),
        'waiting_material' => __('Wartet auf Material'),
        'completed' => __('Abgeschlossen'),
        'accepted_final' => __('Abgenommen'),
        'invoiced' => __('Berechnet'),
        'cancelled' => __('Storniert'),
        'in_review' => __('diary.status.InReview'),
        'review_queried' => __('diary.status.ReviewQueried'),
        'review_approved' => __('diary.status.ReviewApproved'),
        'open' => __('Offen'),
        'problem' => __('Problem'),
        'done' => __('Erledigt'),
    ];
    $statusTone = $toneMap[$diary->statusStage()] ?? 'ghost';
    // Mit Prüfpflicht führt der Weg über Vorlage und Freigabe (Feature 198).
    $actions = $diary->lifecycleActions();
    $signedProtocol = $diary->protocols
        ->first(fn ($protocol) => $protocol->status === \App\Enums\Protocol\ProtocolStatus::Signed);
    // Ohne Kundenunterschrift im Protokoll fragt die Abnahme nach dem Namen der abnehmenden Person.
    $handoverSigner = $signedProtocol !== null && in_array('handover', $actions, true)
        ? \App\Services\Diary\OrderService::customerSignerName($signedProtocol)
        : null;
    $dialogSuffix = $diary->sqid;
@endphp

<section class="mb-6 rounded-box border border-base-300 bg-base-200/40 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted">{{ __('Auftragsstatus') }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-status-badge :tone="$statusTone" icon="route">{{ $diary->statusLabel() }}</x-status-badge>
                @if ($diary->assignedUser)
                    <span class="text-sm text-base-content/70">{{ __('Zugewiesen an :name', ['name' => $diary->assignedUser->name]) }}</span>
                @endif
                @if ($diary->accepted_final_at && $diary->accepted_final_by)
                    <span class="text-sm text-base-content/70">{{ __('diary.lifecycle.accepted_final_by', ['name' => $diary->accepted_final_by, 'date' => $diary->accepted_final_at->fdate()]) }}</span>
                @endif
                @if ($diary->invoiced_at && $diary->invoice_reference)
                    <span class="text-sm text-base-content/70">{{ __('diary.lifecycle.invoiced_with', ['reference' => $diary->invoice_reference]) }}</span>
                @endif
            </div>
            {{-- Periode und interne Prüfung (Feature 198). --}}
            @php($canToggleReview = ! $isDialog && in_array($diary->status, [\App\Enums\Diary\Status::Planned, \App\Enums\Diary\Status::Accepted, \App\Enums\Diary\Status::InProgress, \App\Enums\Diary\Status::WaitingCustomer, \App\Enums\Diary\Status::WaitingMaterial], true) && auth()->user()?->can('waiveReview', $diary))
            @if ($diary->period_key || $diary->requires_review || $canToggleReview)
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                    @if ($diary->period_key)
                        <x-status-badge tone="info" outline icon="date_range">{{ __('diary.period.badge', ['period' => $diary->period_key->label()]) }}</x-status-badge>
                    @endif
                    @if ($diary->requires_review)
                        <x-status-badge tone="primary" outline icon="fact_check">{{ __('diary.review.required_badge') }}</x-status-badge>
                        @if ($diary->reviewer)
                            <span>{{ __('diary.review.reviewer_named', ['name' => $diary->reviewer->name]) }}</span>
                        @endif
                        @if ($diary->status->isReview() && $diary->review_submitted_at)
                            <span>{{ __('diary.review.submitted_by', ['name' => $diary->reviewSubmitter?->name ?? '—', 'date' => $diary->review_submitted_at->fdatetime()]) }}</span>
                        @endif
                    @endif
                    @if ($canToggleReview)
                        <button type="button" class="link link-primary text-xs" data-open-dialog="order-review-requirement-{{ $dialogSuffix }}">
                            {{ __($diary->requires_review ? 'diary.review.waive_action' : 'diary.review.require_action') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @if (in_array('accept', $actions, true))
                @can('accept', $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'accept']) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="check_circle" tone="primary" size="sm" show-label>{{ __('Annehmen') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (! $isDialog && in_array('reject', $actions, true))
                @can('reject', $diary)
                    <x-icon-btn type="button" icon="undo" tone="outline" size="sm" show-label
                        data-open-dialog="order-reject-{{ $dialogSuffix }}">{{ __('diary.lifecycle.action.reject') }}</x-icon-btn>
                @endcan
            @endif
            @if (in_array('start', $actions, true))
                @can('start', $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'start']) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="play_arrow" tone="primary" size="sm" show-label>{{ __('Beginnen') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (in_array('resume', $actions, true))
                @can('resume', $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'resume']) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="resume" tone="primary" size="sm" show-label>{{ __('Fortsetzen') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (! $isDialog && in_array('pause', $actions, true))
                @can('pause', $diary)
                    <x-icon-btn type="button" icon="pause" tone="warning" size="sm" show-label
                        data-open-dialog="order-pause-{{ $dialogSuffix }}">{{ __('Pausieren') }}</x-icon-btn>
                @endcan
            @endif
            @if (! $isDialog && in_array('complete', $actions, true))
                @can('complete', $diary)
                    <x-icon-btn type="button" icon="task_alt" tone="success" size="sm" show-label
                        data-open-dialog="order-complete-{{ $dialogSuffix }}">{{ __('Abschließen') }}</x-icon-btn>
                @endcan
            @endif
            @if (in_array('handover', $actions, true))
                @can('handover', $diary)
                    @if ($signedProtocol && $handoverSigner === null)
                        @unless ($isDialog)
                            <x-icon-btn type="button" icon="handshake" tone="primary" size="sm" show-label
                                data-open-dialog="order-handover-{{ $dialogSuffix }}">{{ __('Abnahme starten') }}</x-icon-btn>
                        @endunless
                    @elseif ($signedProtocol)
                        <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'handover']) }}">
                            @csrf
                            <input type="hidden" name="protocol_id" value="{{ $signedProtocol->sqid }}">
                            <x-icon-btn type="submit" icon="handshake" tone="primary" size="sm" show-label>{{ __('Abnahme starten') }}</x-icon-btn>
                        </form>
                    @else
                        <span class="text-sm text-warning">{{ __('Für die Abnahme fehlt ein signiertes Protokoll.') }}</span>
                    @endif
                @endcan
            @endif
            @if (! $isDialog && in_array('markInvoiced', $actions, true))
                @can('markInvoiced', $diary)
                    <x-icon-btn type="button" icon="receipt_long" tone="success" size="sm" show-label
                        data-open-dialog="order-invoice-{{ $dialogSuffix }}">{{ __('Als berechnet markieren') }}</x-icon-btn>
                @endcan
            @endif
            @if (in_array('submitReview', $actions, true) || in_array('resubmitReview', $actions, true))
                @php($submitAction = in_array('submitReview', $actions, true) ? 'submitReview' : 'resubmitReview')
                @can($submitAction, $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => $submitAction]) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="fact_check" tone="primary" size="sm" show-label>{{ __($submitAction === 'submitReview' ? 'diary.lifecycle.action.submit_review' : 'diary.lifecycle.action.resubmit_review') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (in_array('approveReview', $actions, true))
                @can('approveReview', $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'approveReview']) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="verified" tone="success" size="sm" show-label>{{ __('diary.lifecycle.action.approve_review') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (! $isDialog && in_array('queryReview', $actions, true))
                @can('queryReview', $diary)
                    <x-icon-btn type="button" icon="contact_support" tone="warning" size="sm" show-label
                        data-open-dialog="order-query-{{ $dialogSuffix }}">{{ __('diary.lifecycle.action.query_review') }}</x-icon-btn>
                @endcan
            @endif
            @if (! $isDialog && in_array('approveAndComplete', $actions, true))
                @can('approveAndComplete', $diary)
                    <x-icon-btn type="button" icon="task_alt" tone="outline" size="sm" show-label
                        data-open-dialog="order-approve-complete-{{ $dialogSuffix }}">{{ __('diary.lifecycle.action.approve_and_complete') }}</x-icon-btn>
                @endcan
            @endif
            @if (in_array('withdrawReview', $actions, true))
                @can('withdrawReview', $diary)
                    <form method="POST" action="{{ route('diary.lifecycle', [$diary, 'action' => 'withdrawReview']) }}">
                        @csrf
                        <x-icon-btn type="submit" icon="undo" tone="outline" size="sm" show-label>{{ __('diary.lifecycle.action.withdraw_review') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
            @if (! $isDialog && $diary->period_key && $diary->customer_id && $diary->entry_type_id)
                @can('createFollowUp', $diary)
                    <x-icon-btn type="button" icon="event_repeat" tone="outline" size="sm" show-label
                        data-open-dialog="order-follow-up-{{ $dialogSuffix }}">{{ __('diary.follow_up.action') }}</x-icon-btn>
                @endcan
            @endif
            @if (! $isDialog && in_array('cancel', $actions, true))
                @can('cancel', $diary)
                    <x-icon-btn type="button" icon="cancel" tone="error" size="sm" show-label
                        data-open-dialog="order-cancel-{{ $dialogSuffix }}">{{ __('Stornieren') }}</x-icon-btn>
                @endcan
            @endif
        </div>
    </div>

    @if ($diary->journal->isNotEmpty())
        <ol class="mt-4 grid gap-2 border-t border-base-300 pt-3 text-sm md:grid-cols-2">
            @foreach ($diary->journal->take(-4) as $event)
                <li class="flex items-start gap-2">
                    <x-icon name="history" size="1rem" class="mt-0.5 shrink-0 text-muted" />
                    <span>
                        <span class="font-medium">{{ __(':from → :to', [
                            'from' => $event->from_status ? ($eventStatusLabels[$event->from_status] ?? $event->from_status) : '—',
                            'to' => $eventStatusLabels[$event->to_status] ?? $event->to_status,
                        ]) }}</span>
                        <span class="block text-xs text-muted">
                            {{ $event->occurred_at->fdatetime() }} · {{ $event->actor?->name ?? __('System') }}
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</section>

@unless ($isDialog)
    @include('diary._lifecycle_dialogs', ['dialogSuffix' => $dialogSuffix, 'signedProtocol' => $signedProtocol, 'handoverSigner' => $handoverSigner])
@endunless
