{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _signature_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://schuppelius.org

  Gemeinsame Felder der Browser-Unterzeichnung (Feature 157): Name, Funktion,
  Methode (Zeichnen oder Namen eingeben), Signaturfeld, Erklärungen. Wird in
  einem umgebenden <form> gerendert — intern im Dialog, öffentlich am Link.
  Erwartet: $signerName, $signerFunction, $declaration, $idPrefix.
  Zeichnen und Eingeben sind beide sichtbar: die Wahl trifft der Radio-
  Button, die Prüfung der Server — kein Alpine-Zustand nötig (CSP-Build).
--}}
@once
    @push('scripts')
        @vite('resources/js/signature.js')
    @endpush
@endonce
<div class="grid gap-3 sm:grid-cols-2">
    <x-input-field name="signer_name" id="{{ $idPrefix }}-signer-name" :label="__('contract-signing.field.signer_name')" :value="old('signer_name', $signerName)" required />
    <x-input-field name="signer_function" id="{{ $idPrefix }}-signer-function" :label="__('contract-signing.field.signer_function')" :value="old('signer_function', $signerFunction)" />
</div>

<fieldset class="wd-fieldset mt-3">
    <span class="fieldset-label">{{ __('contract-signing.field.signature_method') }}</span>
    <div class="flex flex-wrap gap-4">
        <label class="label cursor-pointer justify-start gap-2">
            <input type="radio" name="signature_method" value="drawn" class="radio radio-sm" @checked(old('signature_method', 'drawn') === 'drawn')>
            <span class="label-text">{{ __('contract-signing.method.drawn') }}</span>
        </label>
        <label class="label cursor-pointer justify-start gap-2">
            <input type="radio" name="signature_method" value="typed" class="radio radio-sm" @checked(old('signature_method') === 'typed')>
            <span class="label-text">{{ __('contract-signing.method.typed') }}</span>
        </label>
    </div>
</fieldset>

<div x-data="signaturePad" class="mt-2 flex flex-col gap-2">
    <span class="text-xs text-muted">{{ __('contract-signing.hint.draw') }}</span>
    <div class="rounded-box border border-base-300 bg-white p-2">
        <canvas x-ref="canvas" class="block h-32 w-full touch-none rounded bg-white" aria-label="{{ __('contract-signing.field.signature') }}"></canvas>
    </div>
    <input type="hidden" name="signature" x-ref="sigInput">
    <div class="flex items-center justify-between gap-2">
        <button type="button" class="btn btn-ghost btn-xs" @click="clear()">{{ __('contract-signing.action.clear_signature') }}</button>
        <span class="text-xs text-muted" x-show="hasSignature">{{ __('contract-signing.hint.signature_captured') }}</span>
    </div>
</div>

<div class="mt-2">
    <x-input-field name="typed_name" id="{{ $idPrefix }}-typed-name" :label="__('contract-signing.field.typed_name')" :value="old('typed_name')" :hint="__('contract-signing.hint.typed')" />
</div>

<div class="mt-3 rounded-box bg-base-200 p-3 text-sm">
    <p class="mb-2 whitespace-pre-line text-xs">{{ $declaration }}</p>
    <x-checkbox-field name="authority_confirmed" id="{{ $idPrefix }}-authority" :label="__('contract-signing.field.authority_confirmed')" :toggle="false" :with-hidden="false" />
    <x-checkbox-field name="declaration_accepted" id="{{ $idPrefix }}-declaration" :label="__('contract-signing.field.declaration_accepted')" :toggle="false" :with-hidden="false" />
</div>
