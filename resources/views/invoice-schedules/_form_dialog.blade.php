{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-415: Abrechnungsplan. Variablen: $schedule, $isEdit, $customers, $contracts --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit ? route('invoice-schedules.update', $schedule) : route('invoice-schedules.store');
    $selectedCustomer = (string) old('customer_id', $schedule ? \App\Support\Sqid::encode(\App\Models\Customer::class, $schedule->customer_id) : '');
    $selectedContract = (string) old('contract_id', $schedule?->contract_id !== null ? \App\Support\Sqid::encode(\App\Models\Contract\Contract::class, $schedule->contract_id) : '');
@endphp

<x-modal
    :title="$isEdit ? __('Abrechnungsplan bearbeiten') : __('Abrechnungsplan anlegen')"
    :eyebrow="__('Wiederkehrende Rechnungen')"
    icon="event_repeat"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary" cols="2">
        @if ($isEdit)
            <div class="fieldset" span="2">
                <span class="fieldset-label">{{ __('Kunde') }}</span>
                <p class="text-sm">{{ $schedule->customer?->displayLabel() ?? '—' }}</p>
            </div>
        @else
            <div class="fieldset">
                <label class="fieldset-label" for="is-customer">{{ __('Kunde') }} *</label>
                <select id="is-customer" name="customer_id" class="select select-bordered w-full" required>
                    <option value="">{{ __('Bitte wählen') }}</option>
                    @foreach ($customers as $customer)
                        @php($csqid = \App\Support\Sqid::encode(\App\Models\Customer::class, (int) $customer->id))
                        <option value="{{ $csqid }}" @selected($selectedCustomer === $csqid)>{{ $customer->displayLabel() }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="fieldset">
            <label class="fieldset-label" for="is-contract">{{ __('Vertrag (optional)') }}</label>
            <select id="is-contract" name="contract_id" class="select select-bordered w-full">
                <option value="">{{ __('— keiner —') }}</option>
                @foreach ($contracts as $contract)
                    @php($ksqid = \App\Support\Sqid::encode(\App\Models\Contract\Contract::class, (int) $contract->id))
                    <option value="{{ $ksqid }}" @selected($selectedContract === $ksqid)>{{ $contract->title }}</option>
                @endforeach
            </select>
            <p class="text-xs text-muted">{{ __('Endet der Vertrag, endet der Plan automatisch.') }}</p>
        </div>
        <x-input-field name="title" :label="__('Titel')" required maxlength="180" span="2" :value="old('title', $schedule->title ?? '')" />
    </x-form-group>

    <x-form-group :legend="__('Rhythmus')" icon="event_repeat" tone="ghost" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="is-unit">{{ __('Intervall') }} *</label>
            {{-- Inline- statt Block-Form: Blades lazy Raw-Block-Regex paart die
                 Inline-Form weiter oben sonst mit dem Block-Ende hier und frisst
                 den halben View (ParseError erst beim Rendern). --}}
            @php($unitLabels = [\App\Models\InvoiceSchedule::UNIT_WEEK => __('Woche(n)'), \App\Models\InvoiceSchedule::UNIT_MONTH => __('Monat(e)'), \App\Models\InvoiceSchedule::UNIT_QUARTER => __('Quartal(e)'), \App\Models\InvoiceSchedule::UNIT_YEAR => __('Jahr(e)')])
            <select id="is-unit" name="interval_unit" class="select select-bordered w-full" required>
                @foreach (\App\Models\InvoiceSchedule::UNITS as $unit)
                    <option value="{{ $unit }}" @selected(old('interval_unit', $schedule->interval_unit ?? 'month') === $unit)>{{ $unitLabels[$unit] }}</option>
                @endforeach
            </select>
        </div>
        <x-input-field name="interval_count" type="number" :label="__('Anzahl (z. B. 3 = quartalsweise bei Monat)')" required min="1" max="12" step="1" :value="old('interval_count', (string) ($schedule->interval_count ?? '1'))" />
        <div class="fieldset">
            <label class="fieldset-label" for="is-mode">{{ __('Abrechnungszeitraum') }} *</label>
            <select id="is-mode" name="billing_period_mode" class="select select-bordered w-full" required>
                <option value="previous" @selected(old('billing_period_mode', $schedule->billing_period_mode ?? 'previous') === 'previous')>{{ __('abgelaufener Zeitraum') }}</option>
                <option value="current" @selected(old('billing_period_mode', $schedule->billing_period_mode ?? 'previous') === 'current')>{{ __('laufender Zeitraum (Vorauszahlung)') }}</option>
            </select>
        </div>
        <x-input-field name="next_run_on" type="date" :label="__('Nächster Lauf am')" required :value="old('next_run_on', $schedule?->next_run_on?->format('Y-m-d') ?? '')" />
        <x-input-field name="end_on" type="date" :label="__('Endet am (optional)')" :value="old('end_on', $schedule?->end_on?->format('Y-m-d') ?? '')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
