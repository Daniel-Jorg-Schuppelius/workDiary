{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _deviation_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abweichung erfassen (MVP-795); auch am Backup-Schritt, wenn kein Nachweis möglich ist (MVP-1566). Variablen: $run, $sr. --}}
<form method="POST" action="{{ route('procedure-runs.steps.deviation', [$run, $sr]) }}" class="mt-2 space-y-2 border-t border-base-300 pt-2">
    @csrf
    <p class="text-xs text-muted">{{ __('procedure.run.deviationHint') }}</p>
    <div class="flex flex-wrap gap-2">
        <select name="deviation_type" required aria-label="{{ __('procedure.run.deviationType') }}" class="select select-bordered select-sm">
            @foreach (\App\Enums\Procedure\ProcedureDeviationType::cases() as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
        <select name="severity" aria-label="{{ __('procedure.run.deviationSeverity') }}" class="select select-bordered select-sm">
            <option value="">{{ __('procedure.run.deviationSeverityDefault') }}</option>
            @foreach (\App\Enums\Procedure\ProcedureDeviationSeverity::cases() as $severity)
                <option value="{{ $severity->value }}">{{ $severity->label() }}</option>
            @endforeach
        </select>
        <select name="proposed_action" aria-label="{{ __('procedure.run.deviationAction') }}" class="select select-bordered select-sm">
            @foreach (\App\Enums\Procedure\ProcedureDeviationProposedAction::cases() as $action)
                <option value="{{ $action->value }}">{{ $action->label() }}</option>
            @endforeach
        </select>
    </div>
    <textarea name="reason_text" rows="2" required minlength="20" maxlength="2000"
              aria-label="{{ __('procedure.run.deviationReason') }}"
              class="textarea textarea-bordered textarea-sm w-full"
              placeholder="{{ __('procedure.run.deviationReason') }}"></textarea>
    <x-button type="submit" tone="warning" icon="alert" class="btn-outline">
        {{ __('procedure.run.recordDeviation') }}
    </x-button>
</form>
