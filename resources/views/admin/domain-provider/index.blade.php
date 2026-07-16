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

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>
    @endif

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('domain.field.name') }}</th>
                    <th>{{ __('domain.field.environment') }}</th>
                    <th>{{ __('domain.field.status') }}</th>
                    <th class="text-right">{{ __('domain.title.index') }}</th>
                    <th class="text-right">{{ __('domain.title.reseller') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($connections as $connection)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $connection->name }}</div>
                            <div class="text-xs text-base-content/60 font-mono">{{ $connection->login }}</div>
                        </td>
                        <td>{{ $connection->environment->label() }}</td>
                        <td>
                            <span class="badge badge-{{ $connection->status->badge() }} badge-sm">{{ $connection->status->label() }}</span>
                            @unless ($connection->pilotConfirmed())
                                <span class="badge badge-warning badge-sm">{{ __('domain.pilot.open') }}</span>
                            @endunless
                            @if ($connection->isConnectionFailing())
                                <span class="badge badge-error badge-sm">{{ __('domain.health.attention') }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $connection->projections_count }}</td>
                        <td class="text-right tabular-nums">{{ $connection->reseller_accounts_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($canManage ?? false)
                                <form method="POST" action="{{ route('admin.domain-provider.test', $connection) }}" class="inline">
                                    @csrf
                                    <x-icon-btn icon="wifi_tethering" size="xs" type="submit" :title="__('domain.action.test')" />
                                </form>
                                <form method="POST" action="{{ route('admin.domain-provider.sync', $connection) }}" class="inline">
                                    @csrf
                                    <x-icon-btn icon="sync" size="xs" type="submit" :title="__('domain.action.sync')" />
                                </form>
                                @unless ($connection->pilotConfirmed())
                                    <form method="POST" action="{{ route('admin.domain-provider.pilot', $connection) }}" class="inline">
                                        @csrf
                                        <x-icon-btn icon="verified" size="xs" type="submit" :title="__('domain.action.confirm_pilot')" />
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.domain-provider.destroy', $connection) }}" class="inline"
                                      onsubmit="return confirm('{{ __('domain.action.disconnect_confirm') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :title="__('domain.action.disconnect')" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-base-content/60 py-8">{{ __('domain.empty.connections') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-index-page>
@endsection
