{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
            <x-action-form :action="route('admin.per-diem-rates.destroy', $rate)" method="DELETE"
                  :confirm="__('Pauschalensatz wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
