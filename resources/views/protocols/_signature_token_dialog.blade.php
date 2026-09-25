{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _signature_token_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Signaturlink an eine externe Person senden (MVP-883). --}}
<x-modal
    :title="__('protocol.dialog.token_title')"
    :eyebrow="$protocol->title"
    icon="link"
    tone="primary"
    :action="route('protocols.signature-tokens.store', $protocol)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.dialog.token_submit')">
    <p class="text-sm text-muted">{{ __('protocol.dialog.token_hint') }}</p>
    <x-input-field name="signer_name" :label="__('protocol.dialog.signer_name')" :value="old('signer_name')" maxlength="120" />
    <x-input-field name="signer_email" type="email" :label="__('protocol.dialog.signer_email')" :value="old('signer_email')" required maxlength="180" />
    <x-select-field name="role" :label="__('protocol.dialog.role')" required>
        @foreach (\App\Enums\Protocol\ProtocolSignatureRole::cases() as $role)
            <option value="{{ $role->value }}" @selected(old('role', \App\Enums\Protocol\ProtocolSignatureRole::Customer->value) === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="ttl_days" type="number" min="1" max="30" :label="__('protocol.dialog.ttl_days')" :value="old('ttl_days', 7)" />
</x-modal>
