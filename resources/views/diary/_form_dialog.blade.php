{{-- Diary Form Dialog Body. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
<x-dialog
    :title="$isEdit ? __('Eintrag bearbeiten') : __('Neuen Eintrag anlegen')"
    :eyebrow="__('Tagebuch')"
    icon="✎"
    :badge="$isEdit ? __('Bearbeiten') : __('Neu')"
    badge-tone="outline"
    tone="primary">
    @include('diary._form_body')
</x-dialog>
