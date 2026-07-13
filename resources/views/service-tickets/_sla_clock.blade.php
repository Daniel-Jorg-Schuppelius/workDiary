{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sla_clock.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  SLA-Uhr (Feature 065, MVP-160): Reaktions-/Lösungsfrist mit Status,
  eingefrorener Vertragsstand (sla_snapshot), Wartefelder und offene
  Uhr-Pausen (slaClockSegments). Erwartet: $ticket.
--}}
@php
    /** @var \App\Models\ServiceTicket $ticket */
    $reactionStatus = $ticket->slaReactionStatus();
    $resolutionStatus = $ticket->slaStatus();
    $minutesRemaining = $ticket->slaMinutesRemaining();
    $snapshot = (array) ($ticket->sla_snapshot ?? []);
    $openSegments = $ticket->slaClockSegments->whereNull('paused_to');
    $segmentTargetLabels = [
        \App\Models\SlaClockSegment::TARGET_REACTION => __('Reaktionsfrist'),
        \App\Models\SlaClockSegment::TARGET_RESOLUTION => __('Lösungsfrist'),
    ];
@endphp

<x-card :title="__('SLA-Uhr')" icon="timer">
    @if ($ticket->reaction_due_at === null && $ticket->resolution_due_at === null)
        <p class="text-sm text-base-content/60">{{ __('Keine SLA-Frist hinterlegt.') }}</p>
    @else
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div>
                <dt class="text-base-content/60">{{ __('Reaktionsfrist') }}</dt>
                <dd class="flex items-center gap-2">
                    {{ $ticket->reaction_due_at?->translatedFormat('d.m.Y H:i') ?: '—' }}
                    @if ($ticket->reaction_due_at)
                        <x-status-badge :tone="$reactionStatus->tone()" size="sm" outline>{{ $reactionStatus->label() }}</x-status-badge>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-base-content/60">{{ __('Lösungsfrist') }}</dt>
                <dd class="flex items-center gap-2">
                    {{ $ticket->resolution_due_at?->translatedFormat('d.m.Y H:i') ?: '—' }}
                    @if ($ticket->resolution_due_at)
                        <x-status-badge :tone="$resolutionStatus->tone()" size="sm" outline>{{ $resolutionStatus->label() }}</x-status-badge>
                    @endif
                    @if ($minutesRemaining !== null && $resolutionStatus->value !== 'met' && $resolutionStatus->value !== 'none')
                        <span class="{{ $resolutionStatus->textClass() }} text-xs">
                            {{ $minutesRemaining < 0 ? __('sla.overdue_by', ['min' => abs($minutesRemaining)]) : __('sla.remaining', ['min' => $minutesRemaining]) }}
                        </span>
                    @endif
                </dd>
            </div>
            @if ($snapshot !== [])
                <div class="md:col-span-2">
                    <dt class="text-base-content/60">{{ __('Eingefrorener Vertragsstand') }}</dt>
                    <dd>
                        {{ $snapshot['contract_name'] ?? $ticket->slaContract?->label ?? '—' }}
                        @if (! empty($snapshot['frozen_at']))
                            <span class="text-xs text-base-content/60">
                                · {{ __('eingefroren am :date', ['date' => \Illuminate\Support\Carbon::parse($snapshot['frozen_at'])->translatedFormat('d.m.Y H:i')]) }}
                            </span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    @endif

    @if ($ticket->status->isWaiting())
        <div class="divider my-2"></div>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-base-content/60">{{ __('Wartegrund') }}</dt><dd>{{ $ticket->wait_reason ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Wiedervorlage') }}</dt><dd>{{ $ticket->wait_until?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Verantwortlich') }}</dt><dd>{{ $ticket->waitOwner?->name ?: '—' }}</dd></div>
        </dl>
    @endif

    @if ($openSegments->isNotEmpty())
        <div class="divider my-2"></div>
        <ul class="space-y-1 text-sm">
            @foreach ($openSegments as $segment)
                <li class="flex items-center gap-2">
                    <x-icon name="pause_circle" class="text-warning" />
                    {{ __('SLA-Uhr pausiert (:target) seit :time', [
                        'target' => $segmentTargetLabels[$segment->target] ?? $segment->target,
                        'time' => $segment->paused_from->translatedFormat('d.m.Y H:i'),
                    ]) }}
                    <x-status-badge tone="warning" size="xs" outline>
                        {{ \App\Enums\ServiceTicket\ServiceTicketStatus::tryFrom($segment->reason)?->label() ?? $segment->reason }}
                    </x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
