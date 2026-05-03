<x-dialog
    :title="$isEdit ? __('Bereitschaft bearbeiten') : __('Neue Bereitschaft')"
    :eyebrow="__('Bereitschaftsdienst')"
    icon="⏱"
    tone="info">
    @include('shifts._form_body')
</x-dialog>
