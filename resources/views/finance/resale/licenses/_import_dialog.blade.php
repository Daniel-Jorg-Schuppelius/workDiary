{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  CSV-Import der Schlüssel eines Pakets (MVP-1024): eine Zeile je Lizenz.
  Normales Formular, keine Übernahme ohne Vorschau.
--}}
<x-modal
    :title="__('resale.license.action.import')"
    :eyebrow="$batch->reference"
    icon="upload" tone="primary"
    :action="route('finance.resale.licenses.batches.import.preview', $batch)"
    method="POST" enctype="multipart/form-data"
    :submit-label="__('resale.license.import.preview')">
    <x-form-group :legend="__('resale.license.action.import')" icon="upload" tone="primary" cols="1"
                  :description="__('resale.license.import.hint', ['columns' => implode(', ', array_merge(['position'], array_map(static fn (array $r): string => $r['code'] . ' = ' . $r['label'], $batch->keyRoles())))])">
        <x-input-field name="file" type="file" accept=".csv,text/csv,text/plain" :label="__('resale.license.import.file')" required />
        <a href="{{ route('finance.resale.licenses.batches.template', $batch) }}" class="link text-sm">{{ __('resale.license.action.template') }}</a>
    </x-form-group>
</x-modal>
