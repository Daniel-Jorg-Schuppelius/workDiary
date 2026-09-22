{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _countersign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog: Gegenzeichnung der Organisationsseite (Feature 157).
  Erwartet: $contract, $signatureRequest (mit revision).
--}}
<x-modal
    :title="__('contract-signing.dialog.countersign_title', ['no' => $signatureRequest->revision->revision_no])"
    :eyebrow="$contract->number . ' — ' . $contract->title"
    icon="draw"
    tone="primary"
    :action="route('contracts.signing.requests.countersign.store', $signatureRequest)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('contract-signing.action.countersign')"
>
    <p class="mb-3 text-sm text-muted">{{ __('contract-signing.dialog.countersign_intro') }}</p>

    @include('contracts.signing._signature_fields', [
        'signerName' => $signatureRequest->signer_name,
        'signerFunction' => $signatureRequest->signer_function,
        'declaration' => $signatureRequest->revision->declaration_text,
        'idPrefix' => 'countersign',
    ])

    <x-validation-errors />
</x-modal>
