{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _edit_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal
    :title="__('open-issue.action.edit')"
    :eyebrow="$issue->title"
    icon="flag"
    tone="primary"
    size="md"
    :action="route('open-issues.update', $issue)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-input-field name="title" :label="__('open-issue.field.title')" required maxlength="180" :value="old('title', $issue->title)" />
    <x-textarea-field name="description" :label="__('open-issue.field.description')" rows="3" :value="old('description', $issue->description)" />
    <x-input-field name="category" :label="__('open-issue.field.category')" maxlength="40" :value="old('category', $issue->category)" />
</x-modal>
