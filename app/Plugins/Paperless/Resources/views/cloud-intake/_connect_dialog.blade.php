{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Paperless-ngx als Quelle des Cloud-Dokumenteingangs verbinden (MVP-1314).
  Basis-URL und Token kommen aus den Plugin-Einstellungen; ohne sie zeigt der
  Dialog nur den Weg dorthin. Variablen: $configured, $instance, $connection, $pluginId.
--}}
@if ($configured)
    <x-modal
        :title="__('paperless::paperless.connect.title')"
        icon="description"
        tone="primary"
        :action="route('admin.cloud-intake.paperless.connect')"
        method="POST"
        :form-data="['data-entry-form' => '']"
        :submit-label="__('paperless::paperless.connect.submit')"
    >
        <x-form-group :legend="__('paperless::paperless.connect.legend')" icon="description" tone="primary" cols="1">
            <p class="text-sm text-muted">{{ __('paperless::paperless.connect.instance', ['instance' => $instance]) }}</p>
            <div class="fieldset">
                <label class="fieldset-label" for="pl-name">{{ __('paperless::paperless.connect.name') }}</label>
                <input id="pl-name" type="text" name="name" required maxlength="190"
                       value="{{ old('name', $connection->name ?? 'Paperless-ngx') }}"
                       class="input input-bordered w-full">
                @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-muted">{{ __('paperless::paperless.connect.hint') }}</p>
        </x-form-group>
    </x-modal>
@else
    <x-modal :title="__('paperless::paperless.connect.title')" icon="description" tone="primary">
        <x-empty-state icon="settings" :title="__('paperless::paperless.connect.not_configured')" compact />
        <x-slot:footerExtra>
            <x-button :href="route('admin.plugins.edit', $pluginId)" icon="settings" tone="primary">{{ __('paperless::paperless.connect.to_settings') }}</x-button>
        </x-slot:footerExtra>
    </x-modal>
@endif
