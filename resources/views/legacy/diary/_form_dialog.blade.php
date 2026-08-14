{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Header für Legacy-Diary Form-Dialog. Erwartet: $isEdit --}}
<x-modal
    :title="$isEdit ? __('Legacy-Eintrag bearbeiten') : __('Neuen Legacy-Eintrag anlegen')"
    :eyebrow="__('Legacy Eintrag')"
    icon="info"
    :badge="$isEdit ? __('Bearbeiten') : __('Neu')"
    badge-tone="outline"
    tone="warning"
    :action="$isEdit ? route('legacy.diary.update', $entry) : route('legacy.diary.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Eintrag anlegen')">
    @include('legacy.diary._form_body', ['isDialog' => true])
</x-modal>
