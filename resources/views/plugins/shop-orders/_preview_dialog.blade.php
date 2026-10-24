{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau des ersten Shop-Abgleichs (MVP-1328), schreibt nichts. Variablen aus
  ShopOrderAdminController::preview(): $rows (RemoteOrder, known, customer,
  skipped), $more, $since, $provider, $routePrefix, $confirmed.
--}}
<x-modal
    :title="__('integration.shop_orders.preview.title', ['provider' => $provider])"
    icon="preview"
    tone="primary"
    size="wide"
    :action="$confirmed ? null : route($routePrefix . '.confirm')"
    method="POST"
    :submit-label="__('integration.shop_orders.preview.confirm')"
>
    <p class="text-sm text-muted">{{ __('integration.shop_orders.preview.intro', ['date' => $since->fdate()]) }}</p>
    @if ($confirmed)
        <div role="note" class="alert alert-success text-sm"><x-icon name="check_circle" /><span>{{ __('integration.shop_orders.preview.confirmed_hint') }}</span></div>
    @endif

    @if ($rows === [])
        <x-empty-state icon="storefront" :title="__('integration.shop_orders.preview.empty')" compact />
    @else
        <x-table bare size="sm">
            <x-slot:head>
                <tr>
                    <th>{{ __('integration.shop_orders.orders.col.order') }}</th>
                    <th>{{ __('integration.shop_orders.orders.col.date') }}</th>
                    <th>{{ __('integration.shop_orders.orders.col.buyer') }}</th>
                    <th>{{ __('integration.shop_orders.orders.col.status') }}</th>
                    <th class="text-right">{{ __('integration.shop_orders.orders.col.total') }}</th>
                    <th>{{ __('integration.shop_orders.preview.col.result') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                @php $order = $row['order']; @endphp
                <tr>
                    <td class="font-mono text-sm whitespace-nowrap">#{{ $order->number ?? $order->id }}</td>
                    <td class="text-sm whitespace-nowrap">{{ $order->orderedAt?->fdatetime() ?? '—' }}</td>
                    <td class="text-sm">{{ $order->buyerCompany ?? $order->buyerName ?? $order->buyerEmail ?? '—' }}</td>
                    <td><x-status-badge :tone="$order->status->tone()" size="xs">{{ $order->status->label() }}</x-status-badge></td>
                    <td class="text-right font-mono text-sm whitespace-nowrap">{{ $order->grossMoney()?->format() ?? '—' }}</td>
                    <td class="text-sm">
                        @if ($row['skipped'])
                            <x-status-badge tone="plain" size="xs">{{ __('integration.shop_orders.preview.state.skipped') }}</x-status-badge>
                        @elseif ($row['known'])
                            <x-status-badge tone="plain" size="xs">{{ __('integration.shop_orders.preview.state.known') }}</x-status-badge>
                        @else
                            <x-status-badge tone="info" size="xs">{{ __('integration.shop_orders.preview.state.new') }}</x-status-badge>
                        @endif
                        <div class="text-xs text-muted">{{ $row['customer'] !== null ? __('integration.shop_orders.preview.customer_linked', ['name' => $row['customer']]) : __('integration.shop_orders.preview.customer_open') }}</div>
                    </td>
                </tr>
            @endforeach
        </x-table>
        @if ($more)
            <p class="text-xs text-muted">{{ __('integration.shop_orders.preview.more', ['count' => count($rows)]) }}</p>
        @endif
    @endif
</x-modal>
