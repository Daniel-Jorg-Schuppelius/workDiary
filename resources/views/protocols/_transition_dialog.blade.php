{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transition_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Übergänge mit Eingabe (MVP-883) — Abschluss mit Unterschrift,
     Rückgabe in den Entwurf, Korrektur-Revision mit Begründung. --}}
<x-modal
    :title="__('protocol.action.' . $action)"
    :eyebrow="$protocol->title"
    :icon="$action === 'sign' ? 'draw' : 'undo'"
    :tone="$action === 'supersede' ? 'warning' : 'primary'"
    :action="route('protocols.transition', [$protocol, $action])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.' . $action)">
    @if ($action === 'sign')
        <p class="text-sm text-muted">{{ __('protocol.dialog.sign_hint') }}</p>
        <x-checkbox-field name="with_signature" :label="__('protocol.dialog.with_signature')" :checked="(bool) old('with_signature', true)" />
        <x-input-field name="signature[signer_name]" :label="__('protocol.dialog.signer_name')" :value="old('signature.signer_name', auth()->user()?->name)" maxlength="120" />
        <x-input-field name="signature[signer_email]" type="email" :label="__('protocol.dialog.signer_email')" :value="old('signature.signer_email')" maxlength="180" />
        <x-select-field name="signature[role]" :label="__('protocol.dialog.role')">
            @foreach (\App\Enums\Protocol\ProtocolSignatureRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(old('signature.role', \App\Enums\Protocol\ProtocolSignatureRole::Contractor->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="signature[method]" :label="__('protocol.dialog.method')">
            @foreach ([\App\Enums\Protocol\ProtocolSignatureMethod::Onscreen, \App\Enums\Protocol\ProtocolSignatureMethod::Paper] as $method)
                <option value="{{ $method->value }}" @selected(old('signature.method', \App\Enums\Protocol\ProtocolSignatureMethod::Onscreen->value) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
    @else
        <x-textarea-field name="reason" :label="__('protocol.dialog.reason')" :value="old('reason')" rows="3" :required="$action === 'supersede'" />
    @endif
</x-modal>
