{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog eigene Anforderung (in #entry-modal
  geladen). Katalog-Anforderungen: Norm/Edition/Ref-Nr. sind Referenz und
  unveränderlich (RequirementService), nur der Kurztitel ist pflegbar.
  Variablen: $requirement (IsmsRequirement|null)
--}}
@php
    $isEdit = $requirement !== null;
    $isCatalog = $isEdit && $requirement->source === \App\Enums\Isms\RequirementSource::Catalog;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_requirement') : __('isms.action.create_requirement')"
    :eyebrow="__('isms.title.requirements')"
    icon="checklist"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.requirements.update', $requirement) : route('isms.requirements.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_requirement')">

    <x-form-group :legend="__('isms.group.requirement')" icon="checklist" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.norm') }} @unless ($isCatalog) * @endunless</span>
            <input type="text" name="norm" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('norm', $requirement?->norm) }}"
                   placeholder="{{ __('isms.hint.norm') }}"
                   @if ($isCatalog) readonly @else required @endif>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.edition') }}</span>
            <input type="text" name="edition" maxlength="16"
                   class="input input-bordered w-full font-mono"
                   value="{{ old('edition', $requirement?->edition) }}"
                   placeholder="{{ __('isms.hint.edition') }}"
                   @if ($isCatalog) readonly @endif>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.ref_no') }} @unless ($isCatalog) * @endunless</span>
            <input type="text" name="ref_no" maxlength="24"
                   class="input input-bordered w-full font-mono"
                   value="{{ old('ref_no', $requirement?->ref_no) }}"
                   placeholder="{{ __('isms.hint.ref_no') }}"
                   @if ($isCatalog) readonly @else required @endif>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $requirement?->title) }}">
        </label>
    </x-form-group>
</x-modal>
