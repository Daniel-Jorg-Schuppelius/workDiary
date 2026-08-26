{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _payroll_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Personal-/Lohndaten. Erwartet $member (nullable) und $canManagePayroll.
     Wird im Admin-Formular und im HR-/GF-Zweig (Personalverwaltung) genutzt. --}}
@php
    $canManagePayroll = $canManagePayroll ?? false;
    $minWage = app(\App\Services\Payroll\MinimumWageService::class)->currentFor();
    $wageVal = old('payroll_hourly_wage', $member?->payroll_hourly_wage);
    $belowMin = $minWage !== null && $wageVal !== null && $wageVal !== '' && (float) $wageVal < $minWage;
    $employmentHint = $member ? app(\App\Services\Payroll\PayrollClassifier::class)->mismatchHint($member) : null;
@endphp
<x-form-group :legend="__('Vergütung & Lohn')" icon="payments" tone="warning" cols="2"
              x-data="reveal(@js(old('compensation_model', $member?->compensation_model?->value ?? '')))">
    {{-- Vergütungsmodell steuert, welche Felder gelten: intern (dt. Lohn),
         pauschal (Festbetrag) oder extern nach Zeitaufwand (Stundensatz). --}}
    <x-select-field name="compensation_model" :label="__('Vergütungsmodell')" span="2"
                    x-model="value" :disabled="! $canManagePayroll"
                    :hint="__('Extern (pauschal / nach Zeitaufwand) blendet die deutschen Lohnfelder aus.')">
        <option value="">{{ __('Intern (Lohnabrechnung)') }}</option>
        @foreach (\App\Enums\User\CompensationModel::options() as $value => $label)
            <option value="{{ $value }}" @selected(old('compensation_model', $member?->compensation_model?->value) === $value)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    {{-- Pauschale (x-show muss auf einem Wrapper AUSSERHALB der Komponente
         sitzen — sonst verschwände nur das Input, nicht das Label). --}}
    <div x-show="is('pauschal')" x-cloak>
        <x-input-field name="flat_amount" type="number" :label="__('Pauschalbetrag (€)')" step="0.01" min="0"
                       :value="old('flat_amount', $member?->flat_amount)" :disabled="! $canManagePayroll" />
    </div>
    <div x-show="is('pauschal')" x-cloak>
        <x-select-field name="flat_interval" :label="__('Intervall')" :disabled="! $canManagePayroll">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach (\App\Enums\User\FlatInterval::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('flat_interval', $member?->flat_interval?->value) === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </div>

    {{-- Nach Zeitaufwand --}}
    <div x-show="is('nach_zeitaufwand')" x-cloak>
        <x-input-field name="compensation_rate" type="number" :label="__('Stundensatz (Vergütung, €)')" step="0.01" min="0"
                       :value="old('compensation_rate', $member?->compensation_rate)"
                       x-bind:required="is('nach_zeitaufwand')" :disabled="! $canManagePayroll"
                       :hint="__('Wird auf die erfasste Zeit angewandt (nicht der Kundensatz).')" />
    </div>

    <div class="fieldset" x-show="isAny('payroll', '')" x-cloak>
        <label class="fieldset-label" for="payroll_hourly_wage">{{ __('Stundenlohn') }}</label>
        <input type="number" name="payroll_hourly_wage" id="payroll_hourly_wage" step="0.01" min="0"
               class="input input-bordered w-full @error('payroll_hourly_wage') @enderror @if($belowMin) input-warning @endif"
               value="{{ $wageVal }}"
               @disabled(! $canManagePayroll)>
        @error('payroll_hourly_wage')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @if ($minWage !== null)
            <p class="mt-1 text-xs {{ $belowMin ? 'text-warning font-medium' : 'text-muted' }}">
                @if ($belowMin)
                    {{ __('Unter dem aktuellen Mindestlohn von :min €.', ['min' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minWage, 2, withThousandsSeparator: true)]) }}
                @else
                    {{ __('Aktueller Mindestlohn: :min €.', ['min' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minWage, 2, withThousandsSeparator: true)]) }}
                @endif
            </p>
        @endif
    </div>

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-select-field name="employment_type" :label="__('Beschäftigungsart')">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach (\App\Enums\User\EmploymentType::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('employment_type', $member?->employment_type?->value) === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </div>

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-input-field name="tax_identification_number" :label="__('Steuer-ID / Steuernummer')" maxlength="32"
                       :value="old('tax_identification_number', $member?->tax_identification_number)" />
    </div>

    <x-input-field name="date_of_birth" type="date" :label="__('Geburtstag')"
                   :value="old('date_of_birth', $member?->date_of_birth?->format('Y-m-d'))" />

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-input-field name="social_security_number" :label="__('Sozialversicherungsnummer')" maxlength="64"
                       :value="old('social_security_number', $member?->social_security_number)" />
    </div>

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-input-field name="health_insurance" :label="__('Krankenkasse')" maxlength="128"
                       :value="old('health_insurance', $member?->health_insurance)" />
    </div>

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-input-field name="tax_class" :label="__('Steuerklasse')" maxlength="16"
                       :value="old('tax_class', $member?->tax_class)" />
    </div>

    <div x-show="isAny('payroll', '')" x-cloak>
        <x-input-field name="child_allowances" type="number" :label="__('Kinderfreibeträge')" step="0.01" min="0"
                       :value="old('child_allowances', $member?->child_allowances)" />
    </div>

    {{-- Wochenstunden: read-only aus dem Arbeitszeit-Modell (Single Source of Truth). --}}
    <div class="fieldset">
        <label class="fieldset-label" for="weekly-hours-display">{{ __('Wochenstunden') }}</label>
        @php $ws = $member?->workSchedule(); @endphp
        <input type="text" id="weekly-hours-display" class="input input-bordered w-full" disabled
               value="{{ $ws ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($ws->weekly_minutes / 60, 2, withThousandsSeparator: true) . ' h' : __('— kein Arbeitszeit-Modell —') }}">
        @can('create', \App\Models\WorkSchedule::class)
            @if ($member)
                <a href="{{ route('users.work-schedule.edit', $member) }}" data-entry-modal-trigger
                   class="link link-primary mt-1 inline-flex items-center gap-1 text-xs">
                    <x-icon name="schedule" class="text-[1rem]" />
                    {{ __('Arbeitszeit-Modell bearbeiten') }}
                </a>
            @endif
        @endcan
        <p class="mt-1 text-xs text-muted">{{ __('Stammt aus dem Arbeitszeit-Modell.') }}</p>
    </div>

    {{-- Ein-/Austritt als gekoppeltes Von-Bis (I6): Austritt nie vor Eintritt;
         Feldnamen bleiben unverändert (Request-Seite unberührt). --}}
    <x-date-range layout="split" form-control size="" class="md:col-span-2"
                  from-name="employment_start_date" to-name="employment_end_date"
                  from-id="employment_start_date" to-id="employment_end_date"
                  :from-label="__('Eintrittsdatum')" :to-label="__('Austrittsdatum')"
                  :from="old('employment_start_date', $member?->employment_start_date?->format('Y-m-d'))"
                  :to="old('employment_end_date', $member?->employment_end_date?->format('Y-m-d'))"
                  :from-error="$errors->first('employment_start_date') ?: null"
                  :to-error="$errors->first('employment_end_date') ?: null" />

    <label class="label cursor-pointer justify-start gap-3 md:col-span-2" x-show="isAny('payroll', '')" x-cloak>
        <input type="checkbox" name="church_tax" value="1" class="checkbox checkbox-sm"
               @checked(old('church_tax', $member?->church_tax))>
        <span class="label-text">{{ __('Kirchensteuerpflichtig') }}</span>
    </label>

    @if ($employmentHint)
        <div class="alert alert-warning md:col-span-2 py-2 text-sm" x-show="isAny('payroll', '')" x-cloak>
            <x-icon name="info" class="text-[1.1rem]" />
            <span>{{ $employmentHint }}</span>
        </div>
    @endif
</x-form-group>
