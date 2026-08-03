{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.title.connections') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.title.connections'))

@section('content')
<x-index-page :subtitle="__('domain.title.connections_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.domain-provider.create')"
                        show-label>{{ __('domain.connect.title') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="dns" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
    </x-slot:actions>

    <x-table :caption="__('domain.title.connections')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('domain.field.name') }}</x-table.th>
                <x-table.th>{{ __('domain.field.environment') }}</x-table.th>
                <x-table.th>{{ __('domain.field.status') }}</x-table.th>
                <x-table.th align="right">{{ __('domain.title.index') }}</x-table.th>
                <x-table.th align="right">{{ __('domain.title.reseller') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($connections as $connection)
            <tr>
                <td>
                    <div class="font-medium">{{ $connection->name }}</div>
                    <div class="text-xs text-base-content/60 font-mono">{{ $connection->login }}</div>
                </td>
                <td>{{ $connection->environment->label() }}</td>
                <td>
                    <x-status-badge :tone="$connection->status->badge()" size="sm">{{ $connection->status->label() }}</x-status-badge>
                    @unless ($connection->pilotConfirmed())
                        <x-status-badge tone="warning" size="sm">{{ __('domain.pilot.open') }}</x-status-badge>
                    @endunless
                    @if ($connection->hasConnectionError())
                        <x-status-badge tone="error" size="sm">{{ __('domain.health.attention') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $connection->projections_count }}</td>
                <td class="text-right tabular-nums">{{ $connection->reseller_accounts_count }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($canManage ?? false)
                        <x-action-form :action="route('admin.domain-provider.test', $connection)">
                            <x-icon-btn icon="wifi_tethering" size="xs" type="submit" :title="__('domain.action.test')" />
                        </x-action-form>
                        <x-action-form :action="route('admin.domain-provider.sync', $connection)">
                            <x-icon-btn icon="sync" size="xs" type="submit" :title="__('domain.action.sync')" />
                        </x-action-form>
                        @unless ($connection->pilotConfirmed())
                            <x-action-form :action="route('admin.domain-provider.pilot', $connection)">
                                <x-icon-btn icon="verified" size="xs" type="submit" :title="__('domain.action.confirm_pilot')" />
                            </x-action-form>
                        @endunless
                        <x-action-form :action="route('admin.domain-provider.destroy', $connection)" method="DELETE"
                                       :confirm="__('domain.action.disconnect_confirm')" confirm-tone="error">
                            <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :title="__('domain.action.disconnect')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" :title="__('domain.empty.connections')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
