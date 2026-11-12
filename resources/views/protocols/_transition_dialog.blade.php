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
@php
    $missingRoles ??= [];
    $defaultRole = ($missingRoles[0] ?? \App\Enums\Protocol\ProtocolSignatureRole::Contractor)->value;
    $paper = \App\Enums\Protocol\ProtocolSignatureMethod::Paper;
    $onscreen = \App\Enums\Protocol\ProtocolSignatureMethod::Onscreen;
@endphp
<x-modal
    :title="__('protocol.action.' . $action)"
    :eyebrow="$protocol->title"
    :icon="$action === 'sign' ? 'draw' : 'undo'"
    :tone="$action === 'supersede' ? 'warning' : 'primary'"
    :action="route('protocols.transition', [$protocol, $action])"
    method="POST"
    :enctype="$action === 'sign' ? 'multipart/form-data' : null"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.' . $action)">
    @if ($action === 'sign')
        <p class="text-sm text-muted">{{ __('protocol.dialog.sign_hint') }}</p>
        @if ($protocol->status->isEditable() && count($missingRoles) > 1)
            <p class="text-sm text-warning">{{ __('protocol.validation.signature.reviewFirst') }}</p>
        @endif
        @if ($missingRoles !== [])
            {{-- Pflichtunterschrift des Typs (MVP-1572): nicht abwählbar. --}}
            <div role="note" class="alert alert-info text-sm">
                <x-icon name="draw" />
                <span>{{ __('protocol.dialog.signature_required', ['roles' => implode(', ', array_map(static fn ($role) => $role->label(), $missingRoles))]) }}</span>
            </div>
            <input type="hidden" name="with_signature" value="1">
        @else
            <x-checkbox-field name="with_signature" :label="__('protocol.dialog.with_signature')" :checked="(bool) old('with_signature', true)" />
        @endif
        <x-input-field name="signature[signer_name]" :label="__('protocol.dialog.signer_name')" :value="old('signature.signer_name', $defaultRole === \App\Enums\Protocol\ProtocolSignatureRole::Customer->value ? null : auth()->user()?->name)" minlength="2" maxlength="120" />
        <x-input-field name="signature[signer_email]" type="email" :label="__('protocol.dialog.signer_email')" :value="old('signature.signer_email')" maxlength="180" />
        <x-select-field name="signature[role]" :label="__('protocol.dialog.role')">
            @foreach (\App\Enums\Protocol\ProtocolSignatureRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(old('signature.role', $defaultRole) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="signature[method]" :label="__('protocol.dialog.method')" :hint="__('protocol.dialog.method_hint')">
            @foreach ([$onscreen, $paper] as $method)
                <option value="{{ $method->value }}" @selected(old('signature.method', $onscreen->value) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-select-field>
        {{-- Zeichnen und Scan stehen beide da: die Art wählt das Feld darüber, geprüft wird am Server (wie bei Verträgen, CSP-Build). --}}
        <div x-data="signaturePad" class="flex flex-col gap-2">
            <span class="text-sm font-medium">{{ $onscreen->label() }}: {{ __('protocol.dialog.signature_image') }}</span>
            <div class="rounded-box border border-base-300 bg-white p-2">
                <canvas x-ref="canvas" class="block h-32 w-full touch-none rounded bg-white" aria-label="{{ __('protocol.dialog.signature_image') }}"></canvas>
            </div>
            <input type="hidden" name="signature[image]" x-ref="sigInput">
            <x-button tone="ghost" size="xs" class="self-start" @click="clear()">{{ __('protocol.dialog.signature_clear') }}</x-button>
        </div>
        <fieldset class="wd-fieldset">
            <span class="fieldset-label">{{ $paper->label() }}</span>
            <label class="label" for="protocol-signature-scan">{{ __('protocol.dialog.signature_scan') }}</label>
            <input type="file" id="protocol-signature-scan" name="signature[scan]" accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment"
                   class="file-input file-input-bordered file-input-sm w-full">
            <p class="fieldset-label text-muted">{{ __('protocol.dialog.signature_scan_hint') }}</p>
            <x-checkbox-field name="signature[original_confirmed]" id="protocol-signature-original" :label="__('protocol.dialog.original_confirmed')" :toggle="false" :checked="(bool) old('signature.original_confirmed')" />
        </fieldset>
    @else
        <x-textarea-field name="reason" :label="__('protocol.dialog.reason')" :value="old('reason')" rows="3" :required="$action === 'supersede'" />
    @endif
</x-modal>
