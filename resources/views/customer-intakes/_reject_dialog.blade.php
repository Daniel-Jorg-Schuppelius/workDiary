{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reject_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Ablehnung mit kundenverständlicher Begründung (MVP-1075). --}}
<x-modal
    :title="__('customer_intake.action.reject')"
    :eyebrow="$intake->number"
    icon="block"
    tone="error"
    :action="route('customer-intakes.reject', $intake)"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-error"
    :submit-label="__('customer_intake.action.reject')"
>
    <x-form-group :legend="__('customer_intake.field.rejection_reason')" icon="chat" tone="error" cols="1">
        <x-textarea-field name="reason" :label="__('customer_intake.field.rejection_reason')" rows="4" maxlength="2000" required :value="old('reason')"
                          :hint="__('customer_intake.reject.hint')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
