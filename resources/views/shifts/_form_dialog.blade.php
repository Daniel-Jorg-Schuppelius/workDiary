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
            <form method="POST" action="{{ route('shifts.destroy', $shift) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <input type="hidden" name="_back" value="{{ request()->query('_back') ?? url()->previous() }}">
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Bereitschaft löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
