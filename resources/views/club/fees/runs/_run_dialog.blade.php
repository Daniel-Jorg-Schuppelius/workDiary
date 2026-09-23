{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _run_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragslauf anlegen (in #entry-modal geladen). Variablen: $month --}}
<x-modal
    :title="__('club.fees.action.create_run')"
    :eyebrow="__('club.fees.title.runs')"
    icon="play_circle"
    tone="primary"
    :action="route('club.fees.runs.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.fees.action.prepare')">

    <x-form-group :legend="__('club.fees.field.month')" icon="calendar_month" tone="primary" cols="1" :description="__('club.fees.hint.run')">
        <x-input-field name="month" type="month" :label="__('club.fees.field.month')" required :value="old('month', $month->format('Y-m'))" />
    </x-form-group>
</x-modal>
