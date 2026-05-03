{{-- Header für Legacy-Diary Form-Dialog. Erwartet: $isEdit --}}
<x-dialog
    :title="$isEdit ? __('Legacy-Eintrag bearbeiten') : __('Neuen Legacy-Eintrag anlegen')"
    :eyebrow="__('Legacy Eintrag')"
    icon="ⓘ"
    :badge="$isEdit ? __('Bearbeiten') : __('Neu')"
    badge-tone="outline"
    tone="warning">
    @include('legacy.diary._form_body', ['isDialog' => true])
</x-dialog>
