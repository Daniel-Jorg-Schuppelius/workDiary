{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reopen_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bestätigte Liste wieder öffnen (in #entry-modal geladen). Variablen: $event, $sheet --}}
<x-modal
    :title="__('club.attendance.action.reopen')"
    :eyebrow="$event->title"
    icon="lock_open"
    tone="warning"
    :action="route('club.events.attendance.reopen', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.attendance.action.reopen')">

    <input type="hidden" name="version" value="{{ $sheet->version }}">
    <x-form-group :legend="__('club.attendance.field.reason')" icon="lock_open" tone="warning" cols="1" :description="__('club.attendance.hint.reopen')">
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>
