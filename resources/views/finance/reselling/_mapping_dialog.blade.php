{{--
  Created on   : Thu Sep 03 2026
  License      : AGPL-3.0-or-later

  Zuordnung einer Marketplace-Firma (Feature 151): direkt als Kunde, über
  einen Partner (Fremdkunde — der Partner bekommt die Rechnung) oder als
  Lexoffice-Kontakt. Gilt je Organisation für alle künftigen Läufe.
--}}
<x-modal
    :title="__('reselling.mapping.title')"
    icon="link"
    tone="primary"
    size="md"
    :action="route('finance.reselling.mappings.store', $run->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('reselling.mapping.submit')"
>
    <input type="hidden" name="company_name" value="{{ $companyName }}">
    <input type="hidden" name="company_key" value="{{ $companyKey }}">

    <div class="text-sm">
        <span class="text-muted">{{ __('reselling.field.company') }}:</span>
        <span class="font-medium">{{ $companyName }}</span>
    </div>
    <p class="text-xs text-muted">{{ __('reselling.mapping.hint') }}</p>

    <fieldset>
        <legend class="label-text mb-1">{{ __('reselling.mapping.mode_label') }}</legend>
        <div class="flex flex-col gap-2">
            @foreach ($modes as $mode)
                <label class="flex items-start gap-2 text-sm cursor-pointer" for="reselling-mode-{{ $mode->value }}">
                    <input id="reselling-mode-{{ $mode->value }}" type="radio" name="mode" value="{{ $mode->value }}" class="radio radio-sm mt-0.5"
                           @checked(old('mode', $existing?->mode->value ?? \App\Enums\Reselling\CompanyMappingMode::Customer->value) === $mode->value)>
                    <span>
                        <span class="font-medium">{{ $mode->label() }}</span>
                        <span class="block text-xs text-muted">{{ __('reselling.mapping.mode_hint.' . $mode->value) }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('mode')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </fieldset>

    <div>
        <label class="label" for="reselling-mapping-customer">
            <span class="label-text">{{ __('reselling.mapping.customer') }}</span>
        </label>
        <select id="reselling-mapping-customer" name="customer" class="select select-bordered w-full @error('customer') select-error @enderror">
            <option value="">{{ __('reselling.mapping.customer_placeholder') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected(old('customer', $existing?->customer?->sqid) === $customer->sqid)>{{ $customer->name }}@if ($customer->company && $customer->company !== $customer->name) · {{ $customer->company }}@endif</option>
            @endforeach
        </select>
        @error('customer')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="reselling-mapping-contact">
            <span class="label-text">{{ __('reselling.mapping.contact') }}</span>
        </label>
        <input id="reselling-mapping-contact" type="text" name="contact_external_id" maxlength="36"
               value="{{ old('contact_external_id', $existing?->contact_external_id) }}"
               class="input input-bordered w-full font-mono text-sm @error('contact_external_id') input-error @enderror">
        <p class="text-xs text-muted mt-1">{{ __('reselling.mapping.contact_hint') }}</p>
        @error('contact_external_id')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>
</x-modal>
