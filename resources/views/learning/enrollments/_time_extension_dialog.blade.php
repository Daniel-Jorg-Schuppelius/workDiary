{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_extension_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachteilsausgleich (Feature 149, MVP-1267): Zeitverlängerung in Prozent
  für alle zeitbegrenzten Prüfungen dieser Einschreibung, mit Begründung.
  Variablen: $course, $enrollment.
--}}
<x-modal
    :title="__('learning.action.time_extension')"
    :eyebrow="$enrollment->learnerName()"
    icon="more_time"
    tone="primary"
    :action="route('learning.courses.enrollments.time-extension.update', [$course, $enrollment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.save')">

    <x-form-group :legend="__('learning.field.time_extension_percent')" icon="more_time" tone="primary" cols="1">
        <x-input-field name="time_extension_percent" type="number" min="0" max="200" step="5"
                       :label="__('learning.field.time_extension_percent')"
                       :hint="__('learning.help.time_extension')"
                       :value="old('time_extension_percent', $enrollment->time_extension_percent)" />
        <x-textarea-field name="reason" :label="__('learning.field.reason')" required minlength="2" maxlength="255" rows="2"
                          :value="old('reason')" />
    </x-form-group>
</x-modal>
