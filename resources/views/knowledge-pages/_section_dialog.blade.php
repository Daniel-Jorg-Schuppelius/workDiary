{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _section_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rubrik eines Bereichs anlegen bzw. umbenennen (Feature 191, MVP-1664).
  Variablen: $space, $section|null.
--}}
@php $isEdit = $section !== null; @endphp
<x-modal
    :title="$isEdit ? __('knowledge_pages.action.edit_section') : __('knowledge_pages.action.create_section')"
    :eyebrow="$space->title"
    icon="bookmark"
    tone="primary"
    :action="$isEdit ? route('knowledge.sections.update', $section) : route('knowledge.sections.store', $space)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('knowledge_pages.action.save') : __('knowledge_pages.action.create_section')">
    <x-input-field name="title" :label="__('knowledge_pages.field.title')" required minlength="2" maxlength="180" :value="old('title', $section?->title)" />
</x-modal>
