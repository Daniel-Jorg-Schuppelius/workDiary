{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Standorte'))
@section('nav-title', __('Standorte'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="$customer
    ? __('Standorte für :customer.', ['customer' => $customer->name])
    : __('Standorte aller Kunden verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('sites.create')"
                    show-label>{{ __('Standort anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @if ($sites->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">location_on</span>' />
    @else
        <x-table table-sort="server"
                 :route="route('sites.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="array_filter(['customer' => request()->query('customer')])"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <th>{{ __('Kunde') }}</th>
                    <x-table.th sort="address_city">{{ __('Ort') }}</x-table.th>
                    <x-table.th sort="is_active" align="right">{{ __('Aktiv') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($sites as $site)
                <tr>
                    <td>
                        <a class="link link-hover" href="{{ route('sites.show', $site) }}">{{ $site->name }}</a>
                        @if ($site->code)
                            <span class="text-base-content/60 ms-1">({{ $site->code }})</span>
                        @endif
                    </td>
                    <td>{{ $site->customer?->name }}</td>
                    <td>{{ trim(($site->address_zip ?? '').' '.($site->address_city ?? '')) }}</td>
                    <td class="text-end">
                        @if ($site->is_active)
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('inaktiv') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('sites.edit', $site)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$sites" />
    @endif
</x-index-page>
@endsection
