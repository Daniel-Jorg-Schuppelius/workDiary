{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Personalstamm-CSV-Import (Feature 103, MVP-537) --}}
<x-modal
    :title="__('Personal importieren')"
    icon="upload_file"
    tone="primary"
    :action="route('org.members.import')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="__('Importieren')"
>
    <p class="mb-2 text-sm text-base-content/60">
        {{ __('CSV mit Kopfzeile name;email (optional personnel_number, role). Neue Benutzer erhalten ein Zufallspasswort, müssen es beim ersten Login ändern und starten im neuen System; vorhandene E-Mails werden mit Grund übersprungen.') }}
    </p>
    <div class="mb-3">
        <a href="{{ route('org.members.import.template') }}" class="link link-primary text-sm">{{ __('CSV-Vorlage herunterladen') }}</a>
    </div>
    <input type="file" name="csv" accept=".csv,.txt" class="file-input file-input-bordered w-full" required>
    @error('csv')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
</x-modal>
