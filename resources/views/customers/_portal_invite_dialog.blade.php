{{-- Kundenportal-Zugang einladen (MVP-510) — erwartet: $customer, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = route('customers.portal-access.store', $customer);
    $dialogUrl = route('customers.portal-access.create', $customer) . '?dialog=1';
@endphp

<x-modal
    :title="__('Portalzugang einladen')"
    :eyebrow="$customer->name"
    icon="person_add"
    tone="primary"
    :action="$action"
    method="POST"
    form-id="portal-invite-form"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Einladung senden')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <p class="text-sm text-base-content/70">
        {{ __('Die eingeladene Person erhält einen einmaligen, :days Tage gültigen Link zur Passwortvergabe. Es werden keine Passwörter per E-Mail versendet; der Zugang sieht nur die für diesen Kunden freigegebenen Bereiche.', ['days' => \App\Services\CustomerPortal\PortalAccessService::INVITE_TTL_DAYS]) }}
    </p>

    <div class="fieldset">
        <label class="fieldset-label" for="portal-invite-name">{{ __('Name') }}</label>
        <input id="portal-invite-name" name="name" type="text" required maxlength="191"
               class="input input-bordered w-full" value="{{ old('name') }}">
        @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label" for="portal-invite-email">{{ __('E-Mail') }}</label>
        <input id="portal-invite-email" name="email" type="email" required maxlength="191"
               class="input input-bordered w-full" value="{{ old('email') }}">
        @error('email')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-modal>
