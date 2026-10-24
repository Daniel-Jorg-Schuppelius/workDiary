{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Seafile als Quelle des Cloud-Dokumenteingangs verbinden (MVP-1346).
  Adresse und Token kommen aus den Plugin-Einstellungen; ohne sie zeigt der
  Dialog nur den Weg dorthin. Variablen: $configured, $instance, $connection, $pluginId.
--}}
@if ($configured)
    <x-modal
        :title="__('seafile::seafile.connect.title')"
        icon="cloud_sync"
        tone="primary"
        :action="route('admin.cloud-intake.seafile.connect')"
        method="POST"
        :form-data="['data-entry-form' => '']"
        :submit-label="__('seafile::seafile.connect.submit')"
    >
        <x-form-group :legend="__('seafile::seafile.connect.legend')" icon="cloud_sync" tone="primary" cols="1">
            <p class="text-sm text-muted">{{ __('seafile::seafile.connect.instance', ['instance' => $instance]) }}</p>
            <div class="fieldset">
                <label class="fieldset-label" for="sf-name">{{ __('seafile::seafile.connect.name') }}</label>
                <input id="sf-name" type="text" name="name" required maxlength="190"
                       value="{{ old('name', $connection->name ?? 'Seafile') }}"
                       class="input input-bordered w-full">
                @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-muted">{{ __('seafile::seafile.connect.hint') }}</p>
        </x-form-group>
    </x-modal>
@else
    <x-modal :title="__('seafile::seafile.connect.title')" icon="cloud_sync" tone="primary">
        <x-empty-state icon="settings" :title="__('seafile::seafile.connect.not_configured')" compact />
        <x-slot:footerExtra>
            <x-button :href="route('admin.plugins.edit', $pluginId)" icon="settings" tone="primary">{{ __('seafile::seafile.connect.to_settings') }}</x-button>
        </x-slot:footerExtra>
    </x-modal>
@endif
