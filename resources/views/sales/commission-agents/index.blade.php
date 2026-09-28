{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Externe Vermittler (MVP-989): Provisionsempfänger ohne Benutzerkonto.
  Variablen: $agents, $canManage
--}}
@extends('layouts.app')
@section('title', __('commission.page.agents'))
@section('nav-title', __('commission.page.agents'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('commission.subtitle.agents')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('commission-agents.create')"
                        show-label>{{ __('commission.action.create_agent') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @include('sales._commission_tabs')

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('commission.field.agent_name') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.company') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.email') }}</x-table.th>
                <x-table.th sort type="string" align="center">{{ __('commission.field.is_active') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($agents as $agent)
            <tr class="hover">
                <td class="font-medium">{{ $agent->name }}@if ($agent->note)<span class="block text-xs text-muted">{{ $agent->note }}</span>@endif</td>
                <td class="text-sm">{{ $agent->company ?? '–' }}</td>
                <td class="text-sm">{{ $agent->email ?? '–' }}</td>
                <td class="text-center">
                    <x-status-badge :tone="$agent->is_active ? 'success' : 'neutral'" size="sm">{{ $agent->is_active ? __('Ja') : __('Nein') }}</x-status-badge>
                </td>
                <td class="text-right">
                    @if ($canManage)
                        <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger
                                    :href="route('commission-agents.edit', $agent)" :label="__('commission.action.edit_agent')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="handshake" :colspan="5" :title="__('commission.empty.agents')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
