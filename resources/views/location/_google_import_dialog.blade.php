{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _google_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

<x-modal
    :title="__('Google-Timeline importieren')"
    :eyebrow="__('Standorterfassung')"
    icon="upload"
    tone="primary"
    size="md"
    :action="route('location.devices.import-google')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Importieren')">

    <x-form-group :legend="__('Standortverlauf')" icon="upload_file" tone="primary" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('JSON-Datei') }} *</span>
            <input type="file" name="file" accept=".json,application/json" required
                   class="file-input file-input-bordered w-full">
        </label>
    </x-form-group>

    <p class="text-sm text-base-content/70">
        {{ __('Lade einen Google-Standortverlauf (JSON-Export vom Handy) hoch. Der Import aktiviert deine Einwilligung.') }}
        {{ __('Export auf dem Handy: Google Maps → Zeitachse → Einstellungen → Zeitachsendaten exportieren.') }}
    </p>
</x-modal>
