{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : chain.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Belegkette (MVP-1057). Erwartet: $groups (list<array{key,label,icon,count,items}>) --}}
@extends('layouts.app')
@section('title', __('invoicing.chain.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('invoicing.chain.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('invoicing.chain.title')" :subtitle="__('invoicing.chain.description')" />
    </x-slot:toolbar>

    @forelse ($groups as $group)
        <x-card :title="$group['label'] . ' (' . $group['count'] . ')'" :icon="$group['icon']" padding="p-0" id="{{ $group['key'] }}">
            @if ($group['items'] === [])
                <p class="px-4 py-3 text-sm text-muted">{{ __('invoicing.chain.nothing_here') }}</p>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('invoicing.chain.col.document') }}</th>
                            <th>{{ __('invoicing.chain.col.status') }}</th>
                            <th class="text-right">{{ __('invoicing.chain.col.amount') }}</th>
                            <th class="text-right">{{ __('Aktionen') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($group['items'] as $item)
                        <tr>
                            <td>{{ $item->title }}</td>
                            <td class="text-sm text-muted">{{ $item->detail }}</td>
                            <td class="text-right tabular-nums">{{ $item->amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->amount->toFloat(), 2, withThousandsSeparator: true) . ' ' . $item->amount->getCurrency()->value : '—' }}</td>
                            <td class="text-right"><x-icon-btn icon="arrow_forward" size="xs" tone="ghost" :href="$item->url" :title="__('invoicing.chain.open')" /></td>
                        </tr>
                    @endforeach
                </x-table>
                @if ($group['count'] > count($group['items']))
                    <p class="px-4 py-2 text-xs text-muted">{{ __('invoicing.chain.more', ['count' => $group['count'] - count($group['items'])]) }}</p>
                @endif
            @endif
        </x-card>
    @empty
        <x-empty-state icon="check_circle" :title="__('invoicing.chain.empty_title')" :message="__('invoicing.chain.empty')" />
    @endforelse
</x-page-shell>
@endsection
