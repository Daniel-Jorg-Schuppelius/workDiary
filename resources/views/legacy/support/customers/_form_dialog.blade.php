{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenzugang des Support-Portals. Erwartet: $customer, $isEdit --}}
@php
    $inputClass = 'input input-bordered input-sm w-full';
@endphp
<x-modal
    :title="$isEdit ? __('legacy_support.customers.edit') : __('legacy_support.customers.create')"
    :eyebrow="__('legacy_support.nav.customers')"
    icon="badge"
    tone="primary"
    :action="$isEdit ? route('legacy.support.customers.update', $customer) : route('legacy.support.customers.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('legacy_support.save')">

    <div>
        <label for="support-customer-kunde" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.company') }}<span class="text-error">*</span></label>
        <input id="support-customer-kunde" name="kunde" type="text" maxlength="255" value="{{ old('kunde', $customer?->kunde) }}" class="{{ $inputClass }} @error('kunde') input-error @enderror" required>
        @error('kunde')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="support-customer-usern" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.username') }}<span class="text-error">*</span></label>
        <input id="support-customer-usern" name="usern" type="text" minlength="4" maxlength="10" autocomplete="off" value="{{ old('usern', $customer?->usern) }}" class="{{ $inputClass }} @error('usern') input-error @enderror" required>
        @error('usern')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="support-customer-passw" class="mb-1 block text-sm font-medium">
                {{ __('legacy_support.field.password') }}@unless ($isEdit)<span class="text-error">*</span>@endunless
            </label>
            <input id="support-customer-passw" name="passw" type="password" minlength="4" maxlength="10" autocomplete="new-password" class="{{ $inputClass }} @error('passw') input-error @enderror" @unless ($isEdit) required @endunless>
            @error('passw')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="support-customer-passw-confirmation" class="mb-1 block text-sm font-medium">{{ __('legacy_support.field.password_repeat') }}</label>
            <input id="support-customer-passw-confirmation" name="passw_confirmation" type="password" maxlength="10" autocomplete="new-password" class="{{ $inputClass }}">
        </div>
    </div>
    <p class="text-xs text-muted">{{ $isEdit ? __('legacy_support.customers.password_keep_hint') : __('legacy_support.customers.password_hint') }}</p>
</x-modal>
