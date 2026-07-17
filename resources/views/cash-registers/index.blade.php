{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashRegister> $registers
 * @var array<int, float> $balances
 * @var array<int, \Carbon\Carbon|null> $lastClosings
 */
@endphp

@section('nav-title', __('Kassenbuch'))

@section('content')
<x-page-shell>
    <x-page-toolbar :subtitle="__('GoBD-konformes Kassenbuch: Buchungen sind unveränderlich, Korrekturen laufen als Storno-Gegenbuchung (kein POS/TSE).')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::CashManage->value)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('cash-registers.create') . '?dialog=1'"
                            show-label>{{ __('Kasse anlegen') }}</x-icon-btn>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    <x-table table-sort="client" :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('Kasse') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Saldo') }}</x-table.th>
                <th>{{ __('Letzter Tagesabschluss') }}</th>
                <th>{{ __('Eröffnet am') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($registers as $register)
            <tr class="hover">
                <td class="font-medium">
                    <a href="{{ route('cash-registers.show', $register) }}" class="link link-hover">{{ $register->name }}</a>
                </td>
                <td class="text-right tabular-nums" data-sort-value="{{ $balances[$register->id] ?? 0 }}">
                    {{ number_format($balances[$register->id] ?? 0, 2, ',', '.') }} {{ $register->currency->value }}
                </td>
                <td class="whitespace-nowrap">{{ ($lastClosings[$register->id] ?? null)?->fdate() ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $register->opened_on->fdate() }}</td>
                <td>
                    <x-status-badge size="sm" :tone="$register->active ? 'success' : 'neutral'">{{ $register->active ? __('Aktiv') : __('Inaktiv') }}</x-status-badge>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>' :colspan="5" :title="__('Noch keine Kasse angelegt')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
