{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('recipes.menu.title'))
@section('nav-title', __('recipes.menu.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('recipes.menu.intro')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('recipe-menus.create')"
                    show-label>{{ __('recipes.menu.action.create') }}</x-icon-btn>
    </x-slot:actions>

    <x-validation-errors first />

    <x-table scroll="flex" :pinRows="true" :empty-title="__('recipes.menu.empty')">
        <x-slot:head>
            <tr>
                <th>{{ __('recipes.menu.field.name') }}</th>
                <th>{{ __('recipes.menu.field.event_date') }}</th>
                <th class="text-right">{{ __('recipes.menu.field.guest_count') }}</th>
                <th class="text-right">{{ __('recipes.menu.field.dishes') }}</th>
                <th class="text-right">{{ __('recipes.field.actions') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($menus as $menu)
            <tr class="hover">
                <td>{{ $menu->name }}</td>
                <td>{{ $menu->event_date?->fdate() ?? '—' }}</td>
                <td class="text-right">{{ $menu->guest_count ?? '—' }}</td>
                <td class="text-right">{{ $menu->items_count }}</td>
                <td class="text-right">
                    <div class="flex justify-end">
                        <x-button :href="route('recipe-menus.show', $menu)" tone="ghost" size="xs">{{ __('recipes.menu.action.open') }}</x-button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-pagination :paginator="$menus" standing />
</x-index-page>
@endsection
