{{-- Shared form fields for Timesheet create & edit --}}

<x-form-group :legend="__('Stammdaten')" icon="description" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="work_date" required
               value="{{ old('work_date', optional($timesheet->work_date)->format('Y-m-d')) }}"
               class="input input-bordered w-full">
        @error('work_date')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Kunde – Name') }}</label>
        <input type="text" name="customer_name" maxlength="255"
               value="{{ old('customer_name', $timesheet->customer_name) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Rolle / Funktion') }}</label>
        <input type="text" name="customer_role" maxlength="255"
               value="{{ old('customer_role', $timesheet->customer_role) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('E-Mail') }}</label>
        <input type="email" name="customer_email" maxlength="255"
               value="{{ old('customer_email', $timesheet->customer_email) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Notizen')" icon="notes" tone="ghost" cols="1">
    <div class="fieldset">
        <textarea name="notes" rows="4" class="textarea textarea-bordered w-full">{{ old('notes', $timesheet->notes) }}</textarea>
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
