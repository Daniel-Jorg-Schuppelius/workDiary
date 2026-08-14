{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _admin_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for AdminTimeEntry create/edit --}}
@php
    /** @var \App\Models\TimeEntry|null $entry */
    $isEdit = (bool) $entry;
    $action = $isEdit
        ? route('admin-time-entries.update', $entry)
        : route('admin-time-entries.store');
@endphp
<x-modal
    :title="$isEdit ? __('Verwaltungszeit bearbeiten') : __('Verwaltungszeit erfassen')"
    :eyebrow="$date"
    icon="access_time"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Erfassen')"
>
    @include('time-entries._admin_form_body')

    @if ($isEdit)
        @include('time-entries._edit_extras', ['entry' => $entry])
    @endif

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin-time-entries.destroy', $entry)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
