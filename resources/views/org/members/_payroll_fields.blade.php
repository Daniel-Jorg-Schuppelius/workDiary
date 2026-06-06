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
              x-data="{ comp: @js(old('compensation_model', $member?->compensation_model?->value ?? '')) }">
    {{-- Vergütungsmodell steuert, welche Felder gelten: intern (dt. Lohn),
         pauschal (Festbetrag) oder extern nach Zeitaufwand (Stundensatz). --}}
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Vergütungsmodell') }}</label>
        <select name="compensation_model" x-model="comp"
                class="select select-bordered w-full @error('compensation_model') select-error @enderror"
                @disabled(! $canManagePayroll)>
            <option value="">{{ __('Intern (Lohnabrechnung)') }}</option>
            @foreach (\App\Enums\User\CompensationModel::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('compensation_model', $member?->compensation_model?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('compensation_model')<p class="text-error text-sm">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-base-content/60">{{ __('Extern (pauschal / nach Zeitaufwand) blendet die deutschen Lohnfelder aus.') }}</p>
    </div>

    {{-- Pauschale --}}
    <div class="fieldset" x-show="comp === 'pauschal'" x-cloak>
        <label class="fieldset-label">{{ __('Pauschalbetrag (€)') }}</label>
        <input type="number" name="flat_amount" step="0.01" min="0"
               class="input input-bordered w-full @error('flat_amount') input-error @enderror"
               value="{{ old('flat_amount', $member?->flat_amount) }}"
               @disabled(! $canManagePayroll)>
        @error('flat_amount')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset" x-show="comp === 'pauschal'" x-cloak>
        <label class="fieldset-label">{{ __('Intervall') }}</label>
        <select name="flat_interval" class="select select-bordered w-full @error('flat_interval') select-error @enderror"
                @disabled(! $canManagePayroll)>
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach (\App\Enums\User\FlatInterval::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('flat_interval', $member?->flat_interval?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('flat_interval')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Nach Zeitaufwand --}}
    <div class="fieldset" x-show="comp === 'nach_zeitaufwand'" x-cloak>
        <label class="fieldset-label">{{ __('Stundensatz (Vergütung, €)') }}</label>
        <input type="number" name="compensation_rate" step="0.01" min="0"
               class="input input-bordered w-full @error('compensation_rate') input-error @enderror"
               value="{{ old('compensation_rate', $member?->compensation_rate) }}"
               @disabled(! $canManagePayroll)>
        @error('compensation_rate')<p class="text-error text-sm">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-base-content/60">{{ __('Wird auf die erfasste Zeit angewandt (nicht der Kundensatz).') }}</p>
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Stundenlohn') }}</label>
        <input type="number" name="payroll_hourly_wage" step="0.01" min="0"
               class="input input-bordered w-full @error('payroll_hourly_wage') @enderror @if($belowMin) input-warning @endif"
               value="{{ $wageVal }}"
               @disabled(! $canManagePayroll)>
        @error('payroll_hourly_wage')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @if ($minWage !== null)
            <p class="mt-1 text-xs {{ $belowMin ? 'text-warning font-medium' : 'text-base-content/60' }}">
                @if ($belowMin)
                    {{ __('Unter dem aktuellen Mindestlohn von :min €.', ['min' => number_format($minWage, 2, ',', '.')]) }}
                @else
                    {{ __('Aktueller Mindestlohn: :min €.', ['min' => number_format($minWage, 2, ',', '.')]) }}
                @endif
            </p>
        @endif
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Beschäftigungsart') }}</label>
        <select name="employment_type" class="select select-bordered w-full @error('employment_type') select-error @enderror">
            <option value="">{{ __('— bitte wählen —') }}</option>
            @foreach (\App\Enums\User\EmploymentType::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('employment_type', $member?->employment_type?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('employment_type')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Steuer-ID / Steuernummer') }}</label>
        <input type="text" name="tax_identification_number" maxlength="32"
               class="input input-bordered w-full @error('tax_identification_number') input-error @enderror"
               value="{{ old('tax_identification_number', $member?->tax_identification_number) }}">
        @error('tax_identification_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Geburtstag') }}</label>
        <input type="date" name="date_of_birth"
               class="input input-bordered w-full @error('date_of_birth') input-error @enderror"
               value="{{ old('date_of_birth', $member?->date_of_birth?->format('Y-m-d')) }}">
        @error('date_of_birth')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Sozialversicherungsnummer') }}</label>
        <input type="text" name="social_security_number" maxlength="64"
               class="input input-bordered w-full @error('social_security_number') input-error @enderror"
               value="{{ old('social_security_number', $member?->social_security_number) }}">
        @error('social_security_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Krankenkasse') }}</label>
        <input type="text" name="health_insurance" maxlength="128"
               class="input input-bordered w-full @error('health_insurance') input-error @enderror"
               value="{{ old('health_insurance', $member?->health_insurance) }}">
        @error('health_insurance')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Steuerklasse') }}</label>
        <input type="text" name="tax_class" maxlength="16"
               class="input input-bordered w-full @error('tax_class') input-error @enderror"
               value="{{ old('tax_class', $member?->tax_class) }}">
        @error('tax_class')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <label class="fieldset-label">{{ __('Kinderfreibeträge') }}</label>
        <input type="number" name="child_allowances" step="0.01" min="0"
               class="input input-bordered w-full @error('child_allowances') input-error @enderror"
               value="{{ old('child_allowances', $member?->child_allowances) }}">
        @error('child_allowances')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Wochenstunden: read-only aus dem Arbeitszeit-Modell (Single Source of Truth). --}}
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Wochenstunden') }}</label>
        @php $ws = $member?->workSchedule(); @endphp
        <input type="text" class="input input-bordered w-full" disabled
               value="{{ $ws ? number_format($ws->weekly_minutes / 60, 2, ',', '.') . ' h' : __('— kein Arbeitszeit-Modell —') }}">
        @can('create', \App\Models\WorkSchedule::class)
            @if ($member)
                <a href="{{ route('users.work-schedule.edit', $member) }}" data-entry-modal-trigger
                   class="link link-primary mt-1 inline-flex items-center gap-1 text-xs">
                    <span class="material-symbols-outlined text-[1rem]" aria-hidden="true">schedule</span>
                    {{ __('Arbeitszeit-Modell bearbeiten') }}
                </a>
            @endif
        @endcan
        <p class="mt-1 text-xs text-base-content/60">{{ __('Stammt aus dem Arbeitszeit-Modell.') }}</p>
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Eintrittsdatum') }}</label>
        <input type="date" name="employment_start_date"
               class="input input-bordered w-full @error('employment_start_date') input-error @enderror"
               value="{{ old('employment_start_date', $member?->employment_start_date?->format('Y-m-d')) }}">
        @error('employment_start_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Austrittsdatum') }}</label>
        <input type="date" name="employment_end_date"
               class="input input-bordered w-full @error('employment_end_date') input-error @enderror"
               value="{{ old('employment_end_date', $member?->employment_end_date?->format('Y-m-d')) }}">
        @error('employment_end_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <label class="label cursor-pointer justify-start gap-3 md:col-span-2" x-show="comp === 'payroll' || comp === ''" x-cloak>
        <input type="checkbox" name="church_tax" value="1" class="checkbox checkbox-sm"
               @checked(old('church_tax', $member?->church_tax))>
        <span class="label-text">{{ __('Kirchensteuerpflichtig') }}</span>
    </label>

    @if ($employmentHint)
        <div class="alert alert-warning md:col-span-2 py-2 text-sm" x-show="comp === 'payroll' || comp === ''" x-cloak>
            <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">info</span>
            <span>{{ $employmentHint }}</span>
        </div>
    @endif
</x-form-group>
