{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.serial.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.serial.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.serial.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="qr_code_scanner" size="sm" :href="route('serials.verify')" show-label>{{ __('inventory.serial.action.verify') }}</x-icon-btn>
        @can(\App\Enums\User\Permission::OrganizationUpdate->value)
            <x-icon-btn icon="badge" size="sm" data-entry-modal-trigger :href="route('serials.passport.edit')" show-label>{{ __('inventory.serial.passport.title') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('serials.index')">
        <x-filter-field :label="__('Suche')" for="ser-q" class="flex-1 min-w-60">
            <input id="ser-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-filter-field :label="__('inventory.serial.field.status')" for="ser-status">
            <select id="ser-status" name="status" class="select select-sm select-bordered">
                <option value="all" @selected($status === 'all')>{{ __('Alle') }}</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table :zebra="true" scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('inventory.serial.field.serial_no') }}</th>
                <th>{{ __('inventory.serial.field.article') }}</th>
                <th>{{ __('inventory.serial.field.status') }}</th>
                <th>{{ __('inventory.serial.field.customer') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($serials as $serial)
            <tr>
                <td><a href="{{ route('serials.show', $serial) }}" class="link link-hover font-mono">{{ $serial->serial_no }}</a></td>
                <td>{{ $serial->article?->name }}</td>
                <td><span class="badge badge-sm badge-ghost">{{ $serial->status->label() }}</span></td>
                <td>{{ $serial->customer?->name ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4"
                           icon="qr_code_2"
                           :title="__('inventory.serial.empty')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$serials" standing />
</x-index-page>
@endsection
