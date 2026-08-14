{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal
    :title="__('Klassifikationen importieren (CSV)')"
    icon="upload"
    tone="primary"
    size="lg"
    :action="route('admin.classifications.import')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Importieren')"
>
    <div>
        <label class="label" for="classification-import-file">
            <span class="label-text">{{ __('CSV-Datei') }}</span>
        </label>
        <input id="classification-import-file"
               type="file"
               name="file"
               accept=".csv,text/csv,text/plain"
               required
               class="file-input file-input-bordered w-full @error('file') file-input-error @enderror">
        @error('file')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="text-sm text-base-content/70 space-y-1">
        <p class="font-semibold">{{ __('Unterstützte Spalten') }}</p>
        <p>{{ __('Pflicht: domain, code, label') }}</p>
        <p>{{ __('Optional: sort_order, color_hex, icon') }}</p>
        <p>{{ __('Trennzeichen: Semikolon, Komma oder Tab. Kopfzeile erforderlich.') }}</p>
        <p>{{ __('Bestehende Org-Werte mit gleichem Domain+Code werden aktualisiert, neue Werte angelegt.') }}</p>
    </div>
</x-modal>
