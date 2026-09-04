{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _period_row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eine Periodenzeile (Feature 152, MVP-761): Deckung in Lizenzmonaten,
  Bezüge mit Herkunft, Aktionen bestätigen / Bezug / verzichten / öffnen.
  Erwartet: $period, $subscription, $showSubscription, $canManage, $today.
--}}
@php
    $required = $period->requiredMonths();
    $covered = $period->coveredMonths();
    $upcoming = $period->starts_on->greaterThan($today);
    $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp
<tr @class(['hover', 'opacity-60' => $upcoming])>
    @if ($showSubscription)
        <td>
            <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
        </td>
        <td class="text-sm">
            {{ $subscription->holderLabel() }}
            @if ($subscription->foreignCustomer !== null)
                <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $subscription->foreignCustomer->customer?->name]) }}</span>
            @endif
        </td>
    @endif
    <td class="whitespace-nowrap tabular-nums">
        {{ $period->label() }}
        @if ($upcoming)
            <span class="badge badge-ghost badge-xs ml-1">{{ __('resale.periods.upcoming') }}</span>
        @endif
    </td>
    <td class="text-right tabular-nums">{{ $period->quantity }}</td>
    <td class="text-right tabular-nums whitespace-nowrap">{{ $period->expected_sale?->format() ?? '—' }}</td>
    <td class="text-right tabular-nums whitespace-nowrap">
        <span @class(['text-success font-medium' => $covered >= $required - 0.001 && $required > 0, 'text-warning' => $covered > 0.001 && $covered < $required - 0.001, 'text-error' => $covered <= 0.001 && ! $upcoming])>
            {{ $fmt($covered) }} / {{ $fmt($required) }}
        </span>
        <span class="block text-xs text-muted">{{ __('resale.link.months') }}</span>
    </td>
    <td class="text-sm">
        @forelse ($period->links as $link)
            <span class="inline-flex items-center gap-1 mr-1 mb-0.5">
                <x-status-badge size="xs" :tone="$link->origin->tone()" :label="($link->voucher_number ?: '—') . ' · ' . $fmt((float) $link->months) . ' ' . __('resale.link.months_short')" :title="$link->origin->label() . ($link->note ? ' · ' . $link->note : '')" />
                @if ($canManage && ! $period->status->isDecided() || $canManage && $period->status !== \App\Enums\Reselling\PeriodStatus::Waived)
                    <form method="POST" action="{{ route('finance.resale.links.destroy', $link->sqid) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                    </form>
                @endif
            </span>
        @empty
            <span class="text-muted">—</span>
        @endforelse
        @if ($period->isProposedOnly())
            <span class="block text-xs text-info">{{ __('resale.link.proposed_hint') }}</span>
        @endif
        @if ($period->waived_reason)
            <span class="block text-xs text-muted">{{ $period->waived_reason }}</span>
        @endif
    </td>
    <td>
        <x-status-badge size="xs" :tone="$period->status->tone()" :label="$period->status->label()" />
        @if ($period->decided_at !== null)
            <span class="block text-xs text-muted">{{ $period->decidedBy?->name }} · {{ $period->decided_at->format('d.m.Y') }}</span>
        @endif
    </td>
    <td class="text-right">
        @if ($canManage && ! $upcoming)
            <div class="flex justify-end gap-1">
                @if ($period->isProposedOnly())
                    <form method="POST" action="{{ route('finance.resale.periods.confirm', $period->sqid) }}">
                        @csrf
                        <x-icon-btn icon="task_alt" size="xs" tone="success" type="submit" :title="__('resale.link.action.confirm')" />
                    </form>
                @endif
                @if ($period->status !== \App\Enums\Reselling\PeriodStatus::Waived)
                    <x-icon-btn icon="add_link" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.periods.link.create', $period->sqid)" :title="__('resale.link.action.link')" />
                @endif
                @if (! $period->status->isDecided() || $period->status === \App\Enums\Reselling\PeriodStatus::Partial)
                    <x-icon-btn icon="do_not_disturb_on" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.periods.waive.create', $period->sqid)" :title="__('resale.link.action.waive')" />
                @endif
                @if ($period->status->isDecided())
                    <form method="POST" action="{{ route('finance.resale.periods.reopen', $period->sqid) }}">
                        @csrf
                        <x-icon-btn icon="undo" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.reopen')" />
                    </form>
                @endif
            </div>
        @endif
    </td>
</tr>
