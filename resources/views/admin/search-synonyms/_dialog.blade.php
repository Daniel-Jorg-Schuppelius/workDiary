{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Synonymgruppe anlegen/bearbeiten (modal-first). --}}
@php $isEdit = $group !== null; @endphp
<x-modal
    :title="$isEdit ? __('search.synonyms.action.edit') : __('search.synonyms.action.new')"
    icon="manage_search"
    tone="primary"
    :action="$isEdit ? route('admin.search-synonyms.update', $group) : route('admin.search-synonyms.store')"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('search.synonyms.action.submit')"
>
    <x-form-group :legend="__('search.synonyms.legend')" icon="manage_search" tone="primary" cols="1">
        <div class="fieldset">
            <label class="fieldset-label" for="ssyn-terms">{{ __('search.synonyms.field.terms') }}</label>
            <textarea id="ssyn-terms" name="terms" rows="6" required maxlength="2000"
                      class="textarea textarea-bordered w-full font-mono"
                      aria-describedby="ssyn-terms-help">{{ old('terms', $isEdit ? implode("\n", (array) $group->terms) : '') }}</textarea>
            <p id="ssyn-terms-help" class="text-xs text-muted">{{ __('search.synonyms.terms_help') }}</p>
            @error('terms')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
