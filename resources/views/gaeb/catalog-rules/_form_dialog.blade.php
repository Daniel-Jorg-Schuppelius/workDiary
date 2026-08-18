{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Catalog\CatalogAssignmentRule $rule */
    $action = $rule->exists ? route('catalog-rules.update', $rule) : route('catalog-rules.store');
@endphp

<x-modal
    :title="$rule->exists ? __('Regel bearbeiten') : __('Regel anlegen')"
    :eyebrow="__('Zuordnungsregeln')"
    icon="auto_fix_high"
    tone="primary"
    :action="$action"
    :method="$rule->exists ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-select-field name="match_type" :label="__('Trifft auf')"
                    :hint="__('Der Leistungsbereich steht in der Datei; das Stichwort ist eine Vermutung über den Text.')">
        <option value="work_category" @selected(old('match_type', $rule->match_type) === 'work_category')>{{ __('Leistungsbereich') }}</option>
        <option value="keyword" @selected(old('match_type', $rule->match_type) === 'keyword')>{{ __('Stichwort') }}</option>
    </x-select-field>

    <x-input-field name="match_value" :label="__('Wert')" :value="old('match_value', $rule->match_value)" required
                   placeholder="{{ __('z. B. 002 oder Erdarbeiten') }}"
                   :hint="__('Leistungsbereiche werden auf Präfix verglichen: „013“ trifft auch „013.2“.')" />

    <x-select-field name="registry" :label="__('Katalog')">
        @foreach ($registries as $registry)
            <option value="{{ $registry->sqid }}" @selected($rule->catalog_registry_id === $registry->id)>
                {{ $registry->name }} {{ $registry->edition }}
            </option>
        @endforeach
    </x-select-field>

    <x-input-field name="code" :label="__('Kostengruppe')" :value="old('code', $rule->code)" required
                   placeholder="310" :hint="__('Muss im gewählten Katalog stehen.')" />

    <x-form-group :cols="2">
        <x-input-field type="number" min="1" max="9999" name="priority" :label="__('Rang')"
                       :value="old('priority', $rule->priority ?? 100)"
                       :hint="__('Kleinere Zahl greift zuerst.')" />
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="old('active', $rule->active ?? true)" />
    </x-form-group>

    @if ($rule->exists)
        <x-slot:footerExtra>
            <x-action-form :action="route('catalog-rules.destroy', $rule)" method="DELETE"
                           :confirm="__('Regel löschen? Bereits gesetzte Vorschläge bleiben erhalten.')"
                           :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
