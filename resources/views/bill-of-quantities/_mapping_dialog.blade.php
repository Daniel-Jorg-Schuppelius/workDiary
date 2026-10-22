{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _mapping_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LV-Position mit Artikel oder Material verknüpfen (MVP-083). Der Optionswert
  trägt den Typ („article:…“/„material:…“), damit Typ und Eintrag nicht
  auseinanderlaufen können.
--}}
<x-modal
    :title="__('gaeb.mapping.title')"
    :eyebrow="trim($item->reference_no . ' ' . ($item->short_text ?? ''))"
    icon="link"
    tone="primary"
    size="md"
    :action="route('bill-of-quantities.items.mappings.add', $item)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('gaeb.mapping.add')">
    <x-select-field name="target" id="boq-mapping-target" :label="__('gaeb.mapping.title')" required>
        <optgroup label="{{ __('gaeb.mapping.article') }}">
            @foreach ($articles as $article)
                <option value="article:{{ $article->sqid }}">{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
            @endforeach
        </optgroup>
        <optgroup label="{{ __('gaeb.mapping.material') }}">
            @foreach ($materials as $material)
                <option value="material:{{ $material->sqid }}">{{ $material->name }}{{ $material->unit ? ' (' . $material->unit . ')' : '' }}</option>
            @endforeach
        </optgroup>
    </x-select-field>
    <x-input-field name="factor" type="number" step="0.0001" min="0" :label="__('gaeb.mapping.factor')" value="1" />
</x-modal>
