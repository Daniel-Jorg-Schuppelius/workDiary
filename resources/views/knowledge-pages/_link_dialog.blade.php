{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wissensseite mit Kunde, Projekt, Asset, Auftrag, Ticket oder
  Prozedurvorlage verknüpfen (Feature 191, MVP-1667). Variablen: $page,
  $groups (je Art die sichtbaren Objekte).
--}}
<x-modal
    :title="__('knowledge_pages.action.link')"
    :eyebrow="$page->title"
    icon="add_link"
    tone="primary"
    :action="$groups !== [] ? route('knowledge.pages.links.store') : null"
    :form-data="['data-entry-form' => '']"
    :submit-label="$groups !== [] ? __('knowledge_pages.action.link') : null">
    <input type="hidden" name="page" value="{{ $page->sqid }}">
    @if ($groups === [])
        <x-empty-state compact icon="link_off" :title="__('knowledge_pages.links.nothing')" />
    @else
        <x-select-field name="target" :label="__('knowledge_pages.links.target')" required :hint="__('knowledge_pages.links.target_hint')">
            @foreach ($groups as $group)
                <optgroup label="{{ $group['label'] }}">
                    @foreach ($group['items'] as $item)
                        <option value="{{ $item['value'] }}">{{ $item['title'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
