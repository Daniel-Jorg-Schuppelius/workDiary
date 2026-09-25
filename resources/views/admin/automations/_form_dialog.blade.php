{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Automatisierungsregel anlegen. Auslöser und Aktion kommen aus
     den Modul-Katalogen (RuleTrigger/RuleAction), Bedingungen als JSON. --}}
@php
    $firstTrigger = $triggers[0] ?? null;
    $examples = collect($triggers)->map(fn ($t) => $t->label() . ': ' . json_encode($t->exampleConditions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->implode("\n");
@endphp
<x-modal
    :title="__('automation.form.title')"
    :eyebrow="__('Automatisierungen')"
    icon="smart_toy"
    tone="primary"
    :action="route('admin.automations.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel anlegen')">

    <x-input-field name="name" :label="__('Name')" :value="old('name')" required maxlength="255" />
    <x-select-field name="trigger_event" :label="__('automation.form.trigger')" required>
        @foreach ($triggers as $trigger)
            <option value="{{ $trigger->key() }}" @selected(old('trigger_event') === $trigger->key())>{{ $trigger->label() }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="action_type" :label="__('automation.form.action')" :hint="__('automation.form.action_hint')" required>
        @foreach ($actions as $action)
            <option value="{{ $action->type() }}" @selected(old('action_type') === $action->type())>{{ $action->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="priority" type="number" :label="__('Priorität')" :value="old('priority', 100)" min="1" max="9999" />
    <x-textarea-field name="conditions" :label="__('automation.form.conditions')" :hint="__('automation.form.conditions_hint') . ' ' . $examples"
        :value="old('conditions', $firstTrigger ? json_encode($firstTrigger->exampleConditions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '')" rows="4" required class="font-mono text-xs" />
</x-modal>
