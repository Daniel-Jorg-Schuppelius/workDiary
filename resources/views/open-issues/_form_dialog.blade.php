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

    <label class="form-control w-full">
        <span class="label-text">{{ __('open-issue.field.title') }}</span>
        <input type="text" name="title" required maxlength="200"
               class="input input-bordered w-full" value="{{ old('title') }}">
    </label>

    <label class="form-control w-full">
        <span class="label-text">{{ __('open-issue.field.description') }}</span>
        <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="form-control">
            <span class="label-text">{{ __('open-issue.field.severity') }}</span>
            <select name="severity" class="select select-bordered w-full">
                @foreach (\App\Enums\OpenIssue\OpenIssueSeverity::cases() as $sev)
                    <option value="{{ $sev->value }}" @selected(old('severity', 'medium') === $sev->value)>{{ $sev->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('open-issue.field.category') }}</span>
            <input type="text" name="category" maxlength="100" class="input input-bordered w-full" value="{{ old('category') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('open-issue.field.due_at') }}</span>
            <input type="datetime-local" name="due_at" class="input input-bordered w-full" value="{{ old('due_at') }}">
        </label>
        @if ($canPublishToCustomer)
            <label class="form-control">
                <span class="label-text">{{ __('open-issue.field.visibility') }}</span>
                <select name="visibility" class="select select-bordered w-full">
                    @foreach (\App\Enums\OpenIssue\OpenIssueVisibility::cases() as $vis)
                        <option value="{{ $vis->value }}" @selected(old('visibility', 'internal') === $vis->value)>{{ $vis->label() }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>
</x-modal>
