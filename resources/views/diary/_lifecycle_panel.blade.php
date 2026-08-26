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
        'open' => __('Offen'),
        'problem' => __('Problem'),
        'done' => __('Erledigt'),
    ];
    $statusTone = $toneMap[$diary->statusTone()] ?? 'ghost';
    $actions = $diary->status->allowedActions();
    $signedProtocol = $diary->protocols
        ->first(fn ($protocol) => $protocol->status === \App\Enums\Protocol\ProtocolStatus::Signed);
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
            </div>
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
                    @if ($signedProtocol)
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
            @if (! $isDialog && in_array('cancel', $actions, true))
                @can('cancel', $diary)
                    <x-icon-btn type="button" icon="cancel" tone="error" size="sm" show-label
                        data-open-dialog="order-cancel-{{ $dialogSuffix }}">{{ __('Stornieren') }}</x-icon-btn>
                @endcan
            @endif
        </div>
    </div>

    @if ($diary->lifecycleEvents->isNotEmpty())
        <ol class="mt-4 grid gap-2 border-t border-base-300 pt-3 text-sm md:grid-cols-2">
            @foreach ($diary->lifecycleEvents->take(-4) as $event)
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
    @include('diary._lifecycle_dialogs', ['dialogSuffix' => $dialogSuffix])
@endunless
