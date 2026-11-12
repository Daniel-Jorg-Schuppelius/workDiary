{{-- Firmen- und Steuerangaben des Ausstellers nach Blockzustand des Designs (MVP-1609, MVP-1624): Teil des Belegschlusses,
     eigenständig in allen übrigen Arten mit Pflichtblock Firmenangaben (RenderDocumentKind::printsIssuerBlocks()), den der Preflight prüft.
     Variablen: $design, $issuer (App\Services\DocumentDesign\IssuerIdentity). --}}
@php
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $issuer ??= [];
    $block = \App\Enums\DocumentDesign\InformationBlock::class;
    $country = strtoupper((string) ($issuer['country'] ?? ''));
    $identity = array_filter([
        $issuer['name'] ?? '',
        $issuer['street'] ?? '',
        trim(($issuer['zip'] ?? '') . ' ' . ($issuer['city'] ?? '')),
        in_array($country, ['', 'DE'], true) ? '' : ($issuer['country'] ?? ''),
        $issuer['register'] ?? '',
    ], static fn ($part): bool => $part !== '');
    $tax = array_filter([
        ($issuer['vat_id'] ?? '') !== '' ? __('USt-IdNr.') . ': ' . $issuer['vat_id'] : '',
        ($issuer['tax_number'] ?? '') !== '' ? __('Steuernr.') . ': ' . $issuer['tax_number'] : '',
    ], static fn ($part): bool => $part !== '');
@endphp
@if ($design->show($block::CompanyIdentity) && $identity !== [])
    <div class="tpl-issuer" data-block="company_identity">{{ implode(' · ', $identity) }}@if (($issuer['legal_note'] ?? '') !== '')<br><span style="white-space: pre-line;">{{ $issuer['legal_note'] }}</span>@endif</div>
@endif
@if ($design->show($block::TaxIdentity) && $tax !== [])
    <div class="tpl-issuer" data-block="tax_identity">{{ implode(' · ', $tax) }}</div>
@endif
