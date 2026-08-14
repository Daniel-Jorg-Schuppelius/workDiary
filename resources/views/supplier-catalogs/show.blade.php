{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $source->name . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.catalog.title'))

@section('content')
<x-index-page :subtitle="$source->name">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('supplier-catalogs.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                        :href="route('supplier-catalogs.edit', $source)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-card class="mb-4">
        <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div><span class="opacity-60">{{ __('procurement.field.supplier') }}:</span> <strong>{{ $source->supplier?->name }}</strong></div>
            <div><span class="opacity-60">{{ __('procurement.catalog.col.format') }}:</span> {{ $source->format->label() }}</div>
            <div><span class="opacity-60">{{ __('procurement.catalog.field.delimiter') }}:</span> <code>{{ $source->delimiter }}</code></div>
            <div><span class="opacity-60">{{ __('procurement.catalog.field.decimal_separator') }}:</span> <code>{{ $source->decimal_separator }}</code></div>
            <div><span class="opacity-60">{{ __('procurement.catalog.col.last_import') }}:</span> {{ optional($source->last_imported_at)->format('d.m.Y H:i') ?: '—' }}</div>
        </div>
        @if ($canManage && ($source->hasRemoteFetch() || $source->hasPunchout()))
            <div class="mt-3 flex flex-wrap items-end gap-3">
                @if ($source->hasRemoteFetch())
                    <form method="POST" action="{{ route('supplier-catalogs.fetch', $source) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline gap-1">
                            <span class="material-symbols-rounded text-base">cloud_download</span>{{ __('procurement.catalog.remote.fetch') }}
                        </button>
                    </form>
                @endif
                @if ($source->hasPunchout())
                    <form method="GET" action="{{ route('supplier-catalogs.punchout', $source) }}" class="flex items-end gap-2">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.warehouse') }}</label>
                            <select name="warehouse" class="select select-sm select-bordered" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->sqid }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline gap-1">
                            <span class="material-symbols-rounded text-base">shopping_cart_checkout</span>{{ __('procurement.oci.punchout.action') }}
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </x-card>

    @if ($canManage)
        @php
            $fmt = $source->format->value;
            $structured = in_array($fmt, ['datanorm', 'bmecat'], true);
            $fileLabel = match ($fmt) {
                'datanorm' => __('procurement.catalog.datanorm_file'),
                'bmecat' => __('procurement.catalog.bmecat_file'),
                default => __('procurement.catalog.csv_file'),
            };
        @endphp

        @if ($fmt === 'csv')
            <x-card class="mb-4">
                <h2 class="font-semibold mb-2">{{ __('procurement.catalog.shopinfo.title') }}</h2>
                <form method="POST" action="{{ route('supplier-catalogs.shopinfo', $source) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="file" name="shopinfo" accept=".xml,text/xml,application/xml" required
                           class="file-input file-input-bordered file-input-sm max-w-md" />
                    <button type="submit" class="btn btn-sm">{{ __('procurement.catalog.shopinfo.action') }}</button>
                </form>
                <p class="text-xs opacity-60 mt-2">{{ __('procurement.catalog.shopinfo.hint') }}</p>
                @if (session('shopinfo_url'))
                    <p class="text-sm mt-2">{{ __('procurement.catalog.shopinfo.url_label') }}:
                        <a href="{{ session('shopinfo_url') }}" target="_blank" rel="noopener" class="link">{{ session('shopinfo_url') }}</a></p>
                @endif
            </x-card>
        @endif

        <x-card class="mb-4">
            <h2 class="font-semibold mb-3">{{ __('procurement.catalog.import_title') }}</h2>
            <form method="POST" action="{{ route('supplier-catalogs.import', $source) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="label-text font-medium">{{ $fileLabel }}</label>
                    <input type="file" name="catalog_csv" required
                           class="file-input file-input-bordered file-input-sm w-full max-w-md" />
                </div>

                @if ($structured)
                    <p class="text-sm opacity-70">{{ $fmt === 'bmecat' ? __('procurement.catalog.bmecat_hint') : __('procurement.catalog.datanorm_hint') }}</p>
                @else
                    <p class="text-sm opacity-70">{{ __('procurement.catalog.mapping_hint') }}</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($mappingFields as $field)
                            <x-input-field name="mapping[{{ $field }}]"
                                           :label="__('procurement.catalog.map.' . $field)"
                                           :required="in_array($field, ['external_no', 'name'], true)"
                                           :value="old('mapping.' . $field, session('shopinfo_mapping.' . $field))" />
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="btn btn-primary btn-sm">{{ __('procurement.catalog.action.import') }}</button>
            </form>
        </x-card>
    @endif

    @if ($imports->isNotEmpty())
        <x-card class="mb-4">
            <h2 class="font-semibold mb-2">{{ __('procurement.catalog.history.title') }}</h2>
            <x-table>
                <x-slot:head>
                    <th>{{ __('procurement.catalog.history.when') }}</th>
                    <th>{{ __('procurement.catalog.history.trigger') }}</th>
                    <th>{{ __('procurement.catalog.col.status') }}</th>
                    <th>{{ __('procurement.catalog.history.balance') }}</th>
                </x-slot:head>
                @foreach ($imports as $imp)
                    <tr>
                        <td class="text-sm">{{ $imp->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-sm">{{ __('procurement.catalog.history.trigger_' . $imp->trigger) }}</td>
                        <td><x-status-badge :tone="$imp->status === 'success' ? 'success' : 'error'">{{ __('procurement.catalog.history.status_' . $imp->status) }}</x-status-badge></td>
                        <td class="text-sm tabular-nums">
                            @if ($imp->status === 'success')
                                +{{ $imp->created }} / ~{{ $imp->updated }} / !{{ $imp->price_changed }} / ×{{ $imp->discontinued }}
                            @else
                                <span class="text-error">{{ \Illuminate\Support\Str::limit((string) $imp->error, 80) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav class="w-fit mb-3" :items="collect([['label' => __('Alle'), 'href' => route('supplier-catalogs.show', $source), 'active' => $status === 'all']])
        ->concat(collect($statuses)->map(fn($st) => [
            'label' => $st->label(),
            'href' => route('supplier-catalogs.show', [$source, 'status' => $st->value]),
            'active' => $status === $st->value,
        ]))->all()" />

    @if ($items->total() === 0)
        <x-empty-state framed :title="__('procurement.catalog.no_items')" />
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('procurement.catalog.map.external_no') }}</th>
                    <th>{{ __('procurement.catalog.map.name') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.map.purchase_price') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.suggestion') }}</th>
                    <th>{{ __('procurement.catalog.col.internal_article') }}</th>
                    <th>{{ __('procurement.catalog.col.status') }}</th>
                    @if ($canManage)<th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>@endif
                </x-slot:head>
                @foreach ($items as $item)
                    @php($tone = match ($item->status) {
                        \App\Enums\Procurement\CatalogItemStatus::Linked => 'success',
                        \App\Enums\Procurement\CatalogItemStatus::Conflict => 'error',
                        \App\Enums\Procurement\CatalogItemStatus::Discontinued => 'neutral',
                        default => 'info',
                    })
                    <tr>
                        <td class="font-mono text-xs">{{ $item->external_no }}</td>
                        <td>
                            {{ $item->name }}
                            @if ($item->gtin)<div class="font-mono text-xs opacity-50">{{ $item->gtin }}</div>@endif
                            @if ($item->classification_code || $item->image_url || $item->datasheet_url)
                                <div class="flex items-center gap-2 text-xs mt-0.5">
                                    @if ($item->classification_code)<span class="opacity-60">{{ $item->classification_system }} {{ $item->classification_code }}</span>@endif
                                    @if ($item->image_url)<a href="{{ $item->image_url }}" target="_blank" rel="noopener" class="link">{{ __('procurement.catalog.media.image') }}</a>@endif
                                    @if ($item->datasheet_url)<a href="{{ $item->datasheet_url }}" target="_blank" rel="noopener" class="link">{{ __('procurement.catalog.media.datasheet') }}</a>@endif
                                </div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $item->purchase_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->purchase_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' ' . $item->currency->value : '—' }}
                            @if (($item->price_tiers_count ?? 0) > 0)
                                <div class="text-xs opacity-50">+{{ $item->price_tiers_count }} {{ __('procurement.catalog.tiers') }}</div>
                            @endif
                        </td>
                        @php($sug = $suggestions[$item->id] ?? null)
                        <td class="text-right tabular-nums text-sm">
                            @if ($sug)
                                <span @class(['text-error font-medium' => $sug['below_min']])>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $sug['price'], 2, withThousandsSeparator: true) }}</span>
                                <span class="opacity-50 text-xs">({{ $sug['margin'] }}%)</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">{{ $item->article?->name ?: '—' }}</td>
                        <td><x-status-badge :tone="$tone">{{ $item->status->label() }}</x-status-badge></td>
                        @if ($canManage)
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($item->article_id)
                                        @if ($sug)
                                            <form method="POST" action="{{ route('supplier-catalogs.items.apply-price', $item) }}">@csrf
                                                <x-icon-btn icon="sell" size="xs" tone="success" type="submit"
                                                            :title="($approvalMode ?? 'direct') === 'four_eyes' ? __('procurement.approval.action.request') : __('procurement.margin.action.apply')" />
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('supplier-catalogs.items.unlink', $item) }}">@csrf
                                            <x-icon-btn icon="link_off" size="xs" type="submit" :title="__('procurement.catalog.action.unlink')" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('supplier-catalogs.items.propose', $item) }}">@csrf
                                            <x-icon-btn icon="lightbulb" size="xs" type="submit" :title="__('procurement.catalog.action.propose')" />
                                        </form>
                                        <x-icon-btn icon="link" size="xs" tone="primary" data-entry-modal-trigger
                                                    :href="route('supplier-catalogs.items.link-form', $item)" :title="__('procurement.catalog.action.link')" />
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$items" standing />
    @endif
</x-index-page>
@endsection
