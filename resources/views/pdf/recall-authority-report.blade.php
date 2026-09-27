{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recall-authority-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldebogen Rückruf (MVP-945). Erwartet: $recall, $organization, $stats --}}
<x-pdf-layout pdf-type="report" :pdf-title="__('recall.authority.pdf_title') . ' ' . $recall->number">
    <h1>{{ __('recall.authority.pdf_title') }} {{ $recall->number }}</h1>
    <div class="meta">{{ $organization?->name }} · {{ now()->format('d.m.Y') }}</div>

    <h2>{{ __('recall.authority.section.product') }}</h2>
    <table>
        <tr><th>{{ __('recall.authority.field.product') }}</th><td>{{ $recall->variant?->article?->name }} ({{ $recall->variant?->sku }})</td></tr>
        <tr><th>{{ __('recall.authority.field.gtin') }}</th><td>{{ $recall->variant?->gtin ?? $recall->variant?->article?->gtin ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.batches') }}</th><td>{{ implode(', ', (array) $recall->serial_numbers) ?: '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.delivered') }}</th><td>{{ $recall->delivered_from?->format('d.m.Y') ?? '—' }} – {{ $recall->delivered_until?->format('d.m.Y') ?? '—' }}</td></tr>
    </table>

    <h2>{{ __('recall.authority.section.hazard') }}</h2>
    <table>
        <tr><th>{{ __('recall.authority.field.hazard_kind') }}</th><td>{{ $recall->hazard_kind ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.hazard_description') }}</th><td>{{ $recall->hazard_description ?? $recall->reason }}</td></tr>
        <tr><th>{{ __('recall.authority.field.risk_level') }}</th><td>{{ $recall->risk_level?->label() ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.measure') }}</th><td>{{ $recall->measure?->label() ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.countries') }}</th><td>{{ implode(', ', (array) $recall->countries) ?: '—' }}</td></tr>
    </table>

    <h2>{{ __('recall.authority.section.scope') }}</h2>
    <table>
        <tr><th>{{ __('recall.authority.field.units') }}</th><td>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($stats['units'], 0, withThousandsSeparator: true) }}</td></tr>
        <tr><th>{{ __('recall.authority.field.customers') }}</th><td>{{ $stats['customers'] }}</td></tr>
        <tr><th>{{ __('recall.authority.field.returned') }}</th><td>{{ $stats['returned'] }} / {{ $stats['items'] }}</td></tr>
        <tr><th>{{ __('recall.authority.field.activated_at') }}</th><td>{{ $recall->activated_at?->format('d.m.Y') ?? '—' }}</td></tr>
    </table>

    <h2>{{ __('recall.authority.section.authority') }}</h2>
    <table>
        <tr><th>{{ __('recall.authority.field.authority_name') }}</th><td>{{ $recall->authority_name ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.authority_reference') }}</th><td>{{ $recall->authority_reference ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.authority_reported_on') }}</th><td>{{ $recall->authority_reported_on?->format('d.m.Y') ?? '—' }}</td></tr>
        <tr><th>{{ __('recall.authority.field.contact') }}</th><td>{{ $recall->contact_name ?? '—' }} {{ $recall->contact_email }}</td></tr>
    </table>
    <p style="margin-top: 12pt; font-size: 8pt;">{{ __('recall.authority.pdf_note') }}</p>
</x-pdf-layout>
