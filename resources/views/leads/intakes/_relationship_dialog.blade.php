{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _relationship_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ende der Geschäftsbeziehung am Kunden (Feature 200, MVP-1643) — Anker der Aufbewahrung nach § 8 Abs. 4 GwG. Variable: $customer. --}}
<x-modal
    :title="__('lead_intake.relationship.title')"
    :eyebrow="$customer->name"
    icon="event_busy"
    tone="primary"
    :action="route('leads.intakes.relationship.update', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('lead_intake.relationship.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('leads.intakes.relationship.edit', $customer) . '?dialog=1' }}">

    <x-form-group :legend="__('lead_intake.relationship.legend')" icon="event_busy" tone="primary">
        <x-input-field type="date" id="lir-ended-on" name="relationship_ended_on" :label="__('lead_intake.field.relationship_ended_on')"
                       :value="old('relationship_ended_on', $customer->relationship_ended_on?->format('Y-m-d'))" :hint="__('lead_intake.relationship.hint')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
