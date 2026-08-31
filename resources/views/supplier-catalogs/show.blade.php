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
            <x-icon-btn icon="library_add" size="sm" data-entry-modal-trigger
                        :href="route('supplier-catalogs.adopt-form', $source)" show-label>{{ __('procurement.catalog.action.adopt') }}</x-icon-btn>
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
                            <label for="warehouse" class="fieldset-label">{{ __('inventory.field.warehouse') }}</label>
                            <select id="warehouse" name="warehouse" class="select select-sm select-bordered" required>
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
                'xlsx' => __('procurement.catalog.xlsx_file'),
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
                    @if ($fmt === 'datanorm')
                        <div class="max-w-md">
                            <label class="label-text font-medium">{{ __('procurement.catalog.import_mode') }}</label>
                            <select name="import_mode" class="select select-bordered select-sm w-full">
                                <option value="auto">{{ __('procurement.catalog.import_mode_auto') }}</option>
                                <option value="snapshot">{{ __('procurement.catalog.import_mode_snapshot') }}</option>
                                <option value="delta">{{ __('procurement.catalog.import_mode_delta') }}</option>
                            </select>
                        </div>
                    @endif
                @else
                    <p class="text-sm opacity-70">{{ __('procurement.catalog.mapping_hint') }}</p>
                    @if ($fmt === 'xlsx')
                        <p class="text-xs opacity-60">{{ __('procurement.catalog.xlsx_hint') }}</p>
                    @endif
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($mappingFields as $field)
                            <x-input-field name="mapping[{{ $field }}]"
                                           :label="__('procurement.catalog.map.' . $field)"
                                           :required="in_array($field, ['external_no', 'name'], true)"
                                           :value="old('mapping.' . $field, session('shopinfo_mapping.' . $field, $source->mapping[$field] ?? null))" />
                        @endforeach
                    </div>

                    <p class="text-sm font-medium mt-2">{{ __('procurement.catalog.attr.legend') }}</p>
                    <p class="text-xs opacity-60">{{ __('procurement.catalog.attr.hint') }}</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @for ($i = 0; $i < max(3, count($attrMapping) + 1); $i++)
                            <x-input-field name="mapping_attr[{{ $i }}][code]"
                                           :label="__('procurement.catalog.attr.code')"
                                           :value="old('mapping_attr.' . $i . '.code', $attrMapping[$i]['code'] ?? null)" />
                            <x-input-field name="mapping_attr[{{ $i }}][column]"
                                           :label="__('procurement.catalog.attr.column')"
                                           :value="old('mapping_attr.' . $i . '.column', $attrMapping[$i]['column'] ?? null)" />
                        @endfor
                    </div>
                @endif

                <button type="submit" class="btn btn-primary btn-sm">{{ __('procurement.catalog.action.import') }}</button>
            </form>
        </x-card>
    @endif

    @if ($imports->isNotEmpty())
        <x-card class="mb-4">
            <h2 class="font-semibold mb-2">{{ __('procurement.catalog.history.title') }}</h2>
            <x-table :bare="true">
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
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <x-tab-nav class="w-fit" :items="collect([['label' => __('Alle'), 'href' => route('supplier-catalogs.show', [$source, 'q' => request('q')]), 'active' => $status === 'all']])
            ->concat(collect($statuses)->map(fn($st) => [
                'label' => $st->label(),
                'href' => route('supplier-catalogs.show', [$source, 'status' => $st->value, 'q' => request('q')]),
                'active' => $status === $st->value,
            ]))->all()" />
        {{-- MVP-601: Suche über Artikelnummer/Name/Matchcode/GTIN/Hersteller-Nr. --}}
        <form method="GET" action="{{ route('supplier-catalogs.show', $source) }}" class="flex items-center gap-2">
            @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <input aria-label="{{ __('procurement.catalog.search_placeholder') }}" type="search" name="q" value="{{ request('q') }}" class="input input-sm input-bordered w-56"
                   placeholder="{{ __('procurement.catalog.search_placeholder') }}">
            <button type="submit" class="btn btn-sm">{{ __('Suchen') }}</button>
        </form>
    </div>

    @if ($items->total() === 0)
        <x-empty-state framed :title="__('procurement.catalog.no_items')" />
    @else
        <x-card padding="p-0">
            <x-table :bare="true">
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
                            @if ($item->extra_attributes)
                                {{-- MVP-601: strukturierte DATANORM-Extras lesbar, interne Vormerkungen ausgeblendet. --}}
                                @php($extraLine = collect($item->extra_attributes)->map(function ($v, $k) {
                                    if ($k === 'datanorm_raw_surcharges' && is_array($v)) {
                                        return __('procurement.catalog.extras.raw_surcharges') . ': ' . collect($v)->map(fn ($s) => is_array($s) ? ($s['material'] ?? '?') : '?')->implode(', ');
                                    }
                                    if ($k === 'datanorm_worktimes' && is_array($v)) {
                                        return __('procurement.catalog.extras.worktimes') . ': ' . collect($v)->map(fn ($w) => is_array($w) ? (($w['minutes'] ?? 0) . ' min') : '')->filter()->implode(', ');
                                    }
                                    if ($k === 'datanorm_graphics' && is_array($v)) {
                                        return __('procurement.catalog.extras.graphics') . ': ' . collect($v)->map(fn ($g) => is_array($g) ? ($g['file'] ?? '') : '')->filter()->implode(', ');
                                    }
                                    return is_scalar($v) ? $k . ': ' . $v : null;
                                })->filter()->implode(' · '))
                                @if ($extraLine !== '')<div class="text-xs opacity-50">{{ $extraLine }}</div>@endif
                            @endif
                            @if ($item->gtin || $item->matchcode)<div class="font-mono text-xs opacity-50">{{ $item->gtin }}{{ $item->gtin && $item->matchcode ? ' · ' : '' }}{{ $item->matchcode }}</div>@endif
                            @if ($item->classification_code || $item->image_url || $item->datasheet_url)
                                <div class="flex items-center gap-2 text-xs mt-0.5">
                                    @if ($item->classification_code)<span class="opacity-60">{{ $item->classification_system }} {{ $item->classification_code }}</span>@endif
                                    <x-external-link :url="$item->image_url" :label="__('procurement.catalog.media.image')" />
                                    <x-external-link :url="$item->datasheet_url" :label="__('procurement.catalog.media.datasheet')" />
                                </div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $item->purchase_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->purchase_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' ' . $item->currency->value : '—' }}
                            @if ($item->list_price !== null)
                                <div class="text-xs opacity-50">{{ __('procurement.catalog.uvp_short') }} {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->list_price->toFloat(), 2, withThousandsSeparator: true) }}</div>
                            @endif
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
                                        <form method="POST" action="{{ route('supplier-catalogs.items.adopt', $item) }}">@csrf
                                            <x-icon-btn icon="library_add" size="xs" type="submit" :title="__('procurement.catalog.action.adopt_item')" />
                                        </form>
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
