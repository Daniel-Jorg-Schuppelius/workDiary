{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Automatisierungsregel anlegen (MVP-Scope: rohes JSON,
     visueller Form-Builder ist Phase 2 — siehe AutomationRuleController). --}}
<x-modal
    :title="__('Neue Regel anlegen (JSON)')"
    :eyebrow="__('Automatisierungen')"
    icon="smart_toy"
    tone="primary"
    :action="route('admin.automations.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel anlegen')">

    <x-form-group :label="__('Name')" name="name">
        <input type="text" name="name" class="input input-bordered w-full" required maxlength="255" value="{{ old('name') }}">
    </x-form-group>
    <x-form-group :label="__('Trigger-Event')" name="trigger_event">
        <input type="text" name="trigger_event" class="input input-bordered w-full" value="{{ old('trigger_event', 'expense.submitted') }}" required>
    </x-form-group>
    <x-form-group :label="__('Priorität')" name="priority">
        <input type="number" name="priority" class="input input-bordered w-32" value="{{ old('priority', 100) }}" min="1" max="9999">
    </x-form-group>
    {{-- JSON-Defaults literal statt {{ old(...) }}: die Payloads enthalten "}}",
         das Blade als Echo-Ende parst (BladeCompilationTest). --}}
    <x-form-group :label="__('Bedingungen (JSON)')" name="conditions">
        <textarea name="conditions" rows="4" class="textarea textarea-bordered w-full font-mono text-xs" required>{"all":[{"field":"amount_gross","op":"<=","value":50}]}</textarea>
    </x-form-group>
    <x-form-group :label="__('Aktionen (JSON)')" name="actions">
        <textarea name="actions" rows="3" class="textarea textarea-bordered w-full font-mono text-xs" required>[{"type":"expense.approve","params":{}}]</textarea>
    </x-form-group>
</x-modal>
