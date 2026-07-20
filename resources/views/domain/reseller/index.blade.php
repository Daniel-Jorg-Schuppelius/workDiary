{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.title.reseller') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.title.reseller'))

@section('content')
<x-index-page :subtitle="__('domain.title.reseller_subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="dns" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
    </x-slot:actions>

    <x-table :caption="__('domain.title.reseller')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('domain.reseller.user') }}</x-table.th>
                <x-table.th>{{ __('domain.reseller.parent') }}</x-table.th>
                <x-table.th>{{ __('domain.field.customer') }}</x-table.th>
                <x-table.th align="right">{{ __('domain.reseller.domains') }}</x-table.th>
                <x-table.th align="right">{{ __('domain.reseller.balance') }}</x-table.th>
                <x-table.th>{{ __('domain.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($accounts as $account)
            @php $indentClass = ['', 'pl-6', 'pl-12'][$account->depth] ?? 'pl-12'; @endphp
            <tr>
                <td>
                    <div class="flex items-center gap-2 {{ $indentClass }}">
                        @if ($account->depth > 0)
                            <x-icon name="subdirectory_arrow_right" class="text-base-content/40" />
                        @endif
                        <a href="{{ route('domain-reseller.show', $account) }}" class="link link-hover font-mono">{{ $account->external_user }}</a>
                        @if ($account->user_class)<x-status-badge tone="ghost" size="sm">{{ $account->user_class }}</x-status-badge>@endif
                    </div>
                </td>
                <td class="font-mono text-xs text-base-content/60">{{ $account->parent_user ?? '—' }}</td>
                <td>{{ $account->customer?->name ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $account->domains_count }}</td>
                <td class="text-right tabular-nums">
                    {{ $account->balance_snapshot !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $account->balance_snapshot, 2, withThousandsSeparator: true) . ' ' . ($account->currency?->value ?? '') : '—' }}
                </td>
                <td>
                    <x-status-badge :tone="$account->active ? 'success' : 'ghost'" size="sm">
                        {{ $account->active ? __('domain.reseller.active') : __('domain.reseller.inactive') }}
                    </x-status-badge>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" :title="__('domain.empty.reseller')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
