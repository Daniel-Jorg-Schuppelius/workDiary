{{-- Erwartet: $foreignCustomer, $isDialog, $customers, $presetCustomerId --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $foreignCustomer ? route('foreign-customers.update', $foreignCustomer) : route('foreign-customers.store');
    $dialogUrl = ($foreignCustomer ? route('foreign-customers.edit', $foreignCustomer) : route('foreign-customers.create')) . '?dialog=1';
    $selectedCustomerId = old('customer_id') !== null
        ? null // bei Validierungsfehler über old() unten gematcht (Sqid)
        : ($foreignCustomer?->customer_id ?? $presetCustomerId ?? null);
    $oldCustomerSqid = old('customer_id');
@endphp

<x-modal
    :title="$foreignCustomer ? __('Fremdkunde bearbeiten') : __('Neuer Fremdkunde')"
    :eyebrow="__('Fremdkunde')"
    icon="groups"
    tone="primary"
    :action="$action"
    :method="$foreignCustomer ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$foreignCustomer ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="groups" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Kunde (Firma)') }} *</label>
            <select name="customer_id" required class="select select-bordered w-full">
                <option value="">{{ __('— Kunde wählen —') }}</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}"
                        @if ($oldCustomerSqid !== null) @selected($oldCustomerSqid === $c->sqid)
                        @else @selected($selectedCustomerId === $c->id) @endif>
                        {{ $c->company ?: $c->name }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Name') }} *</label>
            <input name="name" type="text" required maxlength="200"
                   class="input input-bordered w-full"
                   value="{{ old('name', $foreignCustomer?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Firma') }}</label>
            <input name="company" type="text" maxlength="200"
                   class="input input-bordered w-full"
                   value="{{ old('company', $foreignCustomer?->company) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Kürzel (Matchcode)') }}</label>
            <input name="matchcode" type="text" maxlength="16"
                   class="input input-bordered w-full"
                   value="{{ old('matchcode', $foreignCustomer?->matchcode) }}"
                   placeholder="{{ __('z. B. GSL') }}">
            @error('matchcode')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Farbe') }}</label>
            <input name="color" type="text" maxlength="16"
                   class="input input-bordered w-full"
                   value="{{ old('color', $foreignCustomer?->color) }}" placeholder="#64748b">
        </div>
    </x-form-group>

    <x-form-group :legend="__('Kontakt')" icon="call" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Ansprechpartner') }}</label>
            <input name="contact_name" type="text" maxlength="200"
                   class="input input-bordered w-full"
                   value="{{ old('contact_name', $foreignCustomer?->contact_name) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('E-Mail') }}</label>
            <input name="email" type="email" maxlength="255"
                   class="input input-bordered w-full"
                   value="{{ old('email', $foreignCustomer?->email) }}">
            @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Telefon') }}</label>
            <input name="phone" type="text" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('phone', $foreignCustomer?->phone) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Mobil') }}</label>
            <input name="mobile" type="text" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('mobile', $foreignCustomer?->mobile) }}">
        </div>
    </x-form-group>

    <x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Adresse (Freitext, optional)') }}</label>
            <textarea name="address" rows="2" maxlength="1000"
                      class="textarea textarea-bordered w-full">{{ old('address', $foreignCustomer?->address) }}</textarea>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Land (ISO 2)') }}</label>
            <input name="country" type="text" maxlength="2"
                   class="input input-bordered w-full uppercase"
                   value="{{ old('country', $foreignCustomer?->country) }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Homepage') }}</label>
            <input name="homepage" type="url" maxlength="255"
                   class="input input-bordered w-full"
                   value="{{ old('homepage', $foreignCustomer?->homepage) }}">
            @error('homepage')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Notiz (intern)') }}</label>
            <textarea name="comment" rows="2" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('comment', $foreignCustomer?->comment) }}</textarea>
        </div>
    </x-form-group>
</x-modal>
