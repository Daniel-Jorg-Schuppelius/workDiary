{{-- Shared form fields for Timesheet create & edit --}}
@php
    // Beim Neuanlegen Kunden-Felder aus dem Kundenstamm vorbefüllen
    // (primärer Ansprechpartner des Projekt-Kunden).
    $isNewTimesheet = ! $timesheet->exists;
    $customer = $project->customer ?? null;
    $primaryContact = $customer?->primaryContact() ?? ['name' => null, 'email' => null, 'phone' => null];
    $defaultCustomerName  = $isNewTimesheet ? ($customer?->name ?? '') : $timesheet->customer_name;
    $defaultCustomerRole  = $isNewTimesheet ? ($primaryContact['name'] ?? '') : $timesheet->customer_role;
    $defaultCustomerEmail = $isNewTimesheet ? ($primaryContact['email'] ?? '') : $timesheet->customer_email;
@endphp

<x-form-group :legend="__('Stammdaten')" icon="description" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="work_date" required
               value="{{ old('work_date', optional($timesheet->work_date)->format('Y-m-d')) }}"
               class="input input-bordered w-full">
        @error('work_date')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <x-input-field name="customer_name" :label="__('Kunde – Name')" maxlength="255" :value="old('customer_name', $defaultCustomerName)" :span="2" />
    <x-input-field name="customer_role" :label="__('Rolle / Funktion')" maxlength="255" :value="old('customer_role', $defaultCustomerRole)" />
    <x-input-field name="customer_email" type="email" :label="__('E-Mail')" maxlength="255" :value="old('customer_email', $defaultCustomerEmail)" />
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="notes" tone="ghost" cols="1">
    <x-textarea-field name="notes" rows="4" :value="old('notes', $timesheet->notes)" />
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
