{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import_preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau des Anbieter-Imports (Feature 185, MVP-1418): je Datei neu,
  geändert (mit geänderten Feldern und altem Wert), unverändert und die
  Abos, die die Datei nicht mehr nennt. Übernommen wird erst nach
  Bestätigung; Verwerfen löscht die abgelegten Dateien.
  Daten: MarketplaceImporter::preview() ($preview), $token.
--}}
@extends('layouts.app')
@section('title', __('resale.import.preview.title'))
@section('nav-title', __('resale.title.menu'))

@php
    $importable = array_filter($preview['kinds'], static fn (array $k): bool => $k['error'] === null) !== [] || ($preview['pricelist'] !== null && $preview['pricelist']['error'] === null);
    $changeTone = ['new' => 'success', 'changed' => 'warning', 'unchanged' => 'ghost'];
@endphp

@section('content')
    <x-index-page :subtitle="__('resale.import.preview.subtitle')" :back="route('finance.resale.index')" :back-label="__('resale.title.menu')">
        <x-slot:actions>
            <x-action-form :action="route('finance.resale.import.discard')">
                <input type="hidden" name="token" value="{{ $token }}">
                <x-button type="submit" tone="ghost" size="sm" icon="delete" placement="danger">{{ __('resale.import.preview.discard') }}</x-button>
            </x-action-form>
            @if ($importable)
                <x-action-form :action="route('finance.resale.import.commit')">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-button type="submit" tone="primary" size="sm" icon="check" placement="bar">{{ __('resale.import.preview.confirm') }}</x-button>
                </x-action-form>
            @endif
        </x-slot:actions>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.import.preview.new')" :value="$preview['total']['new']" :tone="$preview['total']['new'] > 0 ? 'success' : 'neutral'" />
            <x-kpi-tile :label="__('resale.import.preview.changed')" :value="$preview['total']['changed']" :tone="$preview['total']['changed'] > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.import.preview.unchanged')" :value="$preview['total']['unchanged']" />
            <x-kpi-tile :label="__('resale.import.preview.missing')" :value="$preview['total']['missing']" :tone="$preview['total']['missing'] > 0 ? 'error' : 'neutral'" />
        </div>

        @if ($preview['kinds'] !== [] && $preview['total']['new'] + $preview['total']['changed'] + $preview['total']['missing'] === 0 && array_filter($preview['kinds'], static fn (array $k): bool => $k['error'] !== null) === [])
            <div class="alert alert-info mb-4 text-sm" role="status">
                <x-icon name="info" />
                <span>{{ __('resale.import.preview.nothing') }}</span>
            </div>
        @endif

        @foreach ($preview['kinds'] as $kind)
            <x-card :title="$kind['label'] . ' · ' . $kind['file']" icon="description" padding="p-0" class="mb-4">
                <x-slot:actions>
                    @if ($kind['export_date'] !== null)
                        <span class="text-xs text-muted">{{ __('resale.import.preview.export_date', ['date' => \Carbon\CarbonImmutable::parse($kind['export_date'])->fdate()]) }}</span>
                    @endif
                </x-slot:actions>
                @if ($kind['error'] !== null)
                    <div class="alert alert-error m-4 text-sm" role="alert">
                        <x-icon name="error" />
                        <span>{{ $kind['error'] }}</span>
                    </div>
                @else
                    @if ($kind['issues'] !== [])
                        <ul class="px-4 py-2 text-xs text-warning space-y-0.5 border-b border-base-300">
                            @foreach ($kind['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('resale.import.preview.col.change') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.label') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.company') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.starts_on') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.field.purchase_unit_price') }}</x-table.th>
                                <x-table.th>{{ __('resale.import.preview.col.changes') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($kind['rows'] as $row)
                            <tr @class(['opacity-60' => $row['change'] === 'unchanged'])>
                                <td><x-status-badge size="xs" :tone="$changeTone[$row['change']]" :label="__('resale.import.preview.' . $row['change'])" /></td>
                                <td class="text-sm">
                                    {{ $row['product'] }}
                                    <span class="block text-xs text-muted font-mono">{{ $row['external_id'] }}</span>
                                </td>
                                <td class="text-sm">
                                    {{ $row['company'] }}
                                    @if ($row['holder'] !== null)
                                        <span class="block text-xs text-muted">{{ $row['holder'] }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ $row['quantity'] }}
                                    @if (in_array('quantity', $row['changes'], true))
                                        <span class="block text-xs text-muted line-through">{{ $row['previous']['quantity'] }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap tabular-nums text-sm">
                                    {{ \Carbon\CarbonImmutable::parse($row['starts_on'])->fdate() }}@if ($row['ends_on'] !== null) – {{ \Carbon\CarbonImmutable::parse($row['ends_on'])->fdate() }}@endif
                                    @if (in_array('starts_on', $row['changes'], true) || in_array('ends_on', $row['changes'], true))
                                        <span class="block text-xs text-muted line-through">{{ \Carbon\CarbonImmutable::parse($row['previous']['starts_on'])->fdate() }}@if ($row['previous']['ends_on'] !== null) – {{ \Carbon\CarbonImmutable::parse($row['previous']['ends_on'])->fdate() }}@endif</span>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge size="xs" :tone="$row['status']->tone()" :label="$row['status']->label()" />
                                    @if (in_array('status', $row['changes'], true))
                                        <span class="block text-xs text-muted line-through">{{ $row['previous']['status']->label() }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums whitespace-nowrap">
                                    {{ $row['unit_fee']->withScale(2)->format() }}
                                    @if (in_array('price', $row['changes'], true))
                                        <span class="block text-xs text-muted line-through">{{ $row['previous']['unit_fee']?->withScale(2)->format() ?? '—' }}</span>
                                        <span class="block text-xs {{ $row['price_follows'] ? 'text-success' : 'text-warning' }}">{{ __($row['price_follows'] ? 'resale.import.preview.price_follows' : 'resale.import.preview.price_manual') }}</span>
                                    @endif
                                </td>
                                <td class="text-xs">
                                    @if ($row['changes'] !== [])
                                        {{ implode(', ', array_map(static fn (string $f): string => __('resale.import.preview.change.' . $f), $row['changes'])) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="8" icon="table_rows" :title="__('resale.import.preview.no_rows')" compact />
                        @endforelse
                    </x-table>
                    @if ($kind['missing'] !== [])
                        <div class="border-t border-base-300 px-4 py-2 text-sm font-medium">{{ trans_choice('resale.import.preview.missing_title', count($kind['missing']), ['count' => count($kind['missing'])]) }}</div>
                        <p class="px-4 pb-2 text-xs text-muted">{{ __('resale.import.preview.missing_hint') }}</p>
                        <x-table bare>
                            <x-slot:head>
                                <tr>
                                    <x-table.th>{{ __('resale.field.label') }}</x-table.th>
                                    <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                                    <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                                    <x-table.th>{{ __('resale.field.ends_on') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($kind['missing'] as $missing)
                                <tr>
                                    <td class="text-sm">
                                        <a href="{{ route('finance.resale.show', $missing['subscription']->sqid) }}" class="link link-hover">{{ $missing['label'] }}</a>
                                        <span class="block text-xs text-muted font-mono">{{ $missing['external_id'] }}</span>
                                    </td>
                                    <td class="text-sm">{{ $missing['holder'] ?? '—' }}</td>
                                    <td><x-status-badge size="xs" :tone="$missing['status']->tone()" :label="$missing['status']->label()" /></td>
                                    <td class="tabular-nums text-sm">{{ $missing['ends_on'] !== null ? \Carbon\CarbonImmutable::parse($missing['ends_on'])->fdate() : '—' }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif
                @endif
            </x-card>
        @endforeach

        @if ($preview['pricelist'] !== null)
            <x-card :title="__('resale.import.kind.pricelist') . ' · ' . $preview['pricelist']['file']" icon="price_change" class="mb-4">
                @if ($preview['pricelist']['error'] !== null)
                    <div class="alert alert-error text-sm" role="alert">
                        <x-icon name="error" />
                        <span>{{ $preview['pricelist']['error'] }}</span>
                    </div>
                @else
                    <p class="text-sm">{{ trans_choice('resale.import.preview.pricelist_rows', $preview['pricelist']['rows'], ['count' => $preview['pricelist']['rows']]) }}</p>
                @endif
            </x-card>
        @endif
    </x-index-page>
@endsection
