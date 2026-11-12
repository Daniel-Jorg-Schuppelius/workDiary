{{-- Schluss der Beleg-PDFs (MVP-1609): Fußtext, Ansprechpartner, Firmen- und Steuerangaben, Vertraulichkeitsvermerk — je nach Blockzustand des Designs.
     Variablen: $design, $issuer (App\Services\DocumentDesign\IssuerIdentity), optional $contactPerson (name, phone, email). --}}
@php
    $issuer ??= [];
    $block = \App\Enums\DocumentDesign\InformationBlock::class;
    $contact = array_filter([
        trim((string) (($contactPerson ?? [])['name'] ?? '')) ?: ($issuer['contact_name'] ?? ''),
        ($phone = trim((string) (($contactPerson ?? [])['phone'] ?? '')) ?: ($issuer['contact_phone'] ?? '')) !== '' ? __('Tel.') . ': ' . $phone : '',
        trim((string) (($contactPerson ?? [])['email'] ?? '')) ?: ($issuer['contact_email'] ?? ''),
    ], static fn ($part): bool => $part !== '');
@endphp
@if ($design->show($block::ClosingText) && $design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
@if ($design->show($block::ContactPerson) && $contact !== [])
    <div class="tpl-issuer" data-block="contact_person">{{ __('Ihr Ansprechpartner') }}: {{ implode(' · ', $contact) }}</div>
@endif
@include('pdf._design_issuer')
@if ($design->show($block::Confidentiality))
    <div class="tpl-issuer" data-block="confidentiality">{{ $design->confidentialityText() }}</div>
@endif
