<x-dialog
    :title="$isEdit ? __('Notdienst bearbeiten') : __('Neuer Notdienst')"
    :eyebrow="__('Notdienst-Einsatz')"
    icon="⚠"
    tone="error">
    @include('assignments._form_body')
</x-dialog>
