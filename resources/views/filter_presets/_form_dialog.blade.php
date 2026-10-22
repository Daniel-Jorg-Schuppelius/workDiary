{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gespeicherten Filter umbenennen, einordnen und als Standard seines Bereichs
  setzen. Bereich und Filterwerte bleiben, wie sie gespeichert wurden.
--}}
@php
    // Neue Vorlage aus der Filterleiste: die Filterwerte reisen als query[...] mit (verschachtelt höchstens eine Ebene).
    $hiddenQuery = [];
    if (! $preset->exists) {
        foreach ((array) $preset->query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_scalar($item)) {
                    $hiddenQuery[] = ['name' => is_array($value) ? 'query[' . $key . '][]' : 'query[' . $key . ']', 'value' => (string) $item];
                }
            }
        }
    }
@endphp
<x-modal
    :title="$preset->exists ? __('Filter bearbeiten') : __('Aktuellen Filter speichern')"
    :eyebrow="$preset->scope"
    icon="filter_alt"
    :action="$preset->exists ? route('filter-presets.update', $preset) : route('filter-presets.store')"
    :method="$preset->exists ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <input type="hidden" name="scope" value="{{ $preset->scope }}">
    @foreach ($hiddenQuery as $field)
        <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
    @endforeach

    <x-input-field name="name" id="preset-edit-name" :label="__('Name')" required maxlength="120"
                   :value="old('name', $preset->name)" />
    <x-input-field name="sort_order" id="preset-edit-sort-order" type="number" min="0" step="1"
                   :label="__('Reihenfolge')"
                   :value="old('sort_order', $preset->sort_order)" />
    <x-checkbox-field name="is_default" id="preset-edit-default"
                      :label="__('Standard für diesen Bereich')"
                      :checked="(bool) old('is_default', $preset->is_default)" />
</x-modal>
