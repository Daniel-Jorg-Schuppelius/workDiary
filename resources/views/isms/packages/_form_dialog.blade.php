{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Auditpaket anlegen"-Dialog (in #entry-modal geladen): Geltungsbereich,
  Berichtsstichtag (einzelnes Datum — KEIN Zeitraum), optionaler
  Norm-Filter. Eingefroren wird der Datenstand erst bei der Finalisierung
  (ehrliche Stichtags-Semantik, Hinweis im Dialog).
  Variablen: $scopes
--}}

<x-modal
    :title="__('isms.action.create_package')"
    icon="inventory_2"
    tone="primary"
    size="lg"
    :action="route('isms.packages.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.create_package')">

    <x-form-group :legend="__('isms.group.package')" icon="inventory_2" tone="primary" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required maxlength="180"
                   class="input input-bordered w-full"
                   placeholder="{{ __('isms.hint.package_title') }}"
                   value="{{ old('title') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.scope') }} *</span>
            <select name="scope" required class="select select-bordered w-full">
                @foreach ($scopes as $scope)
                    <option value="{{ $scope->sqid }}" @selected(old('scope') === $scope->sqid)>{{ $scope->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.as_of_date') }} *</span>
            <input type="date" name="as_of_date" required
                   class="input input-bordered w-full"
                   value="{{ old('as_of_date', now()->toDateString()) }}">
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.package_as_of_date') }}</span>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.package_filter')" icon="filter_alt" tone="info" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.norm') }}</span>
            <input type="text" name="norm" maxlength="64"
                   class="input input-bordered w-full"
                   placeholder="{{ __('isms.hint.norm') }}"
                   value="{{ old('norm') }}">
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.package_norm') }}</span>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.edition') }}</span>
            <input type="text" name="edition" maxlength="16"
                   class="input input-bordered w-full"
                   placeholder="{{ __('isms.hint.edition') }}"
                   value="{{ old('edition') }}">
        </label>
    </x-form-group>
</x-modal>
