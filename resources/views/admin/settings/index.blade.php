@extends('layouts.app')

@section('title', __('settingsregistry.title.index'))
@section('nav-title', __('settingsregistry.title.index'))

@section('content')
<x-index-page :subtitle="__('settingsregistry.title.subtitle')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.settings.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="scope" class="select select-bordered select-sm" data-autosubmit>
                <option value="system" @selected($scope->value === 'system')>{{ __('settingsregistry.scopes.system') }}</option>
                <option value="organization" @selected($scope->value === 'organization')>{{ __('settingsregistry.scopes.organization') }}</option>
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('settingsregistry.field.search') }}"
                   class="input input-bordered input-sm w-56">
        </form>
        {{-- Konfigurationsstand-Export (Feature 067 P5; Vollaudit 2026-07, N20). --}}
        <x-icon-btn icon="download" tone="ghost" size="sm"
                    :href="route('admin.settings.export', ['scope' => $scope->value])"
                    show-label>{{ __('settingsregistry.action.export') }}</x-icon-btn>
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div class="text-sm">{{ __('settingsregistry.title.help_text') }}</div>
    </div>

    @forelse ($groups as $group => $rows)
        <article class="mt-4 rounded-2xl border border-base-300 bg-base-100 p-4">
            <h2 class="mb-3 font-semibold capitalize">{{ $group }}</h2>
            <div class="space-y-3">
                @foreach ($rows as $row)
                    @php
                        /** @var \App\Settings\SettingDefinition $definition */
                        $definition = $row['definition'];
                        /** @var \App\Settings\EffectiveValue $effective */
                        $effective = $row['effective'];
                        $isOverride = ($scope->value === 'system' && $effective->source === \App\Settings\SettingSource::System)
                            || ($scope->value === 'organization' && $effective->source === \App\Settings\SettingSource::Organization);
                        $displayValue = $definition->sensitive ? null : $effective->value;
                    @endphp
                    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-base-300/60 p-3">
                        <div class="min-w-64 flex-1">
                            <div class="font-mono text-sm">{{ $definition->key }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs">
                                <x-status-badge size="xs" tone="ghost">{{ $definition->type->value }}</x-status-badge>
                                <x-status-badge size="xs" :tone="$isOverride ? 'warning' : 'ghost'">
                                    {{ __('settingsregistry.sources.' . $effective->source->value) }}
                                </x-status-badge>
                                @if ($definition->sensitive)
                                    <x-status-badge size="xs" tone="error">{{ __('settingsregistry.field.sensitive') }}</x-status-badge>
                                @endif
                                @if ($definition->affects !== [])
                                    <span class="text-base-content/50">{{ __('settingsregistry.field.affects') }}: {{ implode(', ', $definition->affects) }}</span>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.update', ['key' => $definition->key]) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="scope" value="{{ $scope->value }}">
                            <input type="hidden" name="q" value="{{ $search }}">
                            <div class="fieldset">
                                @if ($definition->type === \App\Settings\SettingType::Boolean)
                                    <select name="value" class="select select-bordered select-sm">
                                        <option value="1" @selected($displayValue === true)>{{ __('Ja') }}</option>
                                        <option value="0" @selected($displayValue === false)>{{ __('Nein') }}</option>
                                    </select>
                                @elseif ($definition->options !== null)
                                    <select name="value" class="select select-bordered select-sm">
                                        @foreach ($definition->options as $option)
                                            <option value="{{ $option }}" @selected((string) $displayValue === (string) $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($definition->type === \App\Settings\SettingType::Time)
                                    <input type="time" name="value" class="input input-bordered input-sm"
                                           value="{{ is_string($displayValue) ? $displayValue : '' }}">
                                @elseif (in_array($definition->type, [\App\Settings\SettingType::Integer, \App\Settings\SettingType::Duration, \App\Settings\SettingType::Decimal], true))
                                    <input type="number" name="value" class="input input-bordered input-sm w-28"
                                           step="{{ $definition->type === \App\Settings\SettingType::Decimal ? '0.01' : '1' }}"
                                           value="{{ $displayValue !== null && is_scalar($displayValue) ? $displayValue : '' }}">
                                @elseif ($definition->type === \App\Settings\SettingType::Text)
                                    <textarea name="value" rows="6" class="textarea textarea-bordered textarea-sm w-96 font-mono text-xs"
                                              placeholder="{{ $definition->sensitive ? __('settingsregistry.field.sensitive_placeholder') : '' }}">{{ $definition->sensitive ? '' : (is_string($displayValue) ? $displayValue : '') }}</textarea>
                                @else
                                    <input type="{{ $definition->sensitive ? 'password' : 'text' }}" name="value" class="input input-bordered input-sm w-64"
                                           placeholder="{{ $definition->sensitive ? __('settingsregistry.field.sensitive_placeholder') : '' }}"
                                           value="{{ $definition->sensitive ? '' : (is_scalar($displayValue) ? $displayValue : json_encode($displayValue)) }}">
                                @endif
                            </div>
                            <x-button type="submit" tone="primary" size="sm">{{ __('settingsregistry.action.save') }}</x-button>
                        </form>
                        @if ($isOverride)
                            <form method="POST" action="{{ route('admin.settings.reset', ['key' => $definition->key]) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="scope" value="{{ $scope->value }}">
                                <x-button type="submit" tone="ghost" size="sm" icon="restart_alt">{{ __('settingsregistry.action.reset') }}</x-button>
                            </form>
                        @endif
                        @if ($scope->value === 'system')
                            <x-icon-btn icon="history" data-entry-modal-trigger
                                        :href="route('admin.settings.history', ['key' => $definition->key])"
                                        :label="__('settingsregistry.action.history')" />
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    @empty
        <x-empty-state framed icon="tune" :title="__('settingsregistry.empty.title')" :message="__('settingsregistry.empty.message')" />
    @endforelse
</x-index-page>
@endsection
