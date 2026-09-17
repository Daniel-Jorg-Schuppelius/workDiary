{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reference_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verweis setzen (MVP-811). Variablen: $type, $itemSqid, $itemTitle,
  $groups (sichtbare Ziele je Typ, siehe ContentReferenceService::pickerOptions).
--}}
<x-modal
    :title="__('collections.references.action.create')"
    :eyebrow="$itemTitle"
    icon="add_link"
    tone="primary"
    :action="route('references.store')"
    :submit-label="__('collections.references.action.create')">

    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="item" value="{{ $itemSqid }}">

    @if ($groups === [])
        <x-empty-state icon="link_off" :title="__('collections.references.title')" :message="__('collections.references.empty_picker')" compact />
    @else
        <x-form-group :legend="__('collections.references.field.target')" icon="link" tone="primary">
            <x-select-field id="reference-target" name="target" :label="__('collections.references.field.target')" :hint="__('collections.references.help')" required>
                <x-slot:beforeSelect>
                    <input type="search" data-select-search="reference-target" autocomplete="off"
                           class="input input-sm input-bordered mb-1 w-full"
                           placeholder="{{ __('collections.references.field.search') }}" aria-label="{{ __('collections.references.field.search') }}">
                </x-slot:beforeSelect>
                <option value="">—</option>
                @foreach ($groups as $group)
                    <optgroup label="{{ $group['label'] }}">
                        @foreach ($group['items'] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['title'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-select-field>
        </x-form-group>
    @endif
</x-modal>
