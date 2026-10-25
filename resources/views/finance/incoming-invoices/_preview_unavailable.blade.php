{{--
  Created on   : Sun Oct 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview_unavailable.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
{{-- Inhalt des Vorschau-iframes, wenn ein TIFF-Scan nicht umgewandelt werden kann (eigene Sandbox-CSP, kein Layout). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ __('Keine Vorschau möglich') }}</title>
<style>
    body { margin: 0; padding: 1.5rem; font: 14px/1.5 system-ui, sans-serif; color: #444; background: #fff; }
    h1 { margin: 0 0 0.5rem; font-size: 15px; }
</style>
</head>
<body>
    <h1>{{ __('Keine Vorschau möglich') }}</h1>
    <p>{{ __('Das Original lässt sich hier nicht anzeigen. Laden Sie es über „Original (XML/PDF)“ herunter.') }}</p>
</body>
</html>
