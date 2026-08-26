{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('maintenance.window.title'))
@section('nav-title', __('maintenance.window.title'))

@section('content')
<x-index-page :subtitle="__('maintenance.window.subtitle')">
    <x-slot:actions>
        <x-button data-entry-modal-trigger :href="route('admin.maintenance-windows.create')" tone="primary" size="sm" icon="add">
            {{ __('maintenance.window.action.plan') }}
        </x-button>
    </x-slot:actions>

    @if ($windows->isEmpty())
        <x-empty-state framed icon="engineering" :title="__('maintenance.window.empty.title')" :message="__('maintenance.window.empty.message')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('maintenance.window.field.window') }}</th>
                    <th>{{ __('maintenance.window.field.scope') }}</th>
                    <th>{{ __('maintenance.window.field.mode') }}</th>
                    <th>{{ __('maintenance.window.field.status') }}</th>
                    <th class="text-right">{{ __('maintenance.window.field.actions') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($windows as $window)
                <tr>
                    <td>
                        <div class="font-medium">{{ $window->starts_at->format('d.m.Y H:i') }} – {{ $window->ends_at->format('d.m.Y H:i') }}</div>
                        @if ($window->message)
                            <div class="text-xs text-muted">{{ $window->message }}</div>
                        @endif
                        @if ($window->announce_from)
                            <div class="text-xs text-muted">{{ __('maintenance.window.field.announce_from') }}: {{ $window->announce_from->format('d.m.Y H:i') }}</div>
                        @endif
                    </td>
                    <td>
                        <x-status-badge size="xs" :tone="$window->scope === 'system' ? 'info' : 'ghost'">
                            {{ __('maintenance.window.scope.' . $window->scope) }}
                        </x-status-badge>
                    </td>
                    <td class="text-sm">
                        {{ $window->read_only ? __('maintenance.window.mode.read_only') : __('maintenance.window.mode.full') }}
                        @if ($window->block_ingest)
                            <x-status-badge size="xs" tone="warning">{{ __('maintenance.window.mode.block_ingest') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        <x-status-badge size="xs" :tone="match($window->status) { 'active', 'extended' => 'error', 'announced' => 'warning', 'planned' => 'info', 'completed' => 'success', default => 'neutral' }">
                            {{ __('maintenance.window.status.' . $window->status) }}
                        </x-status-badge>
                    </td>
                    <td class="text-right">
                        <div class="inline-flex flex-wrap items-center justify-end gap-1">
                            @if ($window->status === 'planned')
                                <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'announce']) }}">@csrf<x-icon-btn icon="campaign" type="submit" :label="__('maintenance.window.action.announce')" /></form>
                            @endif
                            @if (in_array($window->status, ['planned', 'announced'], true))
                                <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'start']) }}">@csrf<x-icon-btn icon="play_circle" type="submit" :label="__('maintenance.window.action.start')" /></form>
                                <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'cancel']) }}">@csrf<x-icon-btn icon="cancel" type="submit" :label="__('maintenance.window.action.cancel')" /></form>
                            @endif
                            @if (in_array($window->status, ['active', 'extended'], true))
                                <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'complete']) }}">@csrf<x-icon-btn icon="check_circle" type="submit" :label="__('maintenance.window.action.complete')" /></form>
                                <details class="dropdown dropdown-end">
                                    <summary class="btn btn-ghost btn-xs btn-circle" title="{{ __('maintenance.window.action.extend') }}"><x-icon name="more_time" /></summary>
                                    <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'extend']) }}"
                                          class="dropdown-content z-20 w-64 space-y-2 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg">
                                        @csrf
                                        <input type="datetime-local" name="ends_at" required class="input input-bordered input-sm w-full"
                                               value="{{ $window->ends_at->addHour()->format('Y-m-d\TH:i') }}">
                                        <x-button type="submit" tone="primary" size="sm" class="w-full">{{ __('maintenance.window.action.extend') }}</x-button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.maintenance-windows.transition', [$window, 'rollback']) }}">@csrf<x-icon-btn icon="undo" type="submit" :label="__('maintenance.window.action.rollback')" /></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-pagination :paginator="$windows" standing />
</x-index-page>
@endsection
