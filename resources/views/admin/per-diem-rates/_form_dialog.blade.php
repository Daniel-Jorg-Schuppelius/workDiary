{{-- Dialog wrapper for PerDiemRate create/edit --}}
@php
    /** @var \App\Models\PerDiemRate $rate */
    $isEdit = $rate?->exists ?? false;
@endphp
<x-modal
    :title="$isEdit ? __('Pauschalensatz bearbeiten') : __('Pauschalensatz anlegen')"
    :eyebrow="$isEdit ? ($rate->country . ' · ' . \Illuminate\Support\Carbon::parse($rate->valid_from)->fdate()) : null"
    icon="restaurant_menu"
    tone="primary"
    :action="$isEdit ? route('admin.per-diem-rates.update', $rate) : route('admin.per-diem-rates.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    @include('admin.per-diem-rates._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('admin.per-diem-rates.destroy', $rate) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Pauschalensatz wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
