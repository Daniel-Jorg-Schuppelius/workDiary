{{-- Shared form fields for Invoice create --}}

<x-form-group :legend="__('Filter')" icon="receipt_long" tone="primary" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Kunde') }} *</label>
        <select name="customer_id" required class="select select-bordered w-full">
            <option value="">{{ __('-- bitte wählen --') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Projekt (optional)') }}</label>
        <select name="project_id" class="select select-bordered w-full" data-depends-on="customer_id">
            <option value="">{{ __('alle Projekte des Kunden') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Fremdkunde / Endkunde (optional)') }}</label>
        <select name="foreign_customer_id" class="select select-bordered w-full" data-depends-on="customer_id">
            <option value="">{{ __('alle Endkunden') }}</option>
            @foreach (($foreignCustomers ?? collect()) as $fc)
                <option value="{{ $fc->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $fc->customer_id) }}" @selected((string) old('foreign_customer_id') === $fc->sqid)>{{ $fc->company ?: $fc->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-base-content/60">{{ __('Nur Zeiten dieses Endkunden abrechnen.') }}</p>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Von') }}</label>
        <input type="date" name="from" value="{{ old('from', $defaultFrom ?? '') }}" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Bis') }}</label>
        <input type="date" name="to" value="{{ old('to', $defaultTo ?? '') }}" class="input input-bordered w-full">
    </div>
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
