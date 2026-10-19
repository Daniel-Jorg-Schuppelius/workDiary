{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_create_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenportal-Zugang mit Startpasswort anlegen (Phase 153, MVP-1232) — erwartet: $customer, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = route('customers.portal-access.store-direct', $customer);
    $dialogUrl = route('customers.portal-access.create-direct', $customer) . '?dialog=1';
@endphp

<x-modal
    :title="__('customer_portal.direct.title')"
    :eyebrow="$customer->name"
    icon="key"
    tone="primary"
    :action="$action"
    method="POST"
    form-id="portal-create-form"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_portal.direct.submit')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <p class="text-sm text-base-content/70">{{ __('customer_portal.direct.intro') }}</p>

    <div class="fieldset">
        <label class="fieldset-label" for="portal-create-name">{{ __('Name') }}</label>
        <input id="portal-create-name" name="name" type="text" required maxlength="191"
               class="input input-bordered w-full" value="{{ old('name') }}">
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label" for="portal-create-email">{{ __('E-Mail') }}</label>
        <input id="portal-create-email" name="email" type="email" required maxlength="191"
               class="input input-bordered w-full" value="{{ old('email') }}">
        @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-modal>
