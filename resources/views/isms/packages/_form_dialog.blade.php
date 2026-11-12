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
  Variablen: $scopes, optional $package (Entwurf bearbeiten)
--}}
@php
    $package = $package ?? null;
    $scopeSqid = (string) old('scope', $package?->scope?->sqid ?? '');
@endphp

<x-modal
    :title="$package ? __('isms.action.edit_package') : __('isms.action.create_package')"
    icon="inventory_2"
    tone="primary"
    size="lg"
    :action="$package ? route('isms.packages.update', $package) : route('isms.packages.store')"
    :method="$package ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$package ? __('isms.action.save') : __('isms.action.create_package')">

    <x-form-group :legend="__('isms.group.package')" icon="inventory_2" tone="primary" cols="2">
        <x-input-field name="title" :label="__('isms.field.title')" required maxlength="180"
                       span="2"
                       placeholder="{{ __('isms.hint.package_title') }}"
                       :value="old('title', $package?->title)" />
        <x-select-field name="scope" :label="__('isms.field.scope')" required>
            @foreach ($scopes as $scope)
                <option value="{{ $scope->sqid }}" @selected($scopeSqid === $scope->sqid)>{{ $scope->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="as_of_date" type="date" :label="__('isms.field.as_of_date')" required
                       :value="old('as_of_date', $package?->as_of_date?->toDateString() ?? now()->toDateString())"
                       :hint="__('isms.hint.package_as_of_date')" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.package_filter')" icon="filter_alt" tone="info" cols="2">
        <x-input-field name="norm" :label="__('isms.field.norm')" maxlength="64"
                       placeholder="{{ __('isms.hint.norm') }}"
                       :value="old('norm', $package?->norm)"
                       :hint="__('isms.hint.package_norm')" />
        <x-input-field name="edition" :label="__('isms.field.edition')" maxlength="16"
                       placeholder="{{ __('isms.hint.edition') }}"
                       :value="old('edition', $package?->edition)" />
    </x-form-group>

    @if ($package)
        <x-slot:footerExtra>
            <x-action-form :action="route('isms.packages.destroy', $package)" method="DELETE"
                  :confirm="__('isms.confirm_delete_package')"
                  :confirm-label="__('isms.action.delete_package')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('isms.action.delete_package') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
