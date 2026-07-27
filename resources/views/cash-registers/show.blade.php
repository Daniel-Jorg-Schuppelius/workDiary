{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \App\Models\CashRegister $register
 * @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\CashEntry> $entries
 * @var array<int, int> $reversedIds
 * @var float $balance
 * @var \Carbon\Carbon|null $lastClosing
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashDailyClosing> $closings
 */
@endphp

@section('nav-title', $register->name)
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kassenbuch') . ' · ' . __('Saldo') . ': ' . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($balance, 2, withThousandsSeparator: true) . ' ' . $register->currency->value">

    <div class="grid grid-cols-1 gap-3 flex-none sm:grid-cols-3">
        <x-kpi-tile :label="__('Saldo')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($balance, 2, withThousandsSeparator: true) . ' ' . $register->currency->value" tone="neutral" />
        <x-kpi-tile :label="__('Letzter Tagesabschluss')" :value="$lastClosing?->fdate() ?? '—'" tone="neutral" />
        <x-kpi-tile :label="__('Buchungen')" :value="$entries->total()" tone="neutral" />
    </div>

    <x-filter-bar :action="route('cash-registers.show', $register)" :reset="route('cash-registers.show', $register)">
        <x-slot:extra>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('cash-registers.index')" show-label>{{ __('Alle Kassen') }}</x-icon-btn>
            @can(\App\Enums\User\Permission::CashManage->value)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('cash-registers.entries.create', $register) . '?dialog=1'"
                            show-label>{{ __('Buchung erfassen') }}</x-icon-btn>
                <x-icon-btn icon="lock" tone="warning" size="sm"
                            data-entry-modal-trigger
                            :href="route('cash-registers.close-form', $register) . '?dialog=1'"
                            show-label>{{ __('Tagesabschluss') }}</x-icon-btn>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('Beleg-Nr.') }}</th>
                <th>{{ __('Datum') }}</th>
                <th>{{ __('Zweck') }}</th>
                <th>{{ __('Gegenpartei') }}</th>
                <th>{{ __('Rechnung') }}</th>
                <th class="text-right">{{ __('Einnahme') }}</th>
                <th class="text-right">{{ __('Ausgabe') }}</th>
                <th class="w-px"></th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            @php
                $isReversed = isset($reversedIds[$entry->id]);
                $closed = $lastClosing !== null && $entry->booked_on->lessThanOrEqualTo($lastClosing);
            @endphp
            <tr @class(['hover', 'opacity-60' => $isReversed])>
                <td class="tabular-nums">{{ $entry->seq_no }}</td>
                <td class="whitespace-nowrap">
                    {{ $entry->booked_on->fdate() }}
                    @if ($closed)
                        <span class="tooltip tooltip-right" data-tip="{{ __('Tag abgeschlossen — festgeschrieben.') }}">
                            <x-icon name="lock" class="text-base-content/40" />
                        </span>
                    @endif
                </td>
                <td class="max-w-md truncate">
                    {{ $entry->purpose }}
                    @foreach ($entry->attachments as $receipt)
                        <a href="{{ \App\Http\Controllers\AttachmentController::downloadUrl($receipt) }}"
                           class="link link-hover align-middle"
                           title="{{ __('Beleg: :name', ['name' => $receipt->original_name]) }}">
                            <x-icon name="attach_file" class="text-base-content/50" />
                        </a>
                    @endforeach
                    @if ($entry->reversal_of_id !== null)
                        <x-status-badge size="sm" tone="warning">{{ __('Storno') }}</x-status-badge>
                    @endif
                    @if ($isReversed)
                        <x-status-badge size="sm" tone="neutral">{{ __('storniert') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-base-content/60">{{ $entry->counterparty ?? '—' }}</td>
                <td>
                    @if ($entry->invoice !== null)
                        <a href="{{ route('invoices.show', $entry->invoice) }}" class="link link-hover">{{ $entry->invoice->number }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $entry->direction === \App\Models\CashEntry::DIRECTION_IN ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $entry->amount, 2, withThousandsSeparator: true) : '' }}</td>
                <td class="text-right tabular-nums">{{ $entry->direction === \App\Models\CashEntry::DIRECTION_OUT ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $entry->amount, 2, withThousandsSeparator: true) : '' }}</td>
                <td class="text-right">
                    @can(\App\Enums\User\Permission::CashManage->value)
                        @if (! $isReversed && $entry->reversal_of_id === null)
                            <x-icon-btn icon="undo" tone="warning" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('cash-registers.entries.reverse-form', [$register, $entry]) . '?dialog=1'"
                                        :label="__('Stornieren')" />
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>' :colspan="8" :title="__('Noch keine Buchungen')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$entries" standing />

    @if ($closings->isNotEmpty())
        <x-card :title="__('Tagesabschlüsse (letzte 10)')">
            <x-table size="sm" :zebra="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th class="text-right">{{ __('Soll') }}</th>
                        <th class="text-right">{{ __('Gezählt') }}</th>
                        <th class="text-right">{{ __('Differenz') }}</th>
                        <th>{{ __('Notiz') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($closings as $closing)
                    <tr>
                        <td class="whitespace-nowrap">{{ $closing->closing_date->fdate() }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($closing->expected_balance?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($closing->counted_balance?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                        <td @class(['text-right tabular-nums', 'text-error font-semibold' => ($closing->difference?->toFloat() ?? 0.0)!== 0.0])>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($closing->difference?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                        <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $closing->note }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

</x-index-page>
@endsection
