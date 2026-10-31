{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reconcile_board.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abgleichstafel je Produkt (Phase 166, MVP-1447): Perioden (Soll) und
  Rechnungspositionen (Ist) eines Empfängers auf einem Zeitstrahl. Erwartet
  $board (ReconcileBoard::build), $periods (offene Perioden aller Produkte),
  $customer, $canManage, $compact. Die Passung (LineFit) steht an Bezügen und
  Kandidaten; fragliche Zuordnungen verlangen eine Bestätigung.
--}}
@php
    $customerClass = \App\Models\Customer\Customer::class;
    $licenceMonths = \App\Services\Reselling\Register\LicenseMonths::class;
@endphp
<p class="text-xs text-muted mb-2">{{ __('resale.board.hint') }}</p>
@forelse ($board as $block)
    @php
        $product = $block['product'];
        $openMonths = max(0.0, $product['required'] - $product['covered']);
        $term = (float) $product['term'];
    @endphp
    <x-card :title="$product['label']" padding="p-0" class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs border-b border-base-300">
            <span class="text-muted tabular-nums">{{ __('resale.board.summary', ['required' => $licenceMonths::label($product['required'], $term), 'covered' => $licenceMonths::label($product['covered'], $term), 'invoiced' => $licenceMonths::label($product['invoiced'], $term), 'free' => $licenceMonths::label($product['free'], $term)]) }}</span>
            <span class="flex flex-wrap items-center gap-1">
                @if ($product['missing'] > 0.001)
                    <x-status-badge size="xs" tone="error" :label="__('resale.reconcile.verdict.missing', ['amount' => $licenceMonths::label($product['missing'], $term)])" />
                @elseif ($openMonths > 0.001)
                    <x-status-badge size="xs" tone="warning" :label="__('resale.reconcile.verdict.unassigned', ['amount' => $licenceMonths::label($openMonths, $term)])" />
                @endif
                @if ($product['surplus'] > 0.001)
                    <x-status-badge size="xs" tone="info" :label="__('resale.reconcile.verdict.surplus', ['amount' => $licenceMonths::label($product['surplus'], $term)])" />
                @endif
                @if ($product['missing'] <= 0.001 && $openMonths <= 0.001 && $product['surplus'] <= 0.001)
                    <x-status-badge size="xs" tone="success" :label="__('resale.reconcile.verdict.balanced')" />
                @endif
                @if ($product['gap_since'] !== null)
                    <span class="text-warning">{{ __('resale.reconcile.verdict.gap_since', ['date' => $product['gap_since']->fdate()]) }}</span>
                @endif
            </span>
        </div>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('resale.board.col.period') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                    <x-table.th>{{ __('resale.board.col.invoice') }}</x-table.th>
                    <x-table.th>{{ __('resale.board.col.state') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($block['rows'] as $row)
                @if ($row['kind'] === 'period')
                    @php
                        $period = $row['period'];
                        $subscription = $row['subscription'];
                        $open = $row['open'];
                        $required = $period->requiredMonths();
                        $covered = $period->coveredMonths();
                        $periodTerm = $period->termMonths();
                    @endphp
                    <tr @class(['opacity-60' => $row['upcoming'], 'bg-error/5' => $open !== null])>
                        <td class="align-top">
                            <span class="tabular-nums font-medium whitespace-nowrap">{{ $period->label() }}</span>
                            @if ($row['upcoming'])
                                <x-status-badge size="xs" class="ml-1">{{ __('resale.periods.upcoming') }}</x-status-badge>
                            @endif
                            <span class="block text-xs text-muted">
                                <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover">{{ $subscription->holderLabel() }}</a>
                                · {{ $subscription->identityLabel() }}
                            </span>
                        </td>
                        <td class="align-top text-right tabular-nums whitespace-nowrap">
                            {{ $period->expected_sale?->format() ?? '—' }}
                            <span @class(['block text-xs', 'text-success' => $covered >= $required - 0.001 && $required > 0, 'text-warning' => $covered > 0.001 && $covered < $required - 0.001, 'text-error' => $covered <= 0.001 && ! $row['upcoming'] && $period->status !== \App\Enums\Reselling\PeriodStatus::Waived])
                                  title="{{ $compact::compact($covered) }} / {{ $compact::compact($required) }} {{ __('resale.link.months') }}">
                                {{ __('resale.link.licences_of', ['covered' => $compact::compact($covered / max(1, $periodTerm)), 'quantity' => $period->quantity, 'months' => $periodTerm]) }}
                            </span>
                        </td>
                        <td class="align-top text-sm">
                            @foreach ($row['links'] as $entry)
                                @php
                                    $link = $entry['link'];
                                    $mirrorLine = $entry['line'];
                                    $fit = $entry['fit'];
                                @endphp
                                <span class="flex flex-wrap items-center gap-1 mb-0.5">
                                    @if ($mirrorLine !== null)
                                        <a href="{{ route('finance.resale.vouchers.show', ['source' => $mirrorLine->sourceKey, 'voucher' => $mirrorLine->voucherKey, 'period' => $period->sqid, 'line' => $mirrorLine->morphId]) }}" data-entry-modal-trigger class="contents cursor-pointer" title="{{ __('resale.voucher.open') }}">
                                    @endif
                                    <x-status-badge size="xs" :tone="$link->origin->tone()" :label="($link->voucher_number ?: '—') . ' · ' . $licenceMonths::label((float) $link->months, (float) $periodTerm)"
                                                    :title="$link->origin->label() . ($link->note ? ' · ' . $link->note : '')" />
                                    @if ($mirrorLine !== null)
                                        </a>
                                        @if ($mirrorLine->servicePeriodLabel() !== null)
                                            <span class="text-xs text-muted tabular-nums">{{ __('resale.reconcile.service_period') }} {{ $mirrorLine->servicePeriodLabel() }}</span>
                                        @endif
                                    @endif
                                    @if ($fit !== null && $fit->needsConfirmation())
                                        <x-status-badge size="xs" tone="warning" outline :label="$fit->label()" :title="__('resale.fit.link_warning')" />
                                    @endif
                                    @if ($canManage && $period->status !== \App\Enums\Reselling\PeriodStatus::Waived)
                                        <form method="POST" action="{{ route('finance.resale.links.destroy', $link->sqid) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                                        </form>
                                    @endif
                                </span>
                            @endforeach
                            @if ($open !== null)
                                @if ($row['candidates'] === [] && $open['taken'] === [] && $open['foreign'] === [] && $open['voided'] === [])
                                    {{-- raw-markup-ok: Fehlerhinweis je Periode (kein passender Beleg), kein Leerzustand einer Liste --}}
                                    <p class="text-xs text-error">{{ __('resale.reconcile.periods.none', ['amount' => $licenceMonths::label($open['needed'], (float) $periodTerm)]) }}</p>
                                @else
                                    <ul class="space-y-1">
                                        @foreach (array_slice($row['candidates'], 0, 6) as $entry)
                                            @php
                                                $candidate = $entry['candidate'];
                                                $fit = $entry['fit'];
                                                $line = $candidate['row']['line'];
                                            @endphp
                                            <li class="flex flex-wrap items-center justify-between gap-2">
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <a href="{{ route('finance.resale.vouchers.show', ['source' => $line->sourceKey, 'voucher' => $line->voucherKey, 'period' => $period->sqid, 'line' => $line->morphId]) }}" data-entry-modal-trigger class="link link-hover font-mono text-xs" title="{{ __('resale.voucher.open') }}">{{ $line->voucherNumber }}</a>
                                                    <span class="text-xs text-muted tabular-nums">{{ $line->voucherDate?->fdate() }}@if ($line->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $line->servicePeriodLabel() }}@endif</span>
                                                    {{-- Zwillingszeilen eines Belegs (drei × „12 Monat“) stehen einmal mit Anzahl (Pilot 2026-10-31). --}}
                                                    <span class="text-xs">@if ($candidate['count'] > 1)<span class="font-medium tabular-nums">{{ $candidate['count'] }} ×</span> @endif<x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }}</span>
                                                    <span class="text-success text-xs">{{ __('resale.invoices.remaining', ['amount' => $licenceMonths::label($candidate['group_free'], $candidate['row']['per_licence'])]) }}</span>
                                                    @if ($fit->label() !== '')
                                                        <x-status-badge size="xs" :tone="$fit->tone()" :outline="$fit->needsConfirmation()" :label="$fit->label()" />
                                                    @endif
                                                    @if ($line->voucherTextHint !== null)
                                                        <x-status-badge tone="info" size="xs" outline title="{{ $line->voucherText }}">{{ $line->voucherTextHint }}</x-status-badge>
                                                    @endif
                                                    @if ($line->previewUrl !== null)
                                                        <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                                    @endif
                                                </span>
                                                @if ($canManage)
                                                    {{-- Fragliche Passung: Rückfrage vor dem Absenden, der Server prüft dieselbe Regel (confirm_fit). --}}
                                                    <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex items-center gap-1"
                                                          @if ($fit->needsConfirmation()) data-confirm-dialog data-confirm-tone="warning" data-confirm-message="{{ __('resale.fit.confirm', ['voucher' => (string) $line->voucherNumber, 'fit' => $fit->label(), 'period' => $period->label()]) }}" @endif>
                                                        @csrf
                                                        <input type="hidden" name="period_id" value="{{ $period->sqid }}">
                                                        @foreach ($candidate['rows'] as $twin)
                                                            <input type="hidden" name="line_ids[]" value="{{ $twin['line']->key }}">
                                                        @endforeach
                                                        @if ($fit->needsConfirmation())
                                                            <input type="hidden" name="confirm_fit" value="1">
                                                        @endif
                                                        <input type="hidden" name="per_licence" value="{{ number_format($candidate['row']['per_licence'], 2, '.', '') }}">
                                                        <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format(min($open['needed'], $candidate['group_free']) / max(0.01, $candidate['row']['per_licence']), 2, '.', '') }}"
                                                               class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => $compact::compact($candidate['row']['per_licence'])]) }}">
                                                        <x-icon-btn icon="add_link" size="xs" :tone="$fit->needsConfirmation() ? 'ghost' : 'primary'" type="submit" show-label>{{ __('resale.link.action.link') }}</x-icon-btn>
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                        @foreach (array_slice($open['foreign'], 0, 4) as $candidate)
                                            @php
                                                $line = $candidate['row']['line'];
                                                $recipient = $candidate['row']['recipient'] ?? '—';
                                                $targetId = $candidate['row']['recipient_id'];
                                                $targetSqid = $targetId !== null ? \App\Support\Sqid::encode($customerClass, $targetId) : null;
                                            @endphp
                                            <li class="flex flex-wrap items-center justify-between gap-2">
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <x-status-badge size="xs" tone="warning" :label="__('resale.reconcile.periods.foreign', ['recipient' => $recipient])" />
                                                    <a href="{{ route('finance.resale.vouchers.show', ['source' => $line->sourceKey, 'voucher' => $line->voucherKey]) }}" data-entry-modal-trigger class="link link-hover font-mono text-xs" title="{{ __('resale.voucher.open') }}">{{ $line->voucherNumber }}</a>
                                                    <span class="text-xs text-muted tabular-nums">{{ $line->voucherDate?->fdate() }}@if ($line->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $line->servicePeriodLabel() }}@endif · {{ trans_choice('resale.reconcile.distance', $candidate['distance'], ['days' => $candidate['distance']]) }}</span>
                                                    <span class="text-xs">@if ($candidate['count'] > 1)<span class="font-medium tabular-nums">{{ $candidate['count'] }} ×</span> @endif<x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }}</span>
                                                    @if ($line->previewUrl !== null)
                                                        <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                                    @endif
                                                </span>
                                                @if ($canManage && $targetSqid !== null)
                                                    <span class="flex flex-wrap items-center gap-1">
                                                        {{-- Nachfolger nach Insolvenz oder Übernahme: ab diesem Periodenbeginn alles an den anderen Kunden (Phase 166, MVP-1451). --}}
                                                        <x-icon-btn icon="handshake" size="xs" tone="ghost" data-entry-modal-trigger
                                                                    :href="route('finance.resale.takeover.create', ['subscription' => $subscription->sqid, 'customer' => $targetSqid, 'starts_on' => $period->starts_on->toDateString()])"
                                                                    show-label>{{ __('resale.takeover.action_from', ['customer' => $recipient]) }}</x-icon-btn>
                                                        @if (! $subscription->isAssignment())
                                                            {{-- Nur diese Periode an den anderen Kunden abtreten. --}}
                                                            <x-icon-btn icon="call_split" size="xs" tone="ghost" data-entry-modal-trigger
                                                                        :href="route('finance.resale.transfer.create', ['subscription' => $subscription->sqid, 'customer' => $targetSqid, 'quantity' => (int) round($candidate['group_licences']), 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString()])"
                                                                        show-label>{{ __('resale.transfer.action_period', ['customer' => $recipient]) }}</x-icon-btn>
                                                        @endif
                                                        <form method="POST" action="{{ route('finance.resale.reconcile.rehome', $customer) }}" data-confirm-dialog data-confirm-message="{{ __('resale.reconcile.confirm.rehome', ['subscription' => $subscription->label, 'customer' => $recipient]) }}" data-confirm-tone="error">
                                                            @csrf
                                                            <input type="hidden" name="period_id" value="{{ $period->sqid }}">
                                                            <input type="hidden" name="target_id" value="{{ $targetSqid }}">
                                                            <x-icon-btn icon="swap_horiz" size="xs" tone="warning" type="submit" show-label>{{ __('resale.reconcile.action.rehome', ['customer' => $recipient]) }}</x-icon-btn>
                                                        </form>
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                        @foreach (array_slice($open['taken'], 0, 4) as $candidate)
                                            @php $line = $candidate['row']['line']; @endphp
                                            <li class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                                <a href="{{ route('finance.resale.vouchers.show', ['source' => $line->sourceKey, 'voucher' => $line->voucherKey, 'line' => $line->morphId]) }}" data-entry-modal-trigger class="link link-hover font-mono" title="{{ __('resale.voucher.open') }}">{{ $line->voucherNumber }}</a>
                                                <span class="tabular-nums">{{ $line->voucherDate?->fdate() }}</span>
                                                <span>@if ($candidate['count'] > 1)<span class="font-medium tabular-nums">{{ $candidate['count'] }} ×</span> @endif<x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</span>
                                                <span>{{ __('resale.reconcile.periods.taken', ['periods' => implode(' · ', array_unique($candidate['row']['periods']))]) }}</span>
                                            </li>
                                        @endforeach
                                        @foreach (array_slice($open['voided'], 0, 3) as $candidate)
                                            @php $line = $candidate['row']['line']; @endphp
                                            <li class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <x-status-badge size="xs" tone="error" :label="__('resale.reconcile.periods.voided')" />
                                                    <a href="{{ route('finance.resale.vouchers.show', ['source' => $line->sourceKey, 'voucher' => $line->voucherKey, 'line' => $line->morphId]) }}" data-entry-modal-trigger class="link link-hover font-mono" title="{{ __('resale.voucher.open') }}">{{ $line->voucherNumber }}</a>
                                                    <span class="tabular-nums">{{ $line->voucherDate?->fdate() }}</span>
                                                    <span>@if ($candidate['count'] > 1)<span class="font-medium tabular-nums">{{ $candidate['count'] }} ×</span> @endif<x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} · <x-resale.licence-months :value="$candidate['row']['months'] * $candidate['count']" :per-licence="$candidate['row']['per_licence']" /></span>
                                                    @if ($candidate['row']['recipient'] !== null)
                                                        <span>· {{ $candidate['row']['recipient'] }}</span>
                                                    @endif
                                                    @if ($line->previewUrl !== null)
                                                        <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                                    @endif
                                                </span>
                                                @if ($canManage)
                                                    {{-- Storno wegen Insolvenz o. Ä.: die Periode als Forderungsausfall verzichten (Phase 166, MVP-1451). --}}
                                                    <x-icon-btn icon="money_off" size="xs" tone="ghost" data-entry-modal-trigger
                                                                :href="route('finance.resale.periods.waive.create', ['period' => $period->sqid, 'reason' => __('resale.link.reason_voided', ['voucher' => (string) $line->voucherNumber])])"
                                                                show-label>{{ __('resale.board.writeoff') }}</x-icon-btn>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($row['candidates'] === [] && $open['foreign'] === [])
                                        <p class="mt-1 text-xs text-error">{{ __('resale.reconcile.periods.all_taken', ['amount' => $licenceMonths::label($open['needed'], (float) $periodTerm)]) }}</p>
                                    @endif
                                @endif
                            @elseif ($row['links'] === [])
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="align-top">
                            <x-status-badge size="xs" :tone="$period->status->tone()" :label="$period->status->label()" />
                            @if ($period->decided_at !== null)
                                <span class="block text-xs text-muted">{{ $period->decidedBy?->name ?? __('resale.link.origin.auto') }} · {{ $period->decided_at->fdate() }}</span>
                            @endif
                            @if ($period->waived_reason)
                                <span class="block text-xs text-muted max-w-48" title="{{ $period->waived_reason }}">{{ \Illuminate\Support\Str::limit($period->waived_reason, 60) }}</span>
                            @endif
                            @if ($period->isProposedOnly())
                                <span class="block text-xs text-info">{{ __('resale.link.proposed_hint') }}</span>
                            @endif
                        </td>
                        <td class="align-top text-right">
                            @if ($canManage && ! $row['upcoming'])
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
                @else
                    @php
                        $lineRow = $row['line'];
                        $line = $lineRow['line'];
                        $sameProduct = $block['targets'];
                    @endphp
                    <tr class="bg-warning/5">
                        <td class="align-top">
                            <span class="text-muted">{{ __('resale.board.without_period') }}</span>
                            <span class="block text-xs tabular-nums text-muted">{{ $row['date']->fdate() }}</span>
                        </td>
                        <td class="align-top text-right text-muted">—</td>
                        <td class="align-top text-sm">
                            <span class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('finance.resale.vouchers.show', ['source' => $line->sourceKey, 'voucher' => $line->voucherKey, 'line' => $line->morphId]) }}" data-entry-modal-trigger class="link link-hover font-mono text-xs" title="{{ __('resale.voucher.open') }}">{{ $line->voucherNumber }}</a>
                                <span class="text-xs text-muted tabular-nums">{{ $line->voucherDate?->fdate() }}@if ($line->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $line->servicePeriodLabel() }}@endif</span>
                                <span class="text-xs"><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }} = <x-resale.licence-months :value="$lineRow['months']" :per-licence="$lineRow['per_licence']" /></span>
                                @if ($line->previewUrl !== null)
                                    <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                @endif
                            </span>
                            <span class="block text-xs text-muted truncate max-w-md" title="{{ $line->label() }}">{{ $line->label() }}</span>
                        </td>
                        <td class="align-top text-xs">
                            @if ($lineRow['free'] > 0.001)
                                <span class="text-warning">{{ __('resale.invoices.remaining', ['amount' => $licenceMonths::label($lineRow['free'], $lineRow['per_licence'])]) }}</span>
                            @endif
                            @if ($lineRow['linked'] > 0.001)
                                <span class="block text-muted">{{ __('resale.board.line_taken', ['periods' => implode(' · ', array_unique($lineRow['periods']))]) }}</span>
                            @endif
                        </td>
                        <td class="align-top text-right">
                            @if ($canManage && $lineRow['gap'])
                                {{-- Keine Periode dieses Produkts um das Bezugsdatum: das Abo fehlt im Register (z. B. nicht im Anbieter-Export). --}}
                                <x-icon-btn icon="add" size="xs" tone="warning" data-entry-modal-trigger
                                            :href="route('finance.resale.create', ['customer' => $customer->sqid, 'line' => $line->key])"
                                            show-label>{{ __('resale.reconcile.action.create_from_line') }}</x-icon-btn>
                            @endif
                            @if ($canManage && $lineRow['free'] > 0.001 && $periods !== [])
                                <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex flex-wrap items-center justify-end gap-1 mt-1">
                                    @csrf
                                    <input type="hidden" name="line_id" value="{{ $line->key }}">
                                    <select name="period_id" class="select select-xs select-bordered w-64" aria-label="{{ __('resale.field.period') }}">
                                        @foreach ($sameProduct !== [] ? $sameProduct : $periods as $target)
                                            <option value="{{ $target['period']->sqid }}" @selected($loop->first)>{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ $licenceMonths::label($target['needed'], (float) $target['period']->termMonths()) }})</option>
                                        @endforeach
                                        @if ($sameProduct !== [] && count($sameProduct) < count($periods))
                                            <optgroup label="{{ __('resale.reconcile.lines.other_products') }}">
                                                @foreach ($periods as $target)
                                                    @if ($target['product'] !== $lineRow['product'])
                                                        <option value="{{ $target['period']->sqid }}">{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ $licenceMonths::label($target['needed'], (float) $target['period']->termMonths()) }})</option>
                                                    @endif
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <input type="hidden" name="per_licence" value="{{ number_format($lineRow['per_licence'], 2, '.', '') }}">
                                    <input type="number" name="licences" step="0.01" min="0.01" value="{{ number_format(min($lineRow['free'], ($sameProduct[0] ?? $periods[0])['needed']) / max(0.01, $lineRow['per_licence']), 2, '.', '') }}"
                                           class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.licences_field') }}" title="{{ __('resale.link_ui.licences_times', ['months' => $compact::compact($lineRow['per_licence'])]) }}">
                                    {{-- Ohne Kandidatenzeile ist die Passung offen: der Server fragt bei fraglicher Passung nach, das Häkchen bestätigt sie. --}}
                                    <label class="flex items-center gap-1 text-xs text-muted" title="{{ __('resale.fit.confirm_hint') }}">
                                        <input type="checkbox" name="confirm_fit" value="1" class="checkbox checkbox-xs">
                                        {{ __('resale.fit.confirm_short') }}
                                    </label>
                                    <x-icon-btn icon="add_link" size="xs" tone="primary" type="submit" :title="__('resale.link.action.link')" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </x-table>
    </x-card>
@empty
    <x-card padding="p-0" class="mb-4">
        <x-empty-state icon="inventory_2" :title="__('resale.reconcile.products.empty')" compact class="m-4" />
    </x-card>
@endforelse
