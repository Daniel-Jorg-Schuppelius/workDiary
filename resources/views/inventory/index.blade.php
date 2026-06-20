@extends('layouts.app')
@section('title', __('inventory.stock') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.stock'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \App\Models\Warehouse|null $selected */
    $movements = [
        'receipt' => __('inventory.movement.receipt'),
        'issue' => __('inventory.movement.issue'),
        'reserve' => __('inventory.movement.reserve'),
        'release' => __('inventory.movement.release_reservation'),
    ];
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.stock')">
    <x-slot:actions>
        <x-icon-btn icon="fact_check" size="sm" :href="route('inventory.counts.index', ['warehouse' => $selected?->sqid])" show-label>{{ __('inventory.count_ui.title') }}</x-icon-btn>
        <x-icon-btn icon="warehouse" size="sm" :href="route('warehouses.index')" show-label>{{ __('inventory.warehouses') }}</x-icon-btn>
    </x-slot:actions>

    @if ($warehouses->isEmpty())
        <x-empty-state framed :title="__('inventory.empty.warehouses')" />
    @else
        {{-- Lagerort-Auswahl --}}
        <div role="tablist" class="tabs tabs-box w-full">
            @foreach ($warehouses as $wh)
                <a role="tab" href="{{ route('inventory.stock', ['warehouse' => $wh->sqid]) }}"
                   class="tab {{ $selected && $selected->id === $wh->id ? 'tab-active' : '' }}">{{ $wh->name }}</a>
            @endforeach
        </div>

        @if (! $selected)
            <x-empty-state framed :title="__('inventory.empty.no_selection')" />
        @else
            {{-- Buchungsformular --}}
            @if ($canPost)
                <x-card>
                    <h2 class="font-semibold mb-3">{{ __('inventory.action.book') }} — {{ $selected->name }}</h2>
                    <form method="POST" action="{{ route('inventory.movements.store') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="warehouse" value="{{ $selected->sqid }}">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.variant') }}</label>
                            <select name="variant" class="select select-sm select-bordered" required>
                                @foreach ($pickerVariants as $v)
                                    <option value="{{ $v->sqid }}">{{ $v->article?->name }} — {{ $v->name ?? $v->option_signature }} ({{ $v->sku ?? '—' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.movement') }}</label>
                            <select name="movement" class="select select-sm select-bordered">
                                @foreach ($movements as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.quantity') }}</label>
                            <input name="qty" type="number" step="0.0001" min="0.0001" required class="input input-sm input-bordered w-28">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.ownership') }}</label>
                            <select name="ownership" class="select select-sm select-bordered">
                                @foreach ($ownerships as $own)
                                    <option value="{{ $own->value }}">{{ $own->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="label cursor-pointer gap-2">
                            <input type="hidden" name="allow_negative" value="0">
                            <input type="checkbox" name="allow_negative" value="1" class="checkbox checkbox-sm">
                            <span class="label-text">{{ __('inventory.field.allow_negative') }}</span>
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.action.book') }}</button>
                    </form>
                </x-card>
            @endif

            {{-- Bestandstabelle --}}
            <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
                <x-table bare scroll="flex" :pinRows="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('inventory.field.variant') }}</th>
                            <th>{{ __('article.field.sku') }}</th>
                            <th class="text-right">{{ __('inventory.field.available') }}</th>
                            <th class="text-right">{{ __('inventory.field.physical') }}</th>
                            <th class="text-right">{{ __('inventory.field.reserved') }}</th>
                            <th class="text-right">{{ __('inventory.overview.avg') }}</th>
                            <th class="text-right">{{ __('inventory.overview.value') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($rows as $row)
                        @php $short = $row['reorder'] !== null && bccomp($row['available'], (string) $row['reorder'], 4) < 0; @endphp
                        <tr>
                            <td>{{ $row['variant']->article?->name }} — {{ $row['variant']->name ?? $row['variant']->option_signature }}</td>
                            <td class="font-mono text-sm">{{ $row['variant']->sku ?? '—' }}</td>
                            <td class="text-right tabular-nums font-medium {{ $short ? 'text-warning' : '' }}">{{ $row['available'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['physical'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['reserved'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['avg'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['value'] }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" :title="__('inventory.empty.stock')" />
                    @endforelse
                </x-table>
            </x-card>

            {{-- Aktive Reservierungen --}}
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('inventory.overview.reservations') }}</h2>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('inventory.field.variant') }}</th>
                            <th class="text-right">{{ __('inventory.field.reserved') }}</th>
                            <th class="text-right">{{ __('inventory.overview.priority') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td>{{ $reservation->variant?->article?->name }} — {{ $reservation->variant?->name ?? $reservation->variant?->option_signature }}</td>
                            <td class="text-right tabular-nums">{{ $reservation->openQuantity() }}</td>
                            <td class="text-right tabular-nums">{{ $reservation->priority }}</td>
                            <td class="text-right">
                                @if ($canPost)
                                    <form method="POST" action="{{ route('inventory.reservations.release', $reservation) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs">{{ __('inventory.overview.release') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('inventory.overview.no_reservations')" />
                    @endforelse
                </x-table>
            </x-card>

            {{-- Beschaffungsbedarf (unter Meldebestand) --}}
            @if ($belowReorder->isNotEmpty())
                <x-card>
                    <h2 class="font-semibold mb-3 text-warning">{{ __('inventory.overview.below_reorder') }}</h2>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('inventory.field.variant') }}</th>
                                <th class="text-right">{{ __('inventory.field.available') }}</th>
                                <th class="text-right">{{ __('inventory.overview.reorder_point') }}</th>
                                <th class="text-right">{{ __('inventory.overview.shortfall') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($belowReorder as $entry)
                            <tr>
                                <td>{{ $entry['setting']->variant?->article?->name }} — {{ $entry['setting']->variant?->name ?? $entry['setting']->variant?->option_signature }}</td>
                                <td class="text-right tabular-nums">{{ $entry['available'] }}</td>
                                <td class="text-right tabular-nums">{{ $entry['setting']->reorder_point }}</td>
                                <td class="text-right tabular-nums text-warning font-medium">{{ $entry['shortfall'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif

            {{-- Mindest-/Meldebestand festlegen --}}
            @if ($canConfigure)
                <x-card>
                    <h2 class="font-semibold mb-3">{{ __('inventory.overview.set_levels') }}</h2>
                    <form method="POST" action="{{ route('inventory.levels.set') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="warehouse" value="{{ $selected->sqid }}">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.field.variant') }}</label>
                            <select name="variant" class="select select-sm select-bordered" required>
                                @foreach ($pickerVariants as $v)
                                    <option value="{{ $v->sqid }}">{{ $v->article?->name }} — {{ $v->name ?? $v->option_signature }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.overview.min_stock') }}</label>
                            <input name="min_stock" type="number" step="0.0001" min="0" value="0" required class="input input-sm input-bordered w-24">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('inventory.overview.reorder_point') }}</label>
                            <input name="reorder_point" type="number" step="0.0001" min="0" value="0" required class="input input-sm input-bordered w-24">
                        </div>
                        <button type="submit" class="btn btn-sm">{{ __('inventory.overview.set_levels') }}</button>
                    </form>
                </x-card>
            @endif
        @endif
    @endif
</x-index-page>
@endsection
