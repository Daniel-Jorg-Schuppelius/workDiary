{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

<x-modal
    :title="__('open-issue.action.create')"
    :eyebrow="__('open-issue.title.index')"
    icon="flag"
    tone="primary"
    size="md"
    :action="route('open-issues.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('open-issue.action.create')">

    <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
    <input type="hidden" name="subject_id" value="{{ $subjectId }}">

    <x-input-field name="title" :label="__('open-issue.field.title')" required maxlength="200" :value="old('title')" />

    <x-textarea-field name="description" :label="__('open-issue.field.description')" rows="3" :value="old('description')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="severity" :label="__('open-issue.field.severity')">
            @foreach (\App\Enums\OpenIssue\OpenIssueSeverity::cases() as $sev)
                <option value="{{ $sev->value }}" @selected(old('severity', 'medium') === $sev->value)>{{ $sev->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="category" :label="__('open-issue.field.category')" maxlength="100" :value="old('category')" />
        <x-input-field name="due_at" type="datetime-local" :label="__('open-issue.field.due_at')" :value="old('due_at')" />
        @if ($canPublishToCustomer)
            <x-select-field name="visibility" :label="__('open-issue.field.visibility')">
                @foreach (\App\Enums\OpenIssue\OpenIssueVisibility::cases() as $vis)
                    <option value="{{ $vis->value }}" @selected(old('visibility', 'internal') === $vis->value)>{{ $vis->label() }}</option>
                @endforeach
            </x-select-field>
        @endif
    </div>
</x-modal>
