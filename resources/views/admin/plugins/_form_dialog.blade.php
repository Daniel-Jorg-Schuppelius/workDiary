{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $plugin, $setting, $schema, $state --}}
@php
    $action = route('admin.plugins.update', $plugin->id());
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
        <p class="text-xs text-muted mt-1">
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
            <x-icon name="error" />
            <div>
                <div class="font-semibold">{{ __('Plugin ist auto-deaktiviert') }}</div>
                <div class="text-sm opacity-80">{{ $state->disabled_reason }}</div>
            </div>
        </div>
    @endif

    <x-plugin-health :plugin-id="$plugin->id()" :state="$state" detailed />

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
            <x-icon name="info" />
            <span>{{ $configLinks === [] ? __('Dieses Plugin hat keine dialogbasierten Einstellungen.') : __('Dieses Plugin wird auf eigenen Seiten konfiguriert:') }}</span>
        </div>
        @if ($configLinks !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($configLinks as $link)
                    <a href="{{ $link['url'] }}" class="btn btn-sm btn-outline">
                        <x-icon name="{{ $link['icon'] }}" />
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
