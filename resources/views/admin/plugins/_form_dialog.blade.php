{{-- Variablen: $plugin, $setting, $schema, $state --}}
@php
    $action = route('admin.plugins.update', $plugin->id());
    $healthClass = match ($state?->last_health_status) {
        'ok' => 'badge-success',
        'degraded' => 'badge-warning',
        'failing' => 'badge-error',
        default => 'badge-ghost',
    };
@endphp

<x-modal
    :title="$plugin->name()"
    :eyebrow="__('Plugin-Einstellungen')"
    icon="settings"
    tone="primary"
    :action="$action"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-slot:header>
        <p class="text-xs text-base-content/60 mt-1">
            <code>{{ $plugin->id() }}</code>
            <span class="mx-1">·</span>
            {{ __('Version :ver', ['ver' => $plugin->version()]) }}
        </p>
    </x-slot:header>

    <x-slot:headerActions>
        <x-dialog-status-controls
            name="enabled"
            :active="$setting->enabled ?? false"
            :active-label="__('Plugin für diese Organisation aktiv')" />
    </x-slot:headerActions>

    @if ($state && $state->isAutoDisabled())
        <div role="alert" class="alert alert-error">
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <div>
                <div class="font-semibold">{{ __('Plugin ist auto-deaktiviert') }}</div>
                <div class="text-sm opacity-80">{{ $state->disabled_reason }}</div>
            </div>
        </div>
    @endif

    @if ($state && $state->last_health_status)
        <div class="text-sm">
            <span class="badge {{ $healthClass }} badge-sm">{{ $state->last_health_status }}</span>
            @if ($state->last_health_message)
                <span class="text-base-content/70 ml-1">{{ $state->last_health_message }}</span>
            @endif
            @if ($state->last_health_check_at)
                <span class="text-xs text-base-content/50 ml-2">{{ __('zuletzt :time geprüft', ['time' => $state->last_health_check_at->diffForHumans()]) }}</span>
            @endif
        </div>
    @endif

    @if ($plugin->settingsView() !== null)
        @include($plugin->settingsView(), ['plugin' => $plugin, 'setting' => $setting, 'schema' => $schema])
    @else
        @foreach ($schema as $field)
            @include('admin.plugins._field', ['field' => $field, 'setting' => $setting])
        @endforeach
    @endif

    @if ($state && $state->isAutoDisabled())
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('admin.plugins.reset-errors', $plugin->id()) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Failure-Counter zurücksetzen und Plugin wieder aktivieren?') }}"
                  data-confirm-tone="primary">
                @csrf
                <x-icon-btn icon="restart_alt" tone="warning" size="sm" type="submit" show-label>{{ __('Reset & Reaktivieren') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
