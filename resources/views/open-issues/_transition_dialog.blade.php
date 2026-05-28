{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transition_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\OpenIssue $issue */
    $field = $requiresResolution ? 'resolution' : 'reason';
    $tone = $action === 'complete' ? 'success' : ($action === 'block' ? 'error' : 'warning');
    $icon = [
        'block' => 'block',
        'complete' => 'check_circle',
        'wontDo' => 'do_not_disturb_on',
        'reopen' => 'restart_alt',
    ][$action] ?? 'flag';
@endphp

<x-modal
    :title="__('open-issue.action.' . $action)"
    :eyebrow="$issue->title"
    :icon="$icon"
    :tone="$tone"
    size="md"
    :action="route('open-issues.transition', ['issue' => $issue, 'action' => $action])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('open-issue.action.' . $action)"
    :submit-class="'btn-' . $tone">

    <label class="form-control w-full">
        <span class="label-text">{{ $requiresResolution ? __('open-issue.field.resolution') : __('open-issue.field.reason') }}</span>
        <textarea name="{{ $field }}" rows="4" required minlength="3"
                  class="textarea textarea-bordered w-full" autofocus></textarea>
    </label>
</x-modal>
