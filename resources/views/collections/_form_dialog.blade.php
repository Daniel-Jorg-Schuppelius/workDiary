{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sammlung anlegen/bearbeiten (MVP-809). Variablen: $collection (null beim
  Anlegen), $parentOptions (Baumzeilen), $parentSqid.
--}}
@php $isEdit = $collection !== null; @endphp

<x-modal
    :title="$isEdit ? __('collections.action.edit') : __('collections.action.create')"
    :eyebrow="__('collections.title.index')"
    icon="folder_special"
    tone="primary"
    :action="$isEdit ? route('collections.update', $collection) : route('collections.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('collections.action.save') : __('collections.action.create')">

    <x-form-group :legend="__('collections.title.index')" icon="folder_special" tone="primary" cols="2">
        <x-input-field name="title" :label="__('collections.field.title')" required minlength="2" maxlength="180" span="2"
                       :value="old('title', $collection?->title)" />
        <x-textarea-field name="description" :label="__('collections.field.description')" rows="3" span="2" maxlength="2000"
                          :value="old('description', $collection?->description)" />
        <x-select-field name="parent_id" :label="__('collections.field.parent')" :hint="__('collections.help.parent', ['max' => \App\Models\ContentCollection::MAX_DEPTH])">
            <option value="">{{ __('collections.field.no_parent') }}</option>
            @foreach ($parentOptions as $row)
                @continue($isEdit && (int) $row['collection']->id === (int) $collection->id)
                <option value="{{ $row['collection']->sqid }}" @selected((string) old('parent_id', $parentSqid) === (string) $row['collection']->sqid)>{{ str_repeat('– ', $row['depth'] - 1) }}{{ $row['collection']->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="visibility" :label="__('collections.field.visibility')" :hint="__('collections.help.visibility')" required>
            @foreach ([\App\Models\ContentCollection::VISIBILITY_ORGANIZATION, \App\Models\ContentCollection::VISIBILITY_PRIVATE] as $visibility)
                <option value="{{ $visibility }}" @selected(old('visibility', $collection?->visibility ?? \App\Models\ContentCollection::VISIBILITY_ORGANIZATION) === $visibility)>{{ __('collections.visibility.' . $visibility) }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
