{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $bill->name . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $bill->name)

@section('content')
<x-index-page :subtitle="$bill->project?->name ?: __('gaeb.title')"
              back-route="bill-of-quantities.index" :back-label="__('gaeb.show.back')">
    <x-slot:actions>
        @can(\App\Enums\User\Permission::ProjectUpdate->value)
            <x-icon-btn icon="price_change" tone="primary" size="sm" :href="route('bill-of-quantities.pricing', $bill)" show-label>{{ __('gaeb.pricing.button') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="download" size="sm" :href="route('bill-of-quantities.export', $bill)" show-label>{{ __('gaeb.export.button') }}</x-icon-btn>
        {{-- MVP-1056: EFB-Preisblätter für öffentliche Auftraggeber --}}
        <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('bill-of-quantities.efb', [$bill, '221'])" show-label>{{ __('gaeb.efb.221.button') }}</x-icon-btn>
        <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('bill-of-quantities.efb', [$bill, '223'])" show-label>{{ __('gaeb.efb.223.button') }}</x-icon-btn>
    </x-slot:actions>

    @include('bill-of-quantities._tabs')

    {{-- Nachkalkulation (MVP-083) --}}
    <x-card>
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.planned') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($costing['planned'], 2, withThousandsSeparator: true) }} {{ $costing['currency'] }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.executed') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($costing['executed'], 2, withThousandsSeparator: true) }} {{ $costing['currency'] }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.remaining') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($costing['remaining'], 2, withThousandsSeparator: true) }} {{ $costing['currency'] }}</div>
            </div>
            <div class="flex-1 min-w-48">
                <div class="text-xs uppercase opacity-60 mb-1">{{ __('gaeb.costing.progress') }} ({{ round($costing['progress'] * 100) }}%)</div>
                <progress class="progress progress-primary w-full" value="{{ round($costing['progress'] * 100) }}" max="100"></progress>
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('bill-of-quantities.transition', $bill) }}" class="flex items-end gap-2">@csrf
                    <div>
                        <label class="label py-0"><span class="label-text text-xs">{{ __('gaeb.columns.status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm">
                            @foreach ($billStatuses as $st)
                                <option value="{{ $st->value }}" @selected($bill->status === $st)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit" tone="plain">{{ __('gaeb.workflow.status') }}</x-button>
                </form>
            @endif
        </div>
    </x-card>

    {{-- Positionen (MVP-082/083/084) --}}
    <x-card padding="p-0" class="mt-4">
        <x-table :bare="true">
            <x-slot:head>
                <th>{{ __('gaeb.columns.reference_no') }}</th>
                <th>{{ __('gaeb.columns.short_text') }}</th>
                <th>{{ __('gaeb.columns.type') }}</th>
                <th class="text-right">{{ __('gaeb.columns.quantity') }}</th>
                <th class="text-right">{{ __('gaeb.columns.executed') }}</th>
                <th class="text-right">{{ __('gaeb.columns.remaining') }}</th>
                <th>{{ __('gaeb.columns.unit') }}</th>
                <th class="text-right">{{ __('gaeb.columns.unit_price') }}</th>
                <th>{{ __('gaeb.columns.status') }}</th>
                @if ($canManage)<th class="text-right">{{ __('gaeb.progress.record') }}</th>@endif
            </x-slot:head>
            @foreach ($bill->items as $item)
                <tr>
                    <td class="font-mono text-sm whitespace-nowrap">{{ $item->reference_no }}</td>
                    <td>
                        {{ $item->short_text ?: '—' }}
                        @if ($item->is_addendum)<x-status-badge tone="warning" size="xs" class="ml-1">N</x-status-badge>@endif
                        @foreach ($item->mappings as $map)
                            <x-status-badge size="xs" class="ml-1">{{ \App\Support\EntityType::label($map->mappable_type) }}</x-status-badge>
                        @endforeach
                    </td>
                    <td><x-status-badge>{{ $item->type->label() }}</x-status-badge></td>
                    <td class="text-right tabular-nums">{{ $item->quantity !== null ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->quantity?->getValue()->toFloat() ?? 0.0), 3, withThousandsSeparator: true), '0'), ',') : '—' }}</td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->executedQuantity(), 3, withThousandsSeparator: true), '0'), ',') }}</td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->remainingQuantity(), 3, withThousandsSeparator: true), '0'), ',') }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ $item->unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->unit_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) : '—' }}</td>
                    <td><x-status-badge>{{ $item->status->label() }}</x-status-badge></td>
                    @if ($canManage)
                        <td>
                            <form method="POST" action="{{ route('bill-of-quantities.items.progress', $item) }}" class="flex items-center justify-end gap-1">@csrf
                                <input aria-label="{{ __('Menge') }}" type="number" step="0.001" name="quantity" class="input input-bordered input-xs w-24" placeholder="0" required>
                                <x-button type="submit" size="xs">+</x-button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @include('takeoffs._carrier_panel', ['carrierType' => 'boq', 'carrier' => $bill, 'class' => 'mt-4'])

    {{-- Nachtrag anlegen (MVP-084) --}}
    @if ($canManage)
        <x-card class="mt-4">
            <h2 class="text-sm font-semibold mb-2">{{ __('gaeb.workflow.add_addendum') }}</h2>
            <form method="POST" action="{{ route('bill-of-quantities.addenda.add', $bill) }}" class="flex flex-wrap items-end gap-2">@csrf
                <input aria-label="{{ __('gaeb.columns.reference_no') }}" type="text" name="reference_no" class="input input-bordered input-sm w-28" placeholder="{{ __('gaeb.columns.reference_no') }}" required>
                <input aria-label="{{ __('gaeb.columns.short_text') }}" type="text" name="short_text" class="input input-bordered input-sm flex-1 min-w-48" placeholder="{{ __('gaeb.columns.short_text') }}">
                <input aria-label="{{ __('gaeb.columns.quantity') }}" type="number" step="0.001" name="quantity" class="input input-bordered input-sm w-24" placeholder="{{ __('gaeb.columns.quantity') }}">
                <input aria-label="{{ __('gaeb.columns.unit') }}" type="text" name="unit" class="input input-bordered input-sm w-20" placeholder="{{ __('gaeb.columns.unit') }}">
                <input aria-label="{{ __('gaeb.columns.unit_price') }}" type="number" step="0.01" name="unit_price" class="input input-bordered input-sm w-24" placeholder="{{ __('gaeb.columns.unit_price') }}">
                <x-button type="submit" tone="plain">{{ __('gaeb.workflow.add_addendum') }}</x-button>
            </form>
        </x-card>
    @endif

    {{-- Restleistung (MVP-084) --}}
    <h2 class="text-sm font-semibold mt-6 mb-2">{{ __('gaeb.workflow.remaining_title') }}</h2>
    @if ($remaining->isEmpty())
        <p class="text-sm opacity-60">{{ __('gaeb.workflow.no_remaining') }}</p>
    @else
        <x-card padding="p-0">
            <x-table :bare="true">
                <x-slot:head>
                    <th>{{ __('gaeb.columns.reference_no') }}</th>
                    <th>{{ __('gaeb.columns.short_text') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.remaining') }}</th>
                    <th>{{ __('gaeb.columns.unit') }}</th>
                </x-slot:head>
                @foreach ($remaining as $item)
                    <tr>
                        <td class="font-mono text-sm whitespace-nowrap">{{ $item->reference_no }}</td>
                        <td>{{ $item->short_text ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->remainingQuantity(), 3, withThousandsSeparator: true), '0'), ',') }}</td>
                        <td>{{ $item->unit ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    {{-- Importhistorie (MVP-081) --}}
    <h2 class="text-sm font-semibold mt-6 mb-2">{{ __('gaeb.show.history') }}</h2>
    @if ($imports->isEmpty())
        <p class="text-sm opacity-60">{{ __('gaeb.show.no_imports') }}</p>
    @else
        <x-card padding="p-0">
            <x-table :bare="true">
                <x-slot:head>
                    <th>{{ __('gaeb.show.imported_at') }}</th>
                    <th>{{ __('gaeb.columns.phase') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.items') }}</th>
                    <th>{{ __('gaeb.columns.status') }}</th>
                </x-slot:head>
                @foreach ($imports as $import)
                    <tr>
                        <td class="text-sm">{{ $import->created_at?->fdatetime() }}</td>
                        <td>{{ $import->phase?->label() ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $import->item_count }}</td>
                        <td><x-status-badge>{{ $import->status->label() }}</x-status-badge></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-index-page>
@endsection
