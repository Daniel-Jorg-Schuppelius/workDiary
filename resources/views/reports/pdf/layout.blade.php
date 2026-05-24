<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('pdf-title')</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin: 0 0 12px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
        .header { width: 100%; margin-bottom: 10px; }
        .header td { border: 0; padding: 0; vertical-align: top; }
        .brand { font-size: 18px; font-weight: 700; margin: 0; }
        .logo { max-height: 40px; max-width: 160px; }
        .footer { margin-top: 12px; color: #6b7280; font-size: 10px; }
        .contact { font-size: 10px; color: #4b5563; }
    </style>
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
    <p class="meta">Zeitraum: {{ $label }}</p>

    @yield('pdf-table')

    @if (($pdfCfg['show_footer'] ?? true))
        <p class="footer">
            @if (!empty($legal['footer_text'])){{ $legal['footer_text'] }} · @endif
            Generiert von {{ $appName }} am {{ now()->format('Y-m-d H:i') }}
        </p>
    @endif
</body>
</html>
