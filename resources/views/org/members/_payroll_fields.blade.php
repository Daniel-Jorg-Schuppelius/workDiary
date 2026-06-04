{{-- Personal-/Lohndaten. Erwartet $member (nullable) und $canManagePayroll.
     Wird im Admin-Formular und im HR-/GF-Zweig (Personalverwaltung) genutzt. --}}
@php $canManagePayroll = $canManagePayroll ?? false; @endphp
<x-form-group :legend="__('Lohnabrechnung')" icon="payments" tone="warning" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Stundenlohn') }}</label>
        <input type="number" name="payroll_hourly_wage" step="0.01" min="0"
               class="input input-bordered w-full @error('payroll_hourly_wage') input-error @enderror"
               value="{{ old('payroll_hourly_wage', $member?->payroll_hourly_wage) }}"
               @disabled(! $canManagePayroll)>
        @error('payroll_hourly_wage')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
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

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Sozialversicherungsnummer') }}</label>
        <input type="text" name="social_security_number" maxlength="64"
               class="input input-bordered w-full @error('social_security_number') input-error @enderror"
               value="{{ old('social_security_number', $member?->social_security_number) }}">
        @error('social_security_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Krankenkasse') }}</label>
        <input type="text" name="health_insurance" maxlength="128"
               class="input input-bordered w-full @error('health_insurance') input-error @enderror"
               value="{{ old('health_insurance', $member?->health_insurance) }}">
        @error('health_insurance')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Steuerklasse') }}</label>
        <input type="text" name="tax_class" maxlength="16"
               class="input input-bordered w-full @error('tax_class') input-error @enderror"
               value="{{ old('tax_class', $member?->tax_class) }}">
        @error('tax_class')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
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

    <label class="label cursor-pointer justify-start gap-3 md:col-span-2">
        <input type="checkbox" name="church_tax" value="1" class="checkbox checkbox-sm"
               @checked(old('church_tax', $member?->church_tax))>
        <span class="label-text">{{ __('Kirchensteuerpflichtig') }}</span>
    </label>
</x-form-group>
