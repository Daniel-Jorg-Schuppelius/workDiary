{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Belege') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Belege'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $vouchers */
    $q = $filters['q'] ?? '';
    $type = $filters['type'] ?? '';
    $party = $filters['party'] ?? '';
    $status = $filters['status'] ?? 'active';
    $sum = $vouchers->getCollection()->sum(static fn ($v) => (float) $v->total_amount);
    $statusTone = static fn (?string $s): string => match ($s) {
        'paid' => 'success',
        'paidoff' => 'success',
        'accepted' => 'success',
        'transferred' => 'success',
        'open' => 'warning',
        'sent' => 'info',
        'overdue' => 'error',
        'rejected' => 'error',
        'checked' => 'success',
        'unchecked' => 'warning',
        'voided' => 'ghost',
        default => 'neutral',
    };
    $valueLabel = static function (?string $value, string $empty = '—'): string {
        if ($value === null || $value === '') {
            return $empty;
        }

        $key = 'values.' . $value;
        $label = __($key);

        return $label === $key ? $value : $label;
    };
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Aus Lexoffice synchronisierte Belege im Zeitraum :range — über den Datumsfilter im Header anpassbar.', ['range' => $rangeLabel ?? ''])">
    @if ($canSync ?? false)
        <x-slot:actions>
            <form method="POST" action="{{ route('lexoffice.vouchers.sync') }}">
                @csrf
                <x-icon-btn icon="sync" tone="primary" size="sm" type="submit"
                            show-label>{{ __('Synchronisieren') }}</x-icon-btn>
            </form>
        </x-slot:actions>
    @endif

    @include('billing._tabs')

    <x-filter-bar :action="route('lexoffice.vouchers.index')" :reset="($q !== '' || $type !== '' || $party !== '' || $status !== 'active') ? route('lexoffice.vouchers.index') : null">
        <x-filter-field :label="__('Suche')" for="vch-q" class="flex-1 min-w-60">
            <input id="vch-q" type="text" name="q" value="{{ $q }}" placeholder="{{ __('Belegnr., Typ …') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-filter-field :label="__('Typ')" for="vch-type" class="w-44 shrink-0">
            <select id="vch-type" name="type" class="select select-sm select-bordered">
                <option value="" @selected($type === '')>{{ __('Alle') }}</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ $valueLabel($t) }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Zuordnung')" for="vch-party" class="w-40 shrink-0">
            <select id="vch-party" name="party" class="select select-sm select-bordered">
                <option value="" @selected($party === '')>{{ __('Alle') }}</option>
                <option value="customer" @selected($party === 'customer')>{{ __('Kunde') }}</option>
                <option value="supplier" @selected($party === 'supplier')>{{ __('Lieferant') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Status')" for="vch-status" class="w-40 shrink-0">
            <select id="vch-status" name="status" class="select select-sm select-bordered">
                <option value="active" @selected($status === 'active')>{{ __('Aktiv') }}</option>
                <option value="archived" @selected($status === 'archived')>{{ __('Archiviert') }}</option>
                <option value="all" @selected($status === 'all')>{{ __('Alle') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('lexoffice.vouchers.index')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="array_filter(['q' => $q ?: null, 'type' => $type ?: null, 'party' => $party ?: null, 'status' => $status !== 'active' ? $status : null])">
        <x-slot:head>
            <tr>
                <x-table.th sort="voucher_number">{{ __('Nummer') }}</x-table.th>
                <x-table.th sort="voucher_date">{{ __('Datum') }}</x-table.th>
                <x-table.th sort="voucher_type">{{ __('Typ') }}</x-table.th>
                <th>{{ __('Zuordnung') }}</th>
                <x-table.th sort="voucher_status">{{ __('Status') }}</x-table.th>
                <x-table.th sort="total_amount" align="right">{{ __('Betrag') }}</x-table.th>
                <th class="text-right">{{ __('Beleg') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($vouchers as $voucher)
            <tr>
                <td class="font-medium tabular-nums">
                    <a class="link link-hover" href="{{ route('lexoffice.vouchers.preview', $voucher) }}"
                       data-entry-modal-trigger>
                        {{ $voucher->voucher_number ?? '—' }}
                    </a>
                </td>
                <td class="tabular-nums text-sm text-base-content/70">{{ optional($voucher->voucher_date)->format('d.m.Y') ?? '—' }}</td>
                <td class="text-base-content/70">{{ $valueLabel($voucher->voucher_type) }}</td>
                <td>
                    @if ($voucher->customer)
                        <a class="link link-hover" href="{{ route('customers.show', $voucher->customer) }}">{{ $voucher->customer->name }}</a>
                    @elseif ($voucher->supplier)
                        <a class="link link-hover" href="{{ route('suppliers.show', $voucher->supplier) }}">{{ $voucher->supplier->name }}</a>
                    @else
                        <span class="text-base-content/40">—</span>
                    @endif
                </td>
                <td>
                    <x-status-badge :tone="$statusTone($voucher->voucher_status)" size="sm">
                        {{ $valueLabel($voucher->voucher_status) }}
                    </x-status-badge>
                </td>
                <td class="text-right tabular-nums">
                    {{ number_format((float) $voucher->total_amount, 2, ',', '.') }} {{ $voucher->currency }}
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" size="sm"
                                    :href="route('lexoffice.vouchers.preview', $voucher)"
                                    data-entry-modal-trigger
                                    :label="__('Belegbild anzeigen')" />
                        <x-icon-btn icon="download" size="sm"
                                    :href="route('lexoffice.vouchers.file', [$voucher, 'download' => 1])"
                                    :label="__('Belegbild herunterladen')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="7"
                :title="$q !== '' ? __('Keine Treffer für „:q“.', ['q' => $q]) : __('Noch keine Belege synchronisiert')" compact />
        @endforelse
        @if ($vouchers->total() > 0)
            <x-slot:foot>
                <tr class="font-semibold">
                    <td colspan="5" class="text-right">{{ __('Summe (Seite)') }}</td>
                    <td class="text-right tabular-nums">{{ number_format((float) $sum, 2, ',', '.') }}&nbsp;&euro;</td>
                    <td></td>
                </tr>
            </x-slot:foot>
        @endif
    </x-table>
    <x-pagination :paginator="$vouchers" standing />
</x-index-page>
@endsection
