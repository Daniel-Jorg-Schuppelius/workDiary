{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _version_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Teilnehmende auf die aktuelle Kursversion heben (Feature 149 §4,
  MVP-1272) — mit Vorschau, welche Einheiten gleich bleiben, sich ändern,
  hinzukommen oder entfallen. Variablen: $course, $current, $groups.
--}}
<x-modal
    :title="__('learning.action.move_to_version', ['version' => $current->version])"
    :eyebrow="$course->title"
    icon="upgrade"
    tone="warning"
    :action="route('learning.courses.enrollments.version.update', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.move_to_version', ['version' => $current->version])">

    <p class="text-sm">{{ trans_choice('learning.version.behind', $groups->sum('count'), ['count' => $groups->sum('count'), 'version' => $current->version]) }}</p>

    @foreach ($groups as $group)
        <x-card class="mt-3">
            <h3 class="text-sm font-semibold">
                {{ __('learning.version.from', ['version' => $group['version'] !== null ? 'v' . $group['version'] : '—', 'count' => $group['count']]) }}
            </h3>
            <ul class="mt-2 space-y-1 text-sm">
                <li>{{ __('learning.version.kept', ['count' => count($group['diff']['kept'])]) }}</li>
                @foreach (['changed', 'added', 'removed'] as $kind)
                    @if ($group['diff'][$kind] !== [])
                        <li>{{ __('learning.version.' . $kind, ['count' => count($group['diff'][$kind]), 'units' => implode(', ', $group['diff'][$kind])]) }}</li>
                    @endif
                @endforeach
            </ul>
        </x-card>
    @endforeach

    <p class="mt-3 text-xs text-muted">{{ __('learning.help.move_to_version') }}</p>

    <x-form-group :legend="__('learning.field.reason')" icon="edit_note" tone="warning" cols="1">
        <x-textarea-field name="reason" :label="__('learning.field.reason')" required minlength="2" maxlength="255" rows="2"
                          :value="old('reason')" />
    </x-form-group>
</x-modal>
