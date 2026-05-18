{{--
    Gemeinsames PDF-Grundgerüst.
    Erwartet:
      - $branding (per View-Composer, kann null sein)
      - $pdfType  (z. B. 'timesheet', 'invoice', 'diary')
      - $pdfTitle (optionaler Dokumenttitel im <title>)
    Slots:
      - $slot  Hauptinhalt
      - $documentMeta  (optional) ergänzende Metazeile direkt unter Header
--}}
@php
    /** @var \App\Services\BrandingService|null $branding */
    $pdfType = $pdfType ?? 'default';
    $pdfTitle = $pdfTitle ?? null;
    $pdfCfg = $branding ? $branding->pdfConfig($pdfType) : [
        'logo' => 'light',
        'show_contact' => true,
        'show_footer' => true,
        'logo_data_uri' => null,
        'logo_url' => null,
    ];
    $contact = $branding ? $branding->contact() : [];
    $legal = $branding ? $branding->legal() : [];
    $appName = $branding ? $branding->appName() : config('app.name', 'WorkDiary');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $pdfTitle ?? $appName }}</title>
    <style>
        @page { margin: 22mm 16mm 22mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 4pt 0; }
        h2 { font-size: 12pt; margin: 14pt 0 4pt 0; border-bottom: 1px solid #999; padding-bottom: 2pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ccc; padding: 4pt 6pt; vertical-align: top; }
        th { background: #f3f3f3; text-align: left; }
        .right { text-align: right; }
        .meta  { font-size: 9pt; color: #555; margin-top: 2pt; }
        .totals td { border: 0; padding: 2pt 6pt; }
        .small { font-size: 8pt; color: #666; }

        /* Branding-Header */
        .pdf-header { width: 100%; border-bottom: 1px solid #ccc; padding-bottom: 6pt; margin-bottom: 10pt; }
        .pdf-header td { border: 0; padding: 0; vertical-align: top; }
        .pdf-header .logo-cell { width: 35%; }
        .pdf-header .logo-cell img { max-height: 60pt; max-width: 100%; }
        .pdf-header .contact-cell { text-align: right; font-size: 9pt; color: #444; line-height: 1.4; }
        .pdf-header .app-name { font-weight: bold; font-size: 11pt; color: #111; }

        /* Branding-Footer (manuell unter Inhalt – DomPDF-Pseudo-Footer
           wird hier nicht genutzt, weil verschiedene PDFs unterschiedliche
           Mengen brauchen; einfacher: nach $slot rendern). */
        .pdf-footer { margin-top: 18pt; padding-top: 6pt; border-top: 1px solid #ccc;
                      font-size: 8pt; color: #555; line-height: 1.45; text-align: center; }
        .pdf-footer .legal-line { margin-top: 2pt; }
    </style>
    @stack('pdf-styles')
</head>
<body>

@php
    $showHeader = ! empty($pdfCfg['logo_data_uri']) || ($pdfCfg['show_contact'] ?? true);
@endphp
@if ($showHeader)
    <table class="pdf-header">
        <tr>
            <td class="logo-cell">
                @if (! empty($pdfCfg['logo_data_uri']))
                    <img src="{{ $pdfCfg['logo_data_uri'] }}" alt="{{ $appName }}">
                @else
                    <span class="app-name">{{ $appName }}</span>
                @endif
            </td>
            <td class="contact-cell">
                @if ($pdfCfg['show_contact'] ?? true)
                    <div class="app-name">{{ $appName }}</div>
                    @if (! empty($contact['street'])){{ $contact['street'] }}<br>@endif
                    @if (! empty($contact['postal_code']) || ! empty($contact['city']))
                        {{ trim(($contact['postal_code'] ?? '').' '.($contact['city'] ?? '')) }}<br>
                    @endif
                    @if (! empty($contact['country'])){{ $contact['country'] }}<br>@endif
                    @if (! empty($contact['phone'])){{ __('Tel.') }}: {{ $contact['phone'] }}<br>@endif
                    @if (! empty($contact['email'])){{ $contact['email'] }}<br>@endif
                    @if (! empty($contact['web'])){{ $contact['web'] }}@endif
                @endif
            </td>
        </tr>
    </table>
@endif

{{ $slot }}

@if (($pdfCfg['show_footer'] ?? true) && ($legal !== [] || ! empty($pdfCfg['footer_text'] ?? null)))
    <div class="pdf-footer">
        @if (! empty($legal['footer_text']))
            <div>{!! nl2br(e($legal['footer_text'])) !!}</div>
        @endif
        <div class="legal-line">
            @if (! empty($legal['register'])){{ $legal['register'] }} · @endif
            @if (! empty($legal['vat_id'])){{ __('USt-IdNr.') }}: {{ $legal['vat_id'] }} · @endif
            @if (! empty($legal['tax_number'])){{ __('Steuernr.') }}: {{ $legal['tax_number'] }} · @endif
            @if (! empty($legal['iban'])){{ __('IBAN') }}: {{ $legal['iban'] }}@endif
            @if (! empty($legal['bic'])) · {{ __('BIC') }}: {{ $legal['bic'] }}@endif
        </div>
    </div>
@endif

</body>
</html>
