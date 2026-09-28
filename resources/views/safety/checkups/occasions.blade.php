{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : occasions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Katalog der Vorsorgeanlässe (MVP-986). Variablen: $occasions, $canManage
--}}
@extends('layouts.app')
@section('title', __('safety.register.title.occasions'))
@section('nav-title', __('safety.register.title.occasions'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.register.subtitle.occasions')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('safety.checkups.index')" show-label>{{ __('safety.register.title.checkups') }}</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('safety.checkups.occasions.create')"
                        show-label>{{ __('safety.register.action.create_occasion') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('safety.register.field.occasion') }}</th>
                <th>{{ __('safety.register.field.kind') }}</th>
                <th>{{ __('safety.register.field.interval_months') }}</th>
                <th>{{ __('safety.register.field.is_active') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($occasions as $occasion)
            <tr class="hover">
                <td class="font-medium">{{ $occasion->label }}</td>
                <td><x-status-badge :tone="$occasion->kind->tone()" size="sm" outline>{{ $occasion->kind->label() }}</x-status-badge></td>
                <td class="text-sm">{{ $occasion->interval_months ?? '–' }}</td>
                <td>
                    <x-status-badge :tone="$occasion->is_active ? 'success' : 'neutral'" size="sm">
                        {{ $occasion->is_active ? __('Ja') : __('Nein') }}
                    </x-status-badge>
                </td>
                <td class="text-right">
                    @if ($canManage)
                        <x-icon-btn icon="edit" tone="outline" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('safety.checkups.occasions.edit', $occasion)"
                                    :label="__('safety.register.action.edit_occasion')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="event_repeat" :colspan="5" :title="__('safety.register.empty.occasions')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
