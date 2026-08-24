{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $menu->name . ' — ' . __('recipes.menu.title'))
@section('nav-title', __('recipes.menu.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-card>
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $menu->name }}</h1>
                <a href="{{ route('recipe-menus.index') }}" class="btn btn-sm btn-ghost">{{ __('recipes.action.back') }}</a>
            </div>
            <p class="text-sm text-base-content/60">
                {{ $menu->event_date?->format('d.m.Y') ?? __('recipes.menu.no_date') }}
                @if ($menu->guest_count) · {{ __('recipes.menu.field.guest_count') }}: {{ $menu->guest_count }} @endif
            </p>
        </x-card>

        {{-- Gerichte --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.menu.dishes_heading') }}</h2>

            <form method="POST" action="{{ route('recipe-menus.items.store', $menu) }}" class="mb-3 flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.menu.field.dish') }}</span>
                    <select name="dish" required class="select select-bordered select-sm min-w-64">
                        <option value="">{{ __('recipes.menu.field.dish_placeholder') }}</option>
                        @foreach ($dishOptions as $dish)
                            <option value="{{ $dish->sqid }}">{{ $dish->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.menu.field.portions_per_guest') }}</span>
                    <input type="number" name="portions_per_guest" step="0.01" min="0.01" max="100" value="1" class="input input-bordered input-sm w-28">
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('recipes.menu.action.add_dish') }}</button>
            </form>

            <x-table :bare="true" :empty-title="__('recipes.menu.no_dishes')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('recipes.menu.field.dish') }}</th>
                        <th class="text-right">{{ __('recipes.menu.field.portions_per_guest') }}</th>
                        <th class="text-right">{{ __('recipes.menu.field.portions_total') }}</th>
                        <th>{{ __('recipes.menu.field.version') }}</th>
                        <th class="text-right">{{ __('recipes.field.actions') }}</th>
                    </tr>
                </x-slot:head>
                            @foreach ($aggregate['dishes'] as $dish)
                                <tr class="hover">
                                    <td>{{ $dish['item']->template?->name }}</td>
                                    <td class="text-right">{{ $dish['item']->portions_per_guest }}</td>
                                    <td class="text-right">{{ $dish['portions'] }}</td>
                                    <td>
                                        @if ($dish['version'] !== null)
                                            v{{ $dish['version']->version }}
                                        @else
                                            <span class="badge badge-warning badge-sm">{{ __('recipes.menu.not_published') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end">
                                            <form method="POST" action="{{ route('recipe-menus.items.destroy', [$menu, $dish['item']]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('recipes.action.remove') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
            </x-table>
        </x-card>

        {{-- Allergene des Menüs --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.title.allergens') }}</h2>
            <div class="flex flex-wrap gap-1">
                @forelse ($allergens['effective'] as $code)
                    <span class="badge badge-warning badge-sm">{{ $code }}</span>
                @empty
                    <span class="text-sm text-base-content/60">{{ __('recipes.allergens.none') }}</span>
                @endforelse
            </div>
            @if ($allergens['unresolved'] !== [])
                <p class="mt-2 text-xs text-warning">{{ __('recipes.allergens.unresolved_heading') }}: {{ implode(', ', $allergens['unresolved']) }}</p>
            @endif
        </x-card>

        {{-- Aggregierter Materialbedarf --}}
        <x-card>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('recipes.menu.aggregate_heading') }}</h2>
                <form method="GET" action="{{ route('recipe-menus.show', $menu) }}" class="flex items-center gap-2">
                    <label class="text-sm" for="menu-guests">{{ __('recipes.menu.field.guest_count') }}</label>
                    <input id="menu-guests" type="number" name="guests" min="1" max="100000" value="{{ $guests }}" class="input input-bordered input-sm w-28">
                    <button type="submit" class="btn btn-sm">{{ __('recipes.action.scale') }}</button>
                </form>
            </div>

            @if ($aggregate['missing_published'] !== [])
                <div class="alert alert-warning mb-2 text-sm">{{ __('recipes.menu.missing_published', ['dishes' => implode(', ', $aggregate['missing_published'])]) }}</div>
            @endif

            <x-table :bare="true" :empty-title="__('recipes.menu.no_materials')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('recipes.field.article') }}</th>
                        <th class="text-right">{{ __('recipes.field.demand') }}</th>
                        <th>{{ __('recipes.field.unit') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($aggregate['materials'] as $row)
                    <tr class="hover">
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ $row['demand'] }}</td>
                        <td>{{ $row['unit'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>
</x-page-shell>
@endsection
