{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : label.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
{{-- Drucketikett (Feature 048, E5). $label = {code, code_type, title, subtitle, lines} --}}
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 4mm; }
    body { font-family: DejaVu Sans, sans-serif; }
    .title { font-size: 11pt; font-weight: bold; }
    .subtitle { font-size: 8pt; color: #444; }
    .code { font-family: DejaVu Sans Mono, monospace; font-size: 18pt; letter-spacing: 1px; margin-top: 4mm; }
    .type { font-size: 7pt; color: #666; text-transform: uppercase; }
    .lines { font-size: 8pt; color: #333; margin-top: 2mm; }
    .qr { float: right; width: 22mm; height: 22mm; }
</style>
</head>
<body>
    @php $fields = $fields ?? ['title', 'subtitle', 'code', 'code_type', 'lines']; @endphp
    @if (! empty($qr))
        <img class="qr" src="{{ $qr }}" alt="QR">
    @endif
    @if (in_array('title', $fields, true))
        <div class="title">{{ $label['title'] }}</div>
    @endif
    @if (in_array('subtitle', $fields, true) && ! empty($label['subtitle']))
        <div class="subtitle">{{ $label['subtitle'] }}</div>
    @endif
    @if (in_array('code', $fields, true))
        <div class="code">{{ $label['code'] }}</div>
    @endif
    @if (in_array('code_type', $fields, true))
        <div class="type">{{ $label['code_type'] }}</div>
    @endif
    @if (in_array('lines', $fields, true))
        <div class="lines">
            @foreach ($label['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
