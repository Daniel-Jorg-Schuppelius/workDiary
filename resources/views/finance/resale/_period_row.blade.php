{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _period_row.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eine Periodenzeile (Feature 152, MVP-761): Deckung in Lizenzmonaten,
  Bezüge mit Herkunft, Aktionen bestätigen / Bezug / verzichten / öffnen.
  Erwartet: $period, $subscription, $showSubscription, $canManage, $today;
  optional $bulk (Auswahlspalte für Sammelaktionen, Feature 185). Vorschläge
  tragen ihre Vertrauensstufe: unsicher = außerhalb des Fensters um den
  Periodenbeginn (LinkProposer::isSure).
--}}
@php
    $bulk = $bulk ?? false;
    $plannedNext = $plannedNext ?? [];
    $required = $period->requiredMonths();
    $covered = $period->coveredMonths();
    $term = $period->termMonths();
    $upcoming = $period->starts_on->greaterThan($today);
    $compact = \App\View\Components\Resale\LicenceMonths::class;
@endphp
<tr @class(['hover', 'opacity-60' => $upcoming])>
    @if ($bulk)
        <td class="w-8">
            @if ($canManage && ! $upcoming)
                <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="ids[]" value="{{ $period->sqid }}" aria-label="{{ __('Auswählen') }}">
            @endif
        </td>
    @endif
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
            <x-status-badge size="xs" class="ml-1">{{ __('resale.periods.upcoming') }}</x-status-badge>
        @endif
        @php
            // Serienvorlage läuft noch für diese Periode (Phase 163, MVP-1431): kein Alarm, nur ein Hinweis.
            $plannedOn = isset($plannedNext[$subscription->id]) && in_array($period->status, [\App\Enums\Reselling\PeriodStatus::Open, \App\Enums\Reselling\PeriodStatus::Partial], true)
                && \App\Services\Reselling\Register\ResaleOverview::isPlanned($period, $plannedNext[$subscription->id]) ? $plannedNext[$subscription->id] : null;
        @endphp
        @if ($plannedOn !== null)
            <x-status-badge size="xs" tone="info" outline class="ml-1" :label="__('resale.periods.planned', ['date' => $plannedOn->fdate()])" />
        @endif
    </td>
    <td class="text-right tabular-nums">{{ $period->quantity }}</td>
    <td class="text-right tabular-nums whitespace-nowrap">{{ $period->expected_sale?->format() ?? '—' }}</td>
    <td class="text-right tabular-nums whitespace-nowrap">
        {{-- Lizenzen × Monate statt Lizenzmonate: „5 / 5 Lizenzen · 12 Mon." --}}
        <span @class(['text-success font-medium' => $covered >= $required - 0.001 && $required > 0, 'text-warning' => $covered > 0.001 && $covered < $required - 0.001, 'text-error' => $covered <= 0.001 && ! $upcoming])
              title="{{ $compact::compact($covered) }} / {{ $compact::compact($required) }} {{ __('resale.link.months') }}">
            {{ __('resale.link.licences_of', ['covered' => $compact::compact($covered / max(1, $term)), 'quantity' => $period->quantity, 'months' => $term]) }}
        </span>
    </td>
    <td class="text-sm">
        @forelse ($period->links as $link)
            @php
                $mirrorLine = $link->mirrorLine();
                $sure = $link->origin === \App\Enums\Reselling\LinkOrigin::Proposed
                    ? ($mirrorLine !== null && \App\Services\Reselling\Register\LinkProposer::isSure($period, $subscription, $mirrorLine))
                    : null;
                $previewUrl = $mirrorLine?->previewUrl; // Belegbild über die Spiegelquelle (Recht prüft die Quelle)
            @endphp
            <span class="inline-flex items-center gap-1 mr-1 mb-0.5">
                {{-- Die Belegnummer öffnet den Beleg mit Positionen und dem Vergleich zur Periode (Nachbesserung 2026-10-27). --}}
                @if ($mirrorLine !== null)
                    <a href="{{ route('finance.resale.vouchers.show', ['source' => $mirrorLine->sourceKey, 'voucher' => $mirrorLine->voucherKey, 'period' => $period->sqid, 'line' => $mirrorLine->morphId]) }}" data-entry-modal-trigger class="contents cursor-pointer" title="{{ __('resale.voucher.open') }}">
                @endif
                <x-status-badge size="xs" :tone="$sure === false ? 'warning' : $link->origin->tone()" :label="($link->voucher_number ?: '—') . ' · ' . \App\Services\Reselling\Register\LicenseMonths::label((float) $link->months, (float) $term)"
                                :title="$link->origin->label() . ($sure === null ? '' : ' · ' . __('resale.link.confidence.' . ($sure ? 'sure' : 'unsure'))) . ($link->note ? ' · ' . $link->note : '')" />
                @if ($mirrorLine !== null)
                    </a>
                @endif
                @if ($sure === false)
                    <x-status-badge size="xs" tone="warning" outline :label="__('resale.link.confidence.unsure')" :title="__('resale.link.confidence.unsure_hint')" />
                @endif
                @if ($previewUrl !== null)
                    <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$previewUrl" :title="__('resale.invoices.preview')" />
                @endif
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
            <span class="block text-xs text-muted">{{ $period->decidedBy?->name ?? __('resale.link.origin.auto') }} · {{ $period->decided_at->fdate() }}</span>
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
