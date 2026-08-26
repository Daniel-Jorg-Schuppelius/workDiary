{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.stock') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.stock'))

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
{{-- Kein Voll-Höhe-Layout (scroll=flex): unter der Bestandstabelle folgen
     Reservierungen/Beschaffungsbedarf/Bestandsgrenzen — die Seite scrollt
     normal (Vollscan 2026-08 I10). --}}
<x-index-page :subtitle="__('inventory.stock')">
    <x-slot:actions>
        <x-icon-btn icon="fact_check" size="sm" :href="route('inventory.counts.index', ['warehouse' => $selected?->sqid])" show-label>{{ __('inventory.count_ui.title') }}</x-icon-btn>
        <x-icon-btn icon="warehouse" size="sm" :href="route('warehouses.index')" show-label>{{ __('inventory.warehouses') }}</x-icon-btn>
    </x-slot:actions>

    @if ($warehouses->isEmpty())
        <x-empty-state framed :title="__('inventory.empty.warehouses')" />
    @else
        {{-- Lagerort-Auswahl --}}
        {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
        <x-tab-nav :items="$warehouses->map(fn($wh) => [
            'label' => $wh->name,
            'route' => 'inventory.stock',
            'params' => ['warehouse' => $wh->sqid],
            'active' => $selected && $selected->id === $wh->id,
        ])->all()" />

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
                            <label for="variant" class="fieldset-label">{{ __('inventory.field.variant') }}</label>
                            <select id="variant" name="variant" class="select select-sm select-bordered" required>
                                @foreach ($pickerVariants as $v)
                                    <option value="{{ $v->sqid }}">{{ $v->article?->name }} — {{ $v->name ?? $v->option_signature }} ({{ $v->sku ?? '—' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label for="movement" class="fieldset-label">{{ __('inventory.field.movement') }}</label>
                            <select id="movement" name="movement" class="select select-sm select-bordered">
                                @foreach ($movements as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($bins->isNotEmpty())
                            {{-- Lagerplatz (MVP-706), optional --}}
                            <div class="fieldset">
                                <label class="fieldset-label" for="movement-bin">{{ __('inventory.field.bin') }}</label>
                                <select name="bin" id="movement-bin" class="select select-sm select-bordered">
                                    <option value="">{{ __('inventory.field.no_bin') }}</option>
                                    @foreach ($bins as $bin)
                                        <option value="{{ $bin->sqid }}" @disabled(! $bin->isUsable())>{{ $bin->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="fieldset">
                            <label for="qty" class="fieldset-label">{{ __('inventory.field.quantity') }}</label>
                            <input id="qty" name="qty" type="number" step="0.0001" min="0.0001" required class="input input-sm input-bordered w-28">
                        </div>
                        <div class="fieldset">
                            <label for="ownership" class="fieldset-label">{{ __('inventory.field.ownership') }}</label>
                            <select id="ownership" name="ownership" class="select select-sm select-bordered">
                                @foreach ($ownerships as $own)
                                    <option value="{{ $own->value }}">{{ $own->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label for="cost_customer" class="fieldset-label">{{ __('customer-material.book_to_customer') }}</label>
                            <select id="cost_customer" name="cost_customer" class="select select-sm select-bordered">
                                <option value="">{{ __('customer-material.no_customer') }}</option>
                                @foreach ($costCustomers as $c)
                                    <option value="{{ $c->sqid }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="label cursor-pointer gap-2">
                            <input type="hidden" name="allow_negative" value="0">
                            <input type="checkbox" name="allow_negative" value="1" class="checkbox checkbox-sm">
                            <span class="label-text">{{ __('inventory.field.allow_negative') }}</span>
                        </label>
                        <x-button type="submit" tone="primary" size="sm">{{ __('inventory.action.book') }}</x-button>
                    </form>
                </x-card>
            @endif

            {{-- Bestandstabelle --}}
            <x-table :zebra="true" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('inventory.field.variant') }}</th>
                        <th>{{ __('article.field.sku') }}</th>
                        <th class="text-right">{{ __('inventory.field.available') }}</th>
                        <th class="text-right">{{ __('inventory.field.physical') }}</th>
                        @if ($bins->isNotEmpty())<th>{{ __('inventory.field.bin') }}</th>@endif
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
                        @if ($bins->isNotEmpty())
                            <td class="font-mono text-xs">
                                @forelse ($row['bins'] as $code => $sum)
                                    <span class="badge badge-sm badge-ghost mr-1">{{ $code }}: {{ $sum }}</span>
                                @empty
                                    —
                                @endforelse
                            </td>
                        @endif
                        <td class="text-right tabular-nums">{{ $row['reserved'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['avg'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['value'] }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="$bins->isNotEmpty() ? 8 : 7" :title="__('inventory.empty.stock')" />
                @endforelse
            </x-table>

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
                            <label for="variant-2" class="fieldset-label">{{ __('inventory.field.variant') }}</label>
                            <select id="variant-2" name="variant" class="select select-sm select-bordered" required>
                                @foreach ($pickerVariants as $v)
                                    <option value="{{ $v->sqid }}">{{ $v->article?->name }} — {{ $v->name ?? $v->option_signature }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label for="min_stock" class="fieldset-label">{{ __('inventory.overview.min_stock') }}</label>
                            <input id="min_stock" name="min_stock" type="number" step="0.0001" min="0" value="0" required class="input input-sm input-bordered w-24">
                        </div>
                        <div class="fieldset">
                            <label for="reorder_point" class="fieldset-label">{{ __('inventory.overview.reorder_point') }}</label>
                            <input id="reorder_point" name="reorder_point" type="number" step="0.0001" min="0" value="0" required class="input input-sm input-bordered w-24">
                        </div>
                        <button type="submit" class="btn btn-sm">{{ __('inventory.overview.set_levels') }}</button>
                    </form>
                </x-card>
            @endif
        @endif
    @endif
</x-index-page>
@endsection
