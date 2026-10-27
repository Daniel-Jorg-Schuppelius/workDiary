{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzkonten (Feature 185, MVP-1400): je Anbieterkonto Lizenzen je Produkt,
  Halter mit Mengen, offene Lizenzjahre und Prüffälle — Probleme zuerst.
--}}
@extends('layouts.app')
@section('title', __('resale.account.title'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :title="__('resale.account.title')" :subtitle="__('resale.account.subtitle')">
        @include('finance.resale._tabs')

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.account.summary.accounts')" :value="$summary['accounts']" />
            <x-kpi-tile :label="__('resale.account.summary.open')" :value="$summary['open']" :tone="$summary['open'] > 0 ? 'error' : 'success'" />
            <x-kpi-tile :label="__('resale.account.summary.conflicts')" :value="$summary['conflicts']" :tone="$summary['conflicts'] > 0 ? 'warning' : 'neutral'" />
        </div>

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.account.col.account') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.provider') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.account.col.contracts') }}</x-table.th>
                    <x-table.th>{{ __('resale.account.col.licences') }}</x-table.th>
                    <x-table.th>{{ __('resale.account.col.holders') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.account.col.open') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.account.col.conflicts') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                @php $group = $row['group']; @endphp
                <tr class="hover">
                    <td>
                        <a href="{{ route('finance.resale.accounts.show', $group->routeKey()) }}" class="link link-hover font-medium">{{ $row['name'] }}</a>
                        @if ($row['partner'] !== null)
                            <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $row['partner']->name]) }}</span>
                        @endif
                        <span class="block font-mono text-xs text-muted">{{ $group->account }}</span>
                    </td>
                    <td class="text-sm">{{ $group->provider->label() }}</td>
                    <td class="text-right tabular-nums">{{ $row['contracts'] }}</td>
                    <td class="text-sm">
                        @forelse ($row['products'] as $product)
                            <span class="block whitespace-nowrap"><span class="tabular-nums font-medium">{{ $product['quantity'] }} ×</span> {{ $product['label'] }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="text-sm">
                        @foreach ($row['holders'] as $holder)
                            <span class="block whitespace-nowrap">{{ $holder['label'] }} <span class="text-muted tabular-nums">({{ $holder['quantity'] }})</span></span>
                        @endforeach
                    </td>
                    <td class="text-right tabular-nums">
                        @if ($row['open'] > 0)
                            <x-status-badge tone="error">{{ $row['open'] }}</x-status-badge>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">
                        @if ($row['conflicts'] > 0)
                            <x-status-badge tone="warning">{{ $row['conflicts'] }}</x-status-badge>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="visibility" size="xs" tone="ghost" :href="route('finance.resale.accounts.show', $group->routeKey())" :title="__('resale.action.show')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8" icon="account_balance_wallet" :title="__('resale.account.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
