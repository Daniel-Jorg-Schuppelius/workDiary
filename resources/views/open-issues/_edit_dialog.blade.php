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
    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="severity" :label="__('open-issue.field.severity')">
            @foreach (\App\Enums\OpenIssue\OpenIssueSeverity::cases() as $sev)
                <option value="{{ $sev->value }}" @selected(old('severity', $issue->severity->value) === $sev->value)>{{ $sev->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="category" :label="__('open-issue.field.category')" maxlength="40" :value="old('category', $issue->category)" />
        <x-input-field name="due_at" type="datetime-local" :label="__('open-issue.field.due_at')"
                       :value="old('due_at', \App\Support\Tz::toLocal($issue->due_at)?->format('Y-m-d\TH:i'))" />
        @if ($canPublishToCustomer)
            <x-select-field name="visibility" :label="__('open-issue.field.visibility')">
                @foreach (\App\Enums\OpenIssue\OpenIssueVisibility::cases() as $vis)
                    <option value="{{ $vis->value }}" @selected(old('visibility', $issue->visibility->value) === $vis->value)>{{ $vis->label() }}</option>
                @endforeach
            </x-select-field>
        @endif
    </div>
    <p class="text-xs text-muted">{{ __('open-issue.hint.edit_tracked') }}</p>
</x-modal>
