{{--
  Created on   : Tue Oct 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _voucher_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Beleg hinter einem Rechnungsbezug (Nachbesserung 2026-10-27): Kopf, Text,
  Leistungszeitraum, alle Positionen mit ihrem Verbrauch und — aus einer
  Periode geöffnet — der Vergleich, ob Datum, Leistung und Artikel passen.
  Nur lesend; Belegbild und Sprung ins Programm wie in der Periodenzeile.
--}}
@php
    use App\Services\Reselling\Mirror\MirrorLine;
    $statusTone = match ($voucher->voucherStatus) {
        MirrorLine::STATUS_PAID => 'success',
        MirrorLine::STATUS_VOIDED => 'error',
        MirrorLine::STATUS_DRAFT => 'warning',
        default => 'info',
    };
    $check = static fn(?bool $ok): array => $ok === null ? ['help', 'text-muted'] : ($ok ? ['check_circle', 'text-success'] : ['error', 'text-warning']);
@endphp
<x-modal :title="__('resale.voucher.title', ['number' => $voucher->voucherNumber ?? '—'])" icon="receipt_long" tone="info" size="wide">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-status-badge size="xs" :tone="$statusTone" :label="__('resale.voucher.status.' . $voucher->voucherStatus)" />
        @if ($voucher->isCreditNote)
            <x-status-badge size="xs" tone="warning" outline :label="__('resale.voucher.credit_note')" />
        @endif
        <span class="tabular-nums">{{ $voucher->voucherDate?->fdate() ?? '—' }}</span>
        <span class="text-muted">·</span>
        <span>{{ $voucher->recipientName ?? '—' }}</span>
        <span class="text-muted">· {{ __('resale.mirror.source_' . $voucher->sourceKey) }}</span>
        @if ($voucher->servicePeriodLabel() !== null)
            <span class="text-muted">· {{ __('resale.voucher.service') }} {{ $voucher->servicePeriodLabel() }}</span>
        @endif
        <span class="ml-auto flex items-center gap-1">
            @if ($voucher->previewUrl)
                <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$voucher->previewUrl" :title="__('resale.invoices.preview')" show-label>{{ __('resale.invoices.preview') }}</x-icon-btn>
            @endif
            @if ($voucher->permalink)
                <x-external-link :url="$voucher->permalink" plain class="btn btn-ghost btn-xs" :title="__('resale.mirror.open_source', ['source' => __('resale.mirror.source_' . $voucher->sourceKey)])"><x-icon name="open_in_new" size="1rem" /></x-external-link>
            @endif
        </span>
    </div>

    @if ($comparison !== null && $period !== null)
        @php $line = $comparison['line']; @endphp
        <div @class(['rounded-box border p-3 text-sm space-y-1', 'border-success/50 bg-success/5' => $comparison['sure'], 'border-warning/50 bg-warning/5' => ! $comparison['sure']])>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium">{{ __('resale.voucher.compare.title') }}: {{ $period->subscription->label }} · {{ $period->subscription->holderLabel() }} · {{ $period->label() }}</span>
                <x-status-badge size="xs" :tone="$comparison['sure'] ? 'success' : 'warning'" :label="__('resale.voucher.compare.' . ($comparison['sure'] ? 'sure' : 'check'))" />
            </div>
            <ul class="space-y-0.5">
                @php [$icon, $tone] = $check($comparison['days'] === null ? null : $comparison['in_window']); @endphp
                <li class="flex items-center gap-2"><x-icon :name="$icon" size="1rem" :class="$tone" />
                    <span>{{ $comparison['days'] === null ? __('resale.voucher.compare.date_none') : __('resale.voucher.compare.' . ($comparison['in_window'] ? 'date_ok' : 'date_off'), ['days' => $comparison['days']]) }}</span></li>
                @php [$icon, $tone] = $check($comparison['service']); @endphp
                <li class="flex items-center gap-2"><x-icon :name="$icon" size="1rem" :class="$tone" />
                    <span>{{ __('resale.voucher.compare.' . ($comparison['service'] === null ? 'service_none' : ($comparison['service'] ? 'service_ok' : 'service_off'))) }}</span></li>
                @php [$icon, $tone] = $check($comparison['article']); @endphp
                <li class="flex items-center gap-2"><x-icon :name="$icon" size="1rem" :class="$tone" />
                    <span>{{ __('resale.voucher.compare.' . ($comparison['article'] ? 'article_ok' : 'article_off')) }}</span></li>
                <li class="flex items-center gap-2"><x-icon name="numbers" size="1rem" class="text-muted" />
                    <span>{{ __('resale.voucher.compare.line', ['quantity' => \App\View\Components\Resale\LicenceMonths::compact($line->quantity), 'unit' => $line->unitName ?? '', 'period' => $period->quantity, 'months' => $period->termMonths()]) }}</span></li>
            </ul>
        </div>
    @endif

    @if ($voucher->voucherText)
        <div>
            <div class="text-xs text-muted mb-1">{{ __('resale.voucher.text') }}</div>
            <pre class="whitespace-pre-wrap font-sans text-xs max-h-32 overflow-auto rounded-box border border-base-300 p-2 bg-base-200/40">{{ $voucher->voucherText }}</pre>
        </div>
    @endif

    <x-table bare :caption="__('resale.voucher.lines')">
        <x-slot:head>
            <tr>
                <x-table.th>#</x-table.th>
                <x-table.th>{{ __('resale.invoices.line') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale.invoices.unit_net') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale.voucher.total_net') }}</x-table.th>
                <x-table.th>{{ __('resale.invoices.linked') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($voucher->lines as $line)
            @php $use = $consumed[$line->identity()] ?? null; @endphp
            <tr @class(['bg-base-200/60' => $line->morphId === $highlight])>
                <td class="text-xs text-muted tabular-nums">{{ $line->position }}</td>
                <td class="text-sm">
                    {{ $line->label() }}
                    @if ($line->articleIsLicence)
                        <x-status-badge size="xs" tone="info" outline :label="__('resale.voucher.licence')" />
                    @endif
                    @if ($line->description)
                        <span class="block text-xs text-muted">{{ \Illuminate\Support\Str::limit($line->description, 160) }}</span>
                    @endif
                    @if ($line->servicePeriodLabel() !== null)
                        <span class="block text-xs text-muted">{{ __('resale.voucher.service') }} {{ $line->servicePeriodLabel() }}</span>
                    @endif
                </td>
                <td class="text-right tabular-nums whitespace-nowrap"><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</td>
                <td class="text-right tabular-nums whitespace-nowrap">{{ $line->unitNet->withScale(2)->format() }}</td>
                <td class="text-right tabular-nums whitespace-nowrap">{{ $line->totalNet->format() }}</td>
                <td class="text-xs">
                    @if ($use !== null && $use['periods'] !== [])
                        @foreach ($use['periods'] as $target)
                            <span class="block">{{ $target }}</span>
                        @endforeach
                    @else
                        <span class="text-muted">{{ __('resale.voucher.none_consumed') }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" :title="__('resale.voucher.no_lines')" compact />
        @endforelse
    </x-table>
</x-modal>
