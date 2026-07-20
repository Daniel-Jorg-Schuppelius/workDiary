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

    {{-- Monatsverbrauch (Budget-Transparenz, MVP-399) --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-kpi-tile :label="__('ai.field.usage_llm')"
                    :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) ($usage['llm']->used_units ?? 0), 0, withThousandsSeparator: true)"
                    tone="info" :hint="__('ai.field.usage_period', ['period' => now()->format('m/Y')])" />
        <x-kpi-tile :label="__('ai.field.usage_translation')"
                    :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) ($usage['translation']->used_units ?? 0), 0, withThousandsSeparator: true)"
                    tone="info" :hint="__('ai.field.usage_period', ['period' => now()->format('m/Y')])" />
    </div>

    {{-- Provider-Verbindungen --}}
    <x-card :title="__('ai.section.connections')" padding="p-0">
        <x-table bare :caption="__('ai.section.connections')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('ai.field.name') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.family') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.locality') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.status') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.model') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($connections as $connection)
                <tr>
                    <td>
                        <div class="font-medium">{{ $connection->name }}</div>
                        <div class="text-xs text-base-content/60 font-mono">{{ $connection->provider->label() }}</div>
                    </td>
                    <td>{{ $connection->family->label() }}</td>
                    <td>
                        <x-status-badge :tone="$connection->is_local ? 'success' : 'warning'" size="sm">
                            {{ $connection->is_local ? __('ai.field.local') : __('ai.field.cloud') }}
                        </x-status-badge>
                    </td>
                    <td>
                        <x-status-badge :tone="$connection->status->badge()" size="sm">{{ $connection->status->label() }}</x-status-badge>
                        @if ($connection->isConnectionFailing())
                            <x-status-badge tone="error" size="sm" :title="$connection->last_error">{{ __('ai.health.attention') }}</x-status-badge>
                        @endif
                        @if ($connection->preflight_at === null)
                            <x-status-badge tone="warning" size="sm">{{ __('ai.health.preflight_open') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="font-mono text-xs">{{ $connection->model ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($canManage ?? false)
                            <x-action-form :action="route('admin.ai.test', $connection)">
                                <x-icon-btn icon="wifi_tethering" size="xs" type="submit" :title="__('ai.action.test')" />
                            </x-action-form>
                            @if ($connection->status === \App\Enums\Ai\AiConnectionStatus::Blocked)
                                <x-action-form :action="route('admin.ai.unblock', $connection)">
                                    <x-icon-btn icon="lock_open" size="xs" type="submit" :title="__('ai.action.unblock')" />
                                </x-action-form>
                            @else
                                <x-action-form :action="route('admin.ai.block', $connection)">
                                    <x-icon-btn icon="lock" size="xs" type="submit" :title="__('ai.action.block')" />
                                </x-action-form>
                            @endif
                            <x-action-form :action="route('admin.ai.destroy', $connection)" method="DELETE"
                                           :confirm="__('ai.action.delete_confirm')" confirm-tone="error">
                                <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :title="__('ai.action.delete')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('ai.empty.connections')" compact />
            @endforelse
        </x-table>
    </x-card>

    {{-- Capability-Matrix: Opt-in, Routing, Datenfluss (Feature 016/025) --}}
    <x-card :title="__('ai.section.capabilities')" :subtitle="__('ai.section.capabilities_subtitle')" padding="p-0">
        <x-table bare :caption="__('ai.section.capabilities')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('ai.field.capability') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.verb') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.sensitivity') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.enabled') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.default') }}</x-table.th>
                    <x-table.th>{{ __('ai.field.user_choice') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($capabilities as $capability)
                @php
                    $setting = $settings[$capability->key] ?? null;
                    $defaultName = $setting?->default_connection_id !== null
                        ? $connections->firstWhere('id', $setting->default_connection_id)?->name
                        : null;
                @endphp
                <tr>
                    <td>
                        <div class="font-medium">{{ $capability->label() }}</div>
                        <div class="text-xs text-base-content/60 font-mono">{{ $capability->key }}</div>
                    </td>
                    <td>{{ $capability->verb->label() }}</td>
                    <td><x-status-badge tone="ghost" size="sm" outline>{{ $capability->sensitivity->label() }}</x-status-badge></td>
                    <td>
                        <x-status-badge :tone="($setting?->enabled ?? false) ? 'success' : 'neutral'" size="sm">
                            {{ ($setting?->enabled ?? false) ? __('ai.field.enabled_yes') : __('ai.field.enabled_no') }}
                        </x-status-badge>
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
        </x-table>
    </x-card>
</x-index-page>
@endsection
