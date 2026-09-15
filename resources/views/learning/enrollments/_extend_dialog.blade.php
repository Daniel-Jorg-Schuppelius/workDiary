{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _extend_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Frist und Zugang einer Einschreibung ändern (Feature 149, MVP-778). Die
  Begründung ist Pflicht — sie wird als Ereignis an der Einschreibung
  festgehalten. Variablen: $course, $enrollment.
--}}
<x-modal
    :title="__('learning.action.extend_access')"
    :eyebrow="$enrollment->learnerName()"
    icon="event_repeat"
    tone="primary"
    :action="route('learning.courses.enrollments.update', [$course, $enrollment])"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.save')">

    <x-form-group :legend="__('learning.field.deadlines')" icon="event" tone="primary" cols="2">
        <x-input-field name="due_at" type="date" :label="__('learning.field.due_at')"
                       :value="old('due_at', $enrollment->due_at?->toDateString())" />
        <x-input-field name="access_until" type="date" :label="__('learning.field.access_until')"
                       :value="old('access_until', $enrollment->access_until?->toDateString())" />
        <x-textarea-field name="reason" :label="__('learning.field.reason')" required minlength="2" maxlength="255" rows="2" span="2"
                          :hint="__('learning.help.extend_access')" :value="old('reason')" />
    </x-form-group>
</x-modal>
