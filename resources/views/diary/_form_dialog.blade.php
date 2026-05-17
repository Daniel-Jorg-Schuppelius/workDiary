{{-- Diary Form Dialog Body. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
<x-modal
    :title="$isEdit ? __('Eintrag bearbeiten') : __('Neuen Eintrag anlegen')"
    :eyebrow="__('Tagebuch')"
    icon="edit_note"
    :badge="$isEdit ? __('Bearbeiten') : __('Neu')"
    badge-tone="outline"
    tone="primary"
    :action="$isEdit ? route('diary.update', $entry) : route('diary.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Eintrag anlegen')">
    @include('diary._form_body', ['isDialog' => true])
</x-modal>
