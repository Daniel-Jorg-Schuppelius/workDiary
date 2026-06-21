<x-modal
    :title="$isEdit ? __('Bereitschaft bearbeiten') : __('Neue Bereitschaft')"
    :eyebrow="__('Bereitschaftsdienst')"
    icon="schedule"
    tone="info"
    :action="$isEdit ? route('shifts.update', $shift) : route('shifts.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Bereitschaft anlegen')">
    @include('shifts._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('shifts.destroy', $shift)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <input type="hidden" name="_back" value="{{ request()->query('_back') ?? url()->previous() }}">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Bereitschaft löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
