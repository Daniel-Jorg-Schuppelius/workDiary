{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Fallakte-PDF für den Kunden (Rang 54): strikt kundensichtbarer Schnitt —
  identische Datenquelle wie das Portal-Detail (DiaryDetailController).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $diary->title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 4px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 3px 6px; border-bottom: 1px solid #ddd; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>{{ $diary->title }}</h1>
    <p class="muted">{{ optional($diary->start_at)->fdate() }} · {{ $diary->status }}</p>

    <h2>{{ __('Fotos') }}</h2>
    @if ($photos->isEmpty())
        <p class="muted">{{ __('Keine freigegebenen Fotos.') }}</p>
    @else
        <table>
            <tr><th>{{ __('Datei') }}</th><th>{{ __('Bestätigt') }}</th></tr>
            @foreach ($photos as $photo)
                <tr>
                    <td>{{ $photo->original_name }}</td>
                    <td>
                        @php $confirmation = $photo->confirmations->first(); @endphp
                        {{ $confirmation !== null ? $confirmation->confirmed_at->fdate() : '—' }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('Material') }}</h2>
    @if ($materials->isEmpty())
        <p class="muted">{{ __('Kein Material erfasst.') }}</p>
    @else
        <table>
            <tr><th>{{ __('Bezeichnung') }}</th><th>{{ __('Menge') }}</th></tr>
            @foreach ($materials as $usage)
                <tr>
                    <td>{{ $usage->description }}</td>
                    <td>{{ rtrim(rtrim((string) $usage->quantity, '0'), '.') }} {{ $usage->unit }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('Protokolle') }}</h2>
    @if ($protocols->isEmpty())
        <p class="muted">{{ __('Keine freigegebenen Protokolle.') }}</p>
    @else
        <table>
            <tr><th>{{ __('Titel') }}</th><th>{{ __('Status') }}</th><th>{{ __('Datum') }}</th></tr>
            @foreach ($protocols as $protocol)
                <tr>
                    <td>{{ $protocol->title }}</td>
                    <td>{{ $protocol->status->label() }}</td>
                    <td>{{ optional($protocol->occurred_at)->fdate() }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
