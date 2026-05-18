<x-modal
    :title="$isEdit ? __('Notdienst bearbeiten') : __('Neuer Notdienst')"
    :eyebrow="__('Notdienst-Einsatz')"
    icon="warning"
    tone="error"
    :action="$isEdit ? route('assignments.update', $assignment) : route('assignments.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Notdienst anlegen')">
    @include('assignments._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('assignments.destroy', $assignment) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <input type="hidden" name="_back" value="{{ request()->query('_back') ?? url()->previous() }}">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Notdienst löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
