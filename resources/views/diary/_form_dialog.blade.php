{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Diary Form Dialog Body. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
<x-modal
    :title="$isEdit ? __('Auftrag bearbeiten') : __('Neuen Auftrag anlegen')"
    :eyebrow="__('Auftrag')"
    icon="edit_note"
    :badge="$isEdit ? __('Bearbeiten') : __('Neu')"
    badge-tone="outline"
    tone="primary"
    :action="$isEdit ? route('diary.update', $entry) : route('diary.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Auftrag anlegen')">
    @include('diary._form_body', ['isDialog' => true])
</x-modal>
