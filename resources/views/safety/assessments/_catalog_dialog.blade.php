{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _catalog_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gefährdungen aus dem Katalog übernehmen (MVP-1002). Variablen: $assessment, $catalog (nach Kategorie gruppiert)
--}}
<x-modal :title="__('safety.catalog.action.import')" :eyebrow="$assessment->displayNo()" icon="library_add" tone="primary"
         :action="route('safety.assessments.catalog.store', $assessment)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('safety.catalog.action.import')">
    <p class="text-sm text-muted">{{ __('safety.catalog.hint.import') }}</p>
    @foreach ($catalog as $category => $items)
        <x-form-group :legend="$category" icon="category" tone="ghost" cols="1">
            @foreach ($items as $item)
                <x-checkbox-field name="catalog_items[]" :value="$item->sqid" :id="'cat-' . $item->sqid"
                                  :label="$item->hazard . ' (' . __('safety.catalog.field.risk_short', ['severity' => $item->severity, 'likelihood' => $item->likelihood]) . ')'"
                                  :hint="$item->measure" :checked="in_array($item->sqid, (array) old('catalog_items', []), true)" />
            @endforeach
        </x-form-group>
    @endforeach
</x-modal>
