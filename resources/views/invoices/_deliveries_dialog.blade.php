{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _deliveries_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fertigungsauslieferungen in den Rechnungsentwurf übernehmen (Feature 160, MVP-858).
     Variablen: $invoice, $candidates (delivery, block_reason, reserved_by), $range --}}
<x-modal
    :title="__('invoicing.free.title.deliveries')"
    :eyebrow="$invoice->number"
    icon="local_shipping"
    tone="primary"
    :action="route('invoices.deliveries.attach', $invoice)"
    method="POST"
    :submit-label="__('invoicing.free.action.attach_deliveries')"
    size="lg">

    <p class="text-sm text-base-content/70">{{ __('invoicing.free.hint.deliveries', ['from' => $range['from']->fdate(), 'to' => $range['to']->fdate()]) }}</p>

    <x-table bare class="mt-3">
        <x-slot:head>
            <tr>
                <th class="w-8"></th>
                <th>{{ __('invoicing.free.field.delivery') }}</th>
                <th>{{ __('invoicing.free.field.order') }}</th>
                <th class="text-right">{{ __('Menge') }}</th>
                <th class="text-right">{{ __('Einzelpreis') }}</th>
                <th>{{ __('invoicing.free.field.delivered_on') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($candidates as $row)
            @php($delivery = $row['delivery'])
            <tr @class(['opacity-60' => $row['block_reason'] !== null])>
                <td>
                    <input type="checkbox" name="delivery_ids[]" value="{{ $delivery->sqid }}" class="checkbox checkbox-sm"
                           id="delivery-{{ $delivery->sqid }}" @disabled($row['block_reason'] !== null)
                           @checked(in_array($delivery->sqid, (array) old('delivery_ids', []), true))
                           aria-label="{{ $delivery->name_snapshot }}">
                </td>
                <td>
                    <label for="delivery-{{ $delivery->sqid }}" class="font-medium">{{ $delivery->name_snapshot }}</label>
                    @if ($delivery->sku_snapshot)<div class="text-xs text-muted">{{ $delivery->sku_snapshot }}</div>@endif
                    @if ($row['block_reason'] !== null)
                        <div class="text-xs text-warning">{{ $row['block_reason'] }}</div>
                    @endif
                </td>
                <td class="text-sm">{{ $delivery->order?->number ?? '—' }}</td>
                <td class="text-right text-sm tabular-nums">{{ $delivery->quantity?->getNumericValue() }} {{ $delivery->unit }}</td>
                <td class="text-right text-sm tabular-nums">{{ $delivery->unit_price_snapshot !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($delivery->unit_price_snapshot->toFloat(), 2, withThousandsSeparator: true) . ' ' . $delivery->currency?->value : '—' }}</td>
                <td class="text-sm">{{ $delivery->delivered_at?->fdate() ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty icon="local_shipping" :colspan="6" :title="__('invoicing.free.empty.deliveries')" :message="__('invoicing.free.hint.deliveries_empty')" compact />
        @endforelse
    </x-table>

    <p class="mt-3 text-xs text-muted">{{ __('invoicing.free.hint.deliveries_rules') }}</p>
</x-modal>
