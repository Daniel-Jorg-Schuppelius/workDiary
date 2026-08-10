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

    {{-- Sofort-Healthcheck (testet die aktuell gespeicherte Konfiguration). --}}
    <div class="mt-1" x-data="pluginHealthCheck('{{ route('admin.plugins.health-check', $plugin->id()) }}', '{{ csrf_token() }}', '{{ __('Verbindung fehlgeschlagen.') }}')">
        <button type="button" class="btn btn-ghost btn-xs" :disabled="testing" @click="run()">
            <span x-show="idle">{{ __('Verbindung testen') }}</span>
            <span x-show="testing" x-cloak>{{ __('Wird geprüft …') }}</span>
        </button>
        <template x-if="result">
            <span class="ml-2 text-sm" x-text="resultText"></span>
        </template>
    </div>

    @if ($plugin->settingsView() !== null)
        @include($plugin->settingsView(), ['plugin' => $plugin, 'setting' => $setting, 'schema' => $schema])
    @elseif ($schema !== [])
        @foreach ($schema as $field)
            @include('admin.plugins._field', ['field' => $field, 'setting' => $setting])
        @endforeach
    @else
        {{-- Kein Schema/keine View: Konfigurationsorte verlinken statt leerem Dialog
             (Admin-Panel bzw. Intake-/Backup-Seiten je Capability, Gate-gefiltert). --}}
        @php
            $configLinks = [];
            $panel = $plugin->adminPanel();
            if ($panel !== null && ! empty($panel['route']) && $panel['route'] !== 'admin.plugins.edit') {
                $routeDef = \Illuminate\Support\Facades\Route::getRoutes()->getByName((string) $panel['route']);
                if ($routeDef !== null) {
                    $params = count($routeDef->parameterNames()) > 0 ? [$plugin->id()] : [];
                    $configLinks[] = ['url' => route((string) $panel['route'], $params), 'label' => $panel['label'] ?? $plugin->name(), 'icon' => $panel['icon'] ?? 'settings'];
                }
            }
            $caps = $plugin->capabilities();
            if (in_array(\App\Plugins\Contracts\PluginCapability::DocumentIntake, $caps, true)
                && auth()->user()?->can('viewAny', \App\Models\CloudIntake\CloudDocumentConnection::class)) {
                $configLinks[] = ['url' => route('admin.cloud-intake.index'), 'label' => __('cloud_intake.title.index'), 'icon' => 'cloud_download'];
            }
            if (in_array(\App\Plugins\Contracts\PluginCapability::BackupTarget, $caps, true)
                && auth()->user()?->can('viewAny', \App\Models\Backup\BackupTargetConnection::class)) {
                $configLinks[] = ['url' => route('admin.backup-targets.index'), 'label' => __('backup_targets.title'), 'icon' => 'cloud_upload'];
            }
            $configLinks = collect($configLinks)->unique('url')->all();
        @endphp
        <div class="alert alert-info text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">info</span>
            <span>{{ $configLinks === [] ? __('Dieses Plugin hat keine dialogbasierten Einstellungen.') : __('Dieses Plugin wird auf eigenen Seiten konfiguriert:') }}</span>
        </div>
        @if ($configLinks !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($configLinks as $link)
                    <a href="{{ $link['url'] }}" class="btn btn-sm btn-outline">
                        <span class="material-symbols-outlined" aria-hidden="true">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    @if ($state && $state->isAutoDisabled())
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.plugins.reset-errors', $plugin->id())"
                  :confirm="__('Failure-Counter zurücksetzen und Plugin wieder aktivieren?')"
                  confirm-tone="primary">
                <x-icon-btn icon="restart_alt" tone="warning" size="sm" type="submit" show-label>{{ __('Reset & Reaktivieren') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
