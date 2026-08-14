{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    §3.9 Datenschutzbericht (MVP-327): stichtagsbezogener PDF-Bericht der
    Datenschutzseite. Enthält BEWUSST keine personenbezogenen Detaildaten —
    nur aggregierte Zählungen, Konfigurations- und Audit-Statistiken
    (Konzept datenschutzseite-konzept.md §3.9/§5). Branding via
    reports.pdf.layout (D3).
--}}
@extends('reports.pdf.layout')

@php
    /** @var \Carbon\CarbonInterface $generated_at */
    /** @var \App\Models\Organization $organization */
    /** @var string $operating_mode */
    /** @var int $member_count */
    /** @var int $session_count */
    /** @var int $token_count */
    /** @var int $export_count */
    /** @var string|null $export_last_at */
    /** @var int $support_count */
    /** @var string|null $support_last_at */
    /** @var array<int, array<string, mixed>> $integrations */
    /** @var array<int, array<string, mixed>> $categories */
    /** @var string|null $dpaUrl */
    $modeLabel = match ($operating_mode) {
        'saas' => __('SaaS'),
        'private_cloud' => __('Private Cloud'),
        'on_premise' => __('On-Premise'),
        default => $operating_mode,
    };
    $statusLabel = fn (string $status): string => match ($status) {
        'active' => __('aktiv'),
        'inactive' => __('inaktiv'),
        default => __('nicht konfiguriert'),
    };
    $exportLast = $export_last_at ? \Carbon\CarbonImmutable::parse($export_last_at) : null;
    $supportLast = $support_last_at ? \Carbon\CarbonImmutable::parse($support_last_at) : null;
@endphp

@section('pdf-title', __('Datenschutzbericht') . ' — ' . $organization->name)
@section('pdf-heading', __('Datenschutzbericht'))

@push('pdf-styles')
<style>
    body { line-height: 1.45; }
    h2 { margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
    table { margin-top: 8px; }
    .note { padding: 8px 10px; margin: 10px 0; border: 1px solid #d1d5db; background: #f9fafb; font-size: 10px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Organisation') }}: {{ $organization->name }} ·
    {{ __('Betriebsmodus') }}: {{ $modeLabel }} ·
    {{ __('Stichtag') }}: {{ $generated_at->format('d.m.Y H:i') }}
@endsection

@section('pdf-table')
    <div class="note">
        {{ __('Dieser Bericht enthält keine personenbezogenen Detaildaten — nur aggregierte Zählungen, Konfigurations- und Audit-Statistiken.') }}
    </div>

    <h2>{{ __('Status auf einen Blick') }}</h2>
    <table>
        <tr><th>{{ __('Aktive Nutzer') }}</th><td>{{ $member_count }}</td></tr>
        <tr><th>{{ __('Aktive Sessions') }}</th><td>{{ $session_count }}</td></tr>
        <tr><th>{{ __('API-Tokens') }}</th><td>{{ $token_count }}</td></tr>
        <tr><th>{{ __('AVV/DPA') }}</th><td>{{ $dpaUrl ?: __('nicht hinterlegt') }}</td></tr>
    </table>

    <h2>{{ __('Datenkategorien und Aufbewahrung') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Kategorie') }}</th>
                <th>{{ __('Modelle') }}</th>
                <th>{{ __('Aufbewahrung') }}</th>
                <th>{{ __('Löschpfad') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $cat)
                <tr>
                    <td>{{ $cat['label'] ?? $cat['code'] ?? '—' }}</td>
                    <td>{{ implode(', ', (array) ($cat['models'] ?? [])) }}</td>
                    <td>{{ $cat['retention'] ?? '—' }}</td>
                    <td>{{ $cat['delete_path'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Externe Integrationen und Datenflüsse') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Integration') }}</th>
                <th>{{ __('Quelle') }}</th>
                <th>{{ __('Daten, die abfließen') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($integrations as $integration)
                <tr>
                    <td>
                        {{ $integration['name'] }}@if (($integration['type'] ?? '') === 'plugin') ({{ __('Plugin') }})@endif
                    </td>
                    <td>{{ $integration['source'] }}</td>
                    <td>{{ $integration['data'] }}</td>
                    <td>{{ $statusLabel((string) ($integration['status'] ?? 'not_configured')) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="note">
        {{ __('WorkDiary nutzt keine Tracking-, Analytics- oder Werbe-Dienste.') }}
        {{ __('Es findet keine produktübergreifende Auswertung von Kundendaten statt.') }}
    </div>

    <h2>{{ __('Mandantenexporte') }}</h2>
    <table>
        <tr><th>{{ __('Anzahl Audit-Ereignisse') }}</th><td>{{ $export_count }}</td></tr>
        <tr><th>{{ __('Letztes Ereignis') }}</th><td>{{ $exportLast?->format('d.m.Y H:i') ?? '—' }}</td></tr>
    </table>

    <h2>{{ __('Letzte Supportzugriffe') }}</h2>
    <table>
        <tr><th>{{ __('Anzahl Audit-Ereignisse') }}</th><td>{{ $support_count }}</td></tr>
        <tr><th>{{ __('Letztes Ereignis') }}</th><td>{{ $supportLast?->format('d.m.Y H:i') ?? '—' }}</td></tr>
    </table>

    <h2>{{ __('DSGVO-Aktionen') }}</h2>
    <table>
        <tr><th>{{ __('Auskunft (Art. 15)') }}</th><td>{{ __('Mandantenexport (ZIP) durch den Org-Admin') }}</td></tr>
        <tr><th>{{ __('Berichtigung (Art. 16)') }}</th><td>{{ __('Über die vorhandenen Bearbeitungswege je Datensatz') }}</td></tr>
        <tr><th>{{ __('Löschung (Art. 17)') }}</th><td>{{ __('Löschpfade je Datenkategorie (siehe Tabelle oben)') }}</td></tr>
        <tr><th>{{ __('Einschränkung (Art. 18)') }}</th><td>{{ __('Archivierung je Datensatz') }}</td></tr>
        <tr><th>{{ __('Datenübertragbarkeit (Art. 20)') }}</th><td>{{ __('Mandantenexport (dokumentiertes Format)') }}</td></tr>
        <tr><th>{{ __('Widerspruch (Art. 21)') }}</th><td>{{ __('Über den Org-Admin, dokumentiert im Audit-Protokoll') }}</td></tr>
    </table>

    <p class="footer">
        {{ config('app.name') }} · {{ $organization->name }} · {{ $generated_at->format('d.m.Y H:i') }}
    </p>
@endsection
