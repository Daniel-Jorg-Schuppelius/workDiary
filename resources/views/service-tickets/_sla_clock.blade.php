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
    /** @var \App\Models\ServiceTicket\ServiceTicket $ticket */
    $reactionStatus = $ticket->slaReactionStatus();
    $resolutionStatus = $ticket->slaStatus();
    $minutesRemaining = $ticket->slaMinutesRemaining();
    $snapshot = (array) ($ticket->sla_snapshot ?? []);
    $openSegments = $ticket->slaClockSegments->whereNull('paused_to');
    $segmentTargetLabels = [
        \App\Models\ServiceTicket\SlaClockSegment::TARGET_REACTION => __('Reaktionsfrist'),
        \App\Models\ServiceTicket\SlaClockSegment::TARGET_RESOLUTION => __('Lösungsfrist'),
    ];
@endphp

<x-card :title="__('SLA-Uhr')" icon="timer">
    @if ($ticket->reaction_due_at === null && $ticket->resolution_due_at === null)
        <x-empty-state icon="timer_off" :title="__('Keine SLA-Frist hinterlegt.')" compact />
    @else
        <x-detail-grid layout="cells">
            <x-detail-grid.row :label="__('Reaktionsfrist')" class="flex items-center gap-2">
                {{ $ticket->reaction_due_at?->orgTz()->translatedFormat('d.m.Y H:i') ?: '—' }}
                @if ($ticket->reaction_due_at)
                    <x-status-badge :tone="$reactionStatus->tone()" size="sm" outline>{{ $reactionStatus->label() }}</x-status-badge>
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Lösungsfrist')" class="flex items-center gap-2">
                {{ $ticket->resolution_due_at?->orgTz()->translatedFormat('d.m.Y H:i') ?: '—' }}
                @if ($ticket->resolution_due_at)
                    <x-status-badge :tone="$resolutionStatus->tone()" size="sm" outline>{{ $resolutionStatus->label() }}</x-status-badge>
                @endif
                @if ($minutesRemaining !== null && $resolutionStatus->value !== 'met' && $resolutionStatus->value !== 'none')
                    <span class="{{ $resolutionStatus->textClass() }} text-xs">
                        {{ $minutesRemaining < 0 ? __('sla.overdue_by', ['min' => abs($minutesRemaining)]) : __('sla.remaining', ['min' => $minutesRemaining]) }}
                    </span>
                @endif
            </x-detail-grid.row>
            @if ($snapshot !== [])
                <x-detail-grid.row :label="__('Eingefrorener Vertragsstand')" full>
                    {{ $snapshot['contract_name'] ?? $ticket->slaContract?->label ?? '—' }}
                    @if (! empty($snapshot['frozen_at']))
                        <span class="text-xs text-muted">
                            · {{ __('eingefroren am :date', ['date' => \Illuminate\Support\Carbon::parse($snapshot['frozen_at'])->translatedFormat('d.m.Y H:i')]) }}
                        </span>
                    @endif
                </x-detail-grid.row>
            @endif
        </x-detail-grid>
    @endif

    @if ($ticket->status->isWaiting())
        <div class="divider my-2"></div>
        <x-detail-grid layout="cells" :cols="3">
            <x-detail-grid.row :label="__('Wartegrund')">{{ $ticket->wait_reason ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Wiedervorlage')">{{ $ticket->wait_until?->translatedFormat('d.m.Y H:i') ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Verantwortlich')">{{ $ticket->waitOwner?->name ?: '—' }}</x-detail-grid.row>
        </x-detail-grid>
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
