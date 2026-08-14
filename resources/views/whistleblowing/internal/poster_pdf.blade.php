{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : poster_pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
{{-- Druckfertiger Aushang (A4) für das Hinweisgeber-Meldeportal. --}}
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 20mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; text-align: center; }
    .org { font-size: 14pt; color: #444; margin-top: 8mm; }
    h1 { font-size: 26pt; margin: 4mm 0 2mm; }
    .sub { font-size: 12pt; color: #444; }
    .text { font-size: 11pt; margin: 8mm 15mm; line-height: 1.5; }
    .qr { width: 70mm; height: 70mm; margin: 6mm auto 4mm; }
    .link { font-family: DejaVu Sans Mono, monospace; font-size: 12pt; }
    .footer { font-size: 9pt; color: #666; margin-top: 12mm; }
</style>
</head>
<body>
    @if (! empty($organizationName))
        <div class="org">{{ $organizationName }}</div>
    @endif
    <h1>{{ __('Hinweisgeber-Meldestelle') }}</h1>
    <div class="sub">{{ __('Vertraulicher Meldekanal nach dem Hinweisgeberschutzgesetz (HinSchG)') }}</div>
    <p class="text">{{ __('Sie haben Hinweise auf Rechtsverstöße, Korruption, Betrug oder andere erhebliche Compliance-Verstöße? Über unser Meldeportal melden Sie diese vertraulich und auf Wunsch anonym – rund um die Uhr, ohne Anmeldung.') }}</p>
    <img class="qr" src="{{ $qr }}" alt="{{ __('QR-Code zum Meldeportal') }}">
    <div class="link">{{ $link }}</div>
    <p class="footer">{{ __('Das Portal speichert keine IP-Adresse zur Meldung. Maßgeblich ist der jeweils aktuell veröffentlichte Zugangslink.') }}</p>
</body>
</html>
