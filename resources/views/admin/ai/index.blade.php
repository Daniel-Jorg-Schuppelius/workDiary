{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('ai.title.connections') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('ai.title.connections'))

@section('content')
<x-index-page :subtitle="__('ai.title.connections_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.ai.create')"
                        show-label>{{ __('ai.connect.title') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="psychology" size="sm" :href="route('admin.ai.memory')" show-label>{{ __('ai.title.memory') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>
    @endif

    {{-- Monatsverbrauch (Budget-Transparenz, MVP-399) --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-kpi-tile :label="__('ai.field.usage_llm')"
                    :value="number_format((int) ($usage['llm']->used_units ?? 0), 0, ',', '.')"
                    tone="info" :hint="__('ai.field.usage_period', ['period' => now()->format('m/Y')])" />
        <x-kpi-tile :label="__('ai.field.usage_translation')"
                    :value="number_format((int) ($usage['translation']->used_units ?? 0), 0, ',', '.')"
                    tone="info" :hint="__('ai.field.usage_period', ['period' => now()->format('m/Y')])" />
    </div>

    {{-- Provider-Verbindungen --}}
    <x-card :title="__('ai.section.connections')">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ai.field.name') }}</th>
                        <th>{{ __('ai.field.family') }}</th>
                        <th>{{ __('ai.field.locality') }}</th>
                        <th>{{ __('ai.field.status') }}</th>
                        <th>{{ __('ai.field.model') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($connections as $connection)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $connection->name }}</div>
                                <div class="text-xs text-base-content/60 font-mono">{{ $connection->provider->label() }}</div>
                            </td>
                            <td>{{ $connection->family->label() }}</td>
                            <td>
                                <span class="badge badge-{{ $connection->is_local ? 'success' : 'warning' }} badge-sm">
                                    {{ $connection->is_local ? __('ai.field.local') : __('ai.field.cloud') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $connection->status->badge() }} badge-sm">{{ $connection->status->label() }}</span>
                                @if ($connection->isConnectionFailing())
                                    <span class="badge badge-error badge-sm" title="{{ $connection->last_error }}">{{ __('ai.health.attention') }}</span>
                                @endif
                                @if ($connection->preflight_at === null)
                                    <span class="badge badge-warning badge-sm">{{ __('ai.health.preflight_open') }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs">{{ $connection->model ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($canManage ?? false)
                                    <form method="POST" action="{{ route('admin.ai.test', $connection) }}" class="inline">
                                        @csrf
                                        <x-icon-btn icon="wifi_tethering" size="xs" type="submit" :title="__('ai.action.test')" />
                                    </form>
                                    @if ($connection->status === \App\Enums\Ai\AiConnectionStatus::Blocked)
                                        <form method="POST" action="{{ route('admin.ai.unblock', $connection) }}" class="inline">
                                            @csrf
                                            <x-icon-btn icon="lock_open" size="xs" type="submit" :title="__('ai.action.unblock')" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.ai.block', $connection) }}" class="inline">
                                            @csrf
                                            <x-icon-btn icon="lock" size="xs" type="submit" :title="__('ai.action.block')" />
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.ai.destroy', $connection) }}" class="inline"
                                          data-confirm-dialog data-confirm-message="{{ __('ai.action.delete_confirm') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :title="__('ai.action.delete')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('ai.empty.connections')" compact />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Capability-Matrix: Opt-in, Routing, Datenfluss (Feature 016/025) --}}
    <x-card :title="__('ai.section.capabilities')" :subtitle="__('ai.section.capabilities_subtitle')">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ai.field.capability') }}</th>
                        <th>{{ __('ai.field.verb') }}</th>
                        <th>{{ __('ai.field.sensitivity') }}</th>
                        <th>{{ __('ai.field.enabled') }}</th>
                        <th>{{ __('ai.field.default') }}</th>
                        <th>{{ __('ai.field.user_choice') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($capabilities as $capability)
                        @php
                            $setting = $settings[$capability->key] ?? null;
                            $defaultName = $setting?->default_connection_id !== null
                                ? $connections->firstWhere('id', $setting->default_connection_id)?->name
                                : null;
                        @endphp
                        <tr>
                            <td class="font-mono text-xs">{{ $capability->key }}</td>
                            <td>{{ $capability->verb->label() }}</td>
                            <td><span class="badge badge-outline badge-sm">{{ $capability->sensitivity->label() }}</span></td>
                            <td>
                                <span class="badge badge-{{ ($setting?->enabled ?? false) ? 'success' : 'neutral' }} badge-sm">
                                    {{ ($setting?->enabled ?? false) ? __('ai.field.enabled_yes') : __('ai.field.enabled_no') }}
                                </span>
                            </td>
                            <td class="text-xs">{{ $defaultName ?? '—' }}</td>
                            <td>{{ ($setting?->allow_user_choice ?? false) ? __('ai.field.enabled_yes') : __('ai.field.enabled_no') }}</td>
                            <td class="text-right whitespace-nowrap">
                                <x-icon-btn icon="visibility" size="xs" data-entry-modal-trigger
                                            :href="route('admin.ai.capability.preview', ['capability' => $capability->key])"
                                            :title="__('ai.action.preview')" />
                                @if ($canManage ?? false)
                                    <x-icon-btn icon="tune" size="xs" data-entry-modal-trigger
                                                :href="route('admin.ai.capability.edit', ['capability' => $capability->key])"
                                                :title="__('ai.action.edit')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" :title="__('ai.empty.capabilities')" compact />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-index-page>
@endsection
