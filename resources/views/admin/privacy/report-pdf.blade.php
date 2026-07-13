{{--
    §3.9 Datenschutzbericht (MVP-327): stichtagsbezogener PDF-Bericht der
    Datenschutzseite. Enthält BEWUSST keine personenbezogenen Detaildaten —
    nur aggregierte Zählungen, Konfigurations- und Audit-Statistiken
    (Konzept datenschutzseite-konzept.md §3.9/§5).
--}}
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
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ __('Datenschutzbericht') }} — {{ $organization->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 6px; }
        h2 { font-size: 12pt; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .meta { color: #6b7280; margin-bottom: 14px; }
        .note { padding: 8px 10px; margin: 10px 0; border: 1px solid #d1d5db; background: #f9fafb; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 9pt; vertical-align: top; }
        th { background: #f3f4f6; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>{{ __('Datenschutzbericht') }}</h1>
    <div class="meta">
        {{ __('Organisation') }}: {{ $organization->name }} ·
        {{ __('Betriebsmodus') }}: {{ $modeLabel }} ·
        {{ __('Stichtag') }}: {{ $generated_at->format('d.m.Y H:i') }}
    </div>
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

    <div class="footer">
        {{ config('app.name') }} · {{ $organization->name }} · {{ $generated_at->format('d.m.Y H:i') }}
    </div>
</body>
</html>
