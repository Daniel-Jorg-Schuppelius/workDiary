{{--
    Einheitliches Report-PDF-Layout (D3): Branding-Kopf/-Fuß aus $branding
    (View-Composer, AppServiceProvider) + gemeinsamer Styleblock (Superset
    der früheren Standalone-Templates). Sections: pdf-title, pdf-heading,
    pdf-meta (optional, Fallback: Zeitraum aus $label), pdf-table.
    Template-Spezifisches via @push('pdf-styles').
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('pdf-title')</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 12px 0 4px; }
        .meta { margin: 0 0 12px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .num, .right { text-align: right; }
        /* KPI-Kacheln */
        .kpis { margin-bottom: 10px; }
        .kpis td { border: 1px solid #ccc; background: #f7f7f7; }
        .kpis .label { font-size: 9px; color: #666; }
        .kpis .value { font-size: 13px; font-weight: bold; }
        /* Datentabellen mit reiner Linien-Optik (statt Vollraster) */
        table.data th, table.data td { border: 0; border-bottom: 1px solid #ccc; }
        table.data th { background: #f3f3f3; }
        /* Summenzeilen */
        .totals td, table.data tfoot td { border-top: 2px solid #333; font-weight: bold; }
        .neg { color: #b30000; font-weight: bold; }
        .pos { color: #1b7c2c; }
        .small { font-size: 9px; color: #666; }
        .header { width: 100%; margin-bottom: 10px; }
        .header td { border: 0; padding: 0; vertical-align: top; }
        .brand { font-size: 18px; font-weight: 700; margin: 0; }
        .logo { max-height: 40px; max-width: 160px; }
        .footer { margin-top: 12px; color: #6b7280; font-size: 10px; }
        .contact { font-size: 10px; color: #4b5563; }
    </style>
    @stack('pdf-styles')
</head>
<body>
    @php
        $pdfCfg = isset($branding) && $branding ? $branding->pdfConfig('report') : [
            'logo' => 'light',
            'show_contact' => true,
            'show_footer' => true,
            'logo_data_uri' => null,
        ];
        $appName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
        $contact = isset($branding) && $branding ? $branding->contact() : [];
        $legal = isset($branding) && $branding ? $branding->legal() : [];
    @endphp

    <table class="header">
        <tr>
            <td>
                <p class="brand">{{ $appName }}</p>
                @if (($pdfCfg['show_contact'] ?? true) && $contact !== [])
                    <div class="contact">
                        @if (!empty($contact['street'])){{ $contact['street'] }}<br>@endif
                        @if (!empty($contact['postal_code']) || !empty($contact['city'])){{ trim(($contact['postal_code'] ?? '').' '.($contact['city'] ?? '')) }}<br>@endif
                        @if (!empty($contact['phone']))Tel: {{ $contact['phone'] }}<br>@endif
                        @if (!empty($contact['email'])){{ $contact['email'] }}@endif
                    </div>
                @endif
            </td>
            <td style="text-align: right;">
                @if (($pdfCfg['logo'] ?? null) !== null && !empty($pdfCfg['logo_data_uri']))
                    <img class="logo" src="{{ $pdfCfg['logo_data_uri'] }}" alt="logo">
                @endif
            </td>
        </tr>
    </table>

    <h1>@yield('pdf-heading')</h1>

    @hasSection('pdf-meta')
        <p class="meta">@yield('pdf-meta')</p>
    @elseif (isset($label))
        <p class="meta">Zeitraum: {{ $label }}</p>
    @endif

    @yield('pdf-table')

    @if (($pdfCfg['show_footer'] ?? true))
        <p class="footer">
            @if (!empty($legal['footer_text'])){{ $legal['footer_text'] }} · @endif
            Generiert von {{ $appName }} am {{ now()->format('Y-m-d H:i') }}
        </p>
    @endif
</body>
</html>
