{{--
  Created on   : Sat Oct 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _omd.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Open Masterdata (MVP-1072): Artikel beim Großhändler abfragen und übernehmen. Erwartet: $source, $omd, $canManage --}}
@php
    $money = static fn ($value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 2, withThousandsSeparator: true);
    $product = $omd['product'];
    $record = $omd['record'];
    $extra = (array) ($record['extra_attributes'] ?? []);
@endphp
<x-card class="mb-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-semibold">{{ __('procurement.omd.lookup.title') }}</h2>
            <p class="text-xs opacity-60">{{ __('procurement.omd.lookup.hint') }}</p>
        </div>
        @if ($canManage && $omd['configured'])
            <x-action-form :action="route('supplier-catalogs.omd.refresh', $source)" :confirm="__('procurement.omd.lookup.refresh_confirm')">
                <x-icon-btn icon="currency_exchange" size="sm" tone="ghost" type="submit" show-label>{{ __('procurement.omd.lookup.refresh') }}</x-icon-btn>
            </x-action-form>
        @endif
    </div>

    @unless ($omd['configured'])
        <div class="alert alert-warning text-sm mt-3">{{ __('procurement.omd.lookup.not_configured') }}</div>
    @else
        <form method="GET" action="{{ route('supplier-catalogs.show', $source) }}" class="mt-3 grid gap-3 md:grid-cols-4 items-end">
            <x-select-field name="omd_by" :label="__('procurement.omd.lookup.by')">
                @foreach (['pid', 'gtin', 'manufacturer'] as $by)
                    <option value="{{ $by }}" @selected($omd['by'] === $by)>{{ __('procurement.omd.lookup.by_' . $by) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="omd_value" required maxlength="100" :label="__('procurement.omd.lookup.value')" :value="$omd['value']" />
            <x-input-field name="omd_manufacturer_id" maxlength="100" :label="__('procurement.omd.lookup.manufacturer_id')" :value="$omd['manufacturer_id']" :hint="__('procurement.omd.lookup.manufacturer_id_hint')" />
            <div class="flex items-end gap-2">
                <x-input-field name="omd_manufacturer_id_type" maxlength="20" :label="__('procurement.omd.lookup.manufacturer_id_type')" :value="$omd['manufacturer_id_type']" />
                <button type="submit" class="btn btn-sm btn-primary">{{ __('procurement.omd.lookup.action') }}</button>
            </div>
        </form>

        @if ($omd['error'] !== null)
            <div class="alert alert-error text-sm mt-3">{{ $omd['error'] }}</div>
        @elseif ($product !== null)
            <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto]">
                <div class="space-y-2 text-sm">
                    @if ($product->isAlternative() || $product->isFollowup())
                        <div class="alert alert-warning text-sm">{{ __($product->isFollowup() ? 'procurement.omd.lookup.followup' : 'procurement.omd.lookup.alternative', ['no' => $product->supplierPid()]) }}</div>
                    @endif
                    <div class="text-base font-semibold">{{ $record['name'] ?? $product->supplierPid() }}</div>
                    <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr]">
                        <dt class="text-muted">{{ __('procurement.catalog.map.external_no') }}</dt><dd class="font-mono">{{ $product->supplierPid() }}</dd>
                        @if (! empty($record['gtin']))
                            <dt class="text-muted">{{ __('procurement.catalog.map.gtin') }}</dt><dd class="font-mono">{{ $record['gtin'] }}</dd>
                        @endif
                        @if (! empty($record['manufacturer_no']))
                            <dt class="text-muted">{{ __('procurement.catalog.map.manufacturer_no') }}</dt><dd>{{ $record['manufacturer_no'] }}</dd>
                        @endif
                        @if (! empty($record['purchase_price']))
                            <dt class="text-muted">{{ __('procurement.omd.lookup.net_price') }}</dt>
                            <dd>{{ $money($record['purchase_price']) }} {{ $record['currency'] ?? 'EUR' }} / {{ $record['price_unit_amount'] ?? 1 }} {{ $record['unit'] ?? '' }}@if (($record['price_type'] ?? '') === 'list') <span class="text-xs opacity-60">({{ __('procurement.omd.lookup.list_only') }})</span>@endif</dd>
                        @elseif (($record['price_type'] ?? '') === 'on_request')
                            <dt class="text-muted">{{ __('procurement.omd.lookup.net_price') }}</dt><dd>{{ __('procurement.omd.lookup.on_request') }}</dd>
                        @endif
                        @if (! empty($record['list_price']))
                            <dt class="text-muted">{{ __('procurement.catalog.map.list_price') }}</dt><dd>{{ $money($record['list_price']) }} {{ $record['currency'] ?? 'EUR' }}</dd>
                        @endif
                        @if (! empty($extra['omd_rrp']))
                            <dt class="text-muted">{{ __('procurement.omd.lookup.rrp') }}</dt><dd>{{ $money($extra['omd_rrp']) }} {{ $record['currency'] ?? 'EUR' }}</dd>
                        @endif
                        @if (isset($record['lead_time_days']))
                            <dt class="text-muted">{{ __('procurement.catalog.map.lead_time_days') }}</dt><dd>{{ $record['lead_time_days'] }}</dd>
                        @endif
                        @if (! empty($extra['omd_expiring']) && strtolower((string) $extra['omd_expiring']) !== 'no' && (string) $extra['omd_expiring'] !== 'false')
                            <dt class="text-muted">{{ __('procurement.omd.lookup.expiring') }}</dt><dd>{{ $extra['omd_expiring_date'] ?? $extra['omd_expiring'] }}@if (! empty($extra['omd_successor'])) · {{ __('procurement.omd.lookup.successor', ['no' => $extra['omd_successor']]) }}@endif</dd>
                        @endif
                        @if (! empty($record['product_url']))
                            <dt class="text-muted">{{ __('procurement.omd.lookup.deep_link') }}</dt><dd><a href="{{ $record['product_url'] }}" target="_blank" rel="noopener" class="link break-all">{{ $record['product_url'] }}</a></dd>
                        @endif
                    </dl>
                    @if (! empty($record['description']))
                        <p class="text-sm opacity-80 line-clamp-3">{{ $record['description'] }}</p>
                    @endif
                    @if (! empty($extra['omd_documents']))
                        <ul class="text-sm">
                            @foreach (array_slice($extra['omd_documents'], 0, 8) as $document)
                                <li><a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="link">{{ __('procurement.omd.document_types.' . ($document['type'] ?? 'other'), [], null) ?: ($document['type'] ?? '') }}: {{ $document['description'] ?? $document['filename'] ?? $document['url'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($extra['etim']))
                        <div class="flex flex-wrap gap-1">
                            @foreach (array_slice($extra['etim'], 0, 12, true) as $name => $value)
                                <span class="badge badge-sm badge-ghost">{{ $name }}: {{ $value }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        @if ($omd['item'] !== null)
                            <x-status-badge tone="info" size="sm">{{ __('procurement.omd.lookup.already_listed', ['status' => $omd['item']->status->label()]) }}</x-status-badge>
                        @endif
                        @if ($canManage)
                            <form method="POST" action="{{ route('supplier-catalogs.omd.adopt', $source) }}">
                                @csrf
                                <input type="hidden" name="omd_by" value="{{ $omd['by'] }}">
                                <input type="hidden" name="omd_value" value="{{ $omd['value'] }}">
                                <input type="hidden" name="omd_manufacturer_id" value="{{ $omd['manufacturer_id'] }}">
                                <input type="hidden" name="omd_manufacturer_id_type" value="{{ $omd['manufacturer_id_type'] }}">
                                <button type="submit" class="btn btn-sm btn-primary">{{ __($omd['item'] !== null ? 'procurement.omd.lookup.update_item' : 'procurement.omd.lookup.adopt') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
                @if (! empty($record['image_url']))
                    <img src="{{ $record['image_url'] }}" alt="" class="max-h-40 rounded border border-base-300 object-contain" loading="lazy">
                @endif
            </div>
        @endif
    @endunless
</x-card>
