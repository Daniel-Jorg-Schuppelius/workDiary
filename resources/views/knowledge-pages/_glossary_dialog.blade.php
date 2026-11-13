{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _glossary_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Glossarbegriff anlegen bzw. bearbeiten (Feature 191, MVP-1667).
  Variablen: $term|null, $prefill (Begriff aus einer Suchlücke), $pages.
--}}
@php $isEdit = $term !== null; @endphp
<x-modal
    :title="$isEdit ? __('knowledge_pages.glossary.edit') : __('knowledge_pages.glossary.create')"
    :eyebrow="__('knowledge_pages.glossary.title')"
    icon="spellcheck"
    tone="primary"
    :action="$isEdit ? route('knowledge.glossary.update', $term) : route('knowledge.glossary.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('knowledge_pages.action.save')">
    <x-input-field name="term" :label="__('knowledge_pages.glossary.term')" required minlength="2" maxlength="120" :value="old('term', $term?->term ?? $prefill)" />
    <x-textarea-field name="synonyms" :label="__('knowledge_pages.glossary.synonyms')" rows="3" maxlength="2000" :hint="__('knowledge_pages.glossary.synonyms_hint')"
                      :value="old('synonyms', implode(PHP_EOL, $term?->synonyms ?? []))" />
    <x-textarea-field name="description" :label="__('knowledge_pages.glossary.description')" rows="3" maxlength="5000" :value="old('description', $term?->description)" />
    <x-select-field name="page" :label="__('knowledge_pages.glossary.page')" :hint="__('knowledge_pages.glossary.page_hint')">
        <option value="">—</option>
        @foreach ($pages as $optionPage)
            <option value="{{ $optionPage->sqid }}" @selected($term !== null && (int) $term->knowledge_page_id === (int) $optionPage->id)>{{ $optionPage->title }}</option>
        @endforeach
    </x-select-field>
</x-modal>
