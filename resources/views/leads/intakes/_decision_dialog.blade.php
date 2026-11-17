{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _decision_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Entscheidung über eine Aufnahme (Feature 200, MVP-1641). Variablen: $lead, $intake, $errors_before (Feldschlüssel → Meldung). --}}
<x-modal
    :title="__('lead_intake.decision.title')"
    :eyebrow="$lead->displayName()"
    icon="gavel"
    tone="primary"
    :action="route('leads.intakes.decide', [$lead, $intake])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('lead_intake.action.decide')">

    <input type="hidden" name="_dialog_url" value="{{ route('leads.intakes.decision', [$lead, $intake]) . '?dialog=1' }}">

    @if ($errors_before !== [])
        <div role="note" class="alert alert-warning text-sm">
            <x-icon name="warning" />
            <div>
                <p class="font-medium">{{ __('lead_intake.decision.missing', ['count' => count($errors_before)]) }}</p>
                <ul class="list-disc ps-5 text-xs">
                    @foreach ($errors_before as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <x-form-group :legend="__('lead_intake.decision.legend')" icon="gavel" tone="primary">
        <div class="flex flex-wrap gap-4" role="radiogroup" aria-label="{{ __('lead_intake.field.decision') }}">
            @foreach (\App\Enums\Sales\LeadIntakeDecision::cases() as $option)
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" class="radio radio-sm" name="decision" value="{{ $option->value }}" @checked(old('decision') === $option->value) required>
                    <span>{{ $option->label() }}</span>
                </label>
            @endforeach
        </div>
        <x-textarea-field name="reason" id="intake-decision-reason" :label="__('lead_intake.field.decision_reason')" :value="old('reason')" rows="4" required :hint="__('lead_intake.decision.reason_hint')" />
        @if ($intake->requiresDecision())
            <p class="text-xs text-muted">{{ __('lead_intake.decision.blocks_conversion') }}</p>
        @endif
    </x-form-group>

    <x-validation-errors />
</x-modal>
