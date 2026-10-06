{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : public-page.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'title',
    'main' => 'mx-auto max-w-3xl p-4',
    'body' => 'min-h-screen bg-base-200',
    'assets' => ['resources/css/app.css', 'resources/js/app.js'],
    'icons' => false,
    'referrer' => null,
])

{{--
    <x-public-page> — Dokumentrahmen der öffentlichen Token-Seiten (Signatur,
    Umfrage, Prüfauftrag, Statusseite …): ein Kopf statt einer Kopie je Seite
    (Konsolidierungs-Audit 2026-10, k4-06).

    Jede Seite trägt die Sprache der Anfrage und `noindex` — die Adresse
    enthält das Zugangstoken und gehört in keinen Suchindex.

    Props:
      - title  : Seitentitel
      - main   : Klassen des Inhaltsrahmens (Breite, Abstände)
      - body   : Klassen des <body>
      - assets : Vite-Einträge (z. B. zusätzlich resources/js/signature.js)
      - icons  : Icon-Schrift vorladen, wenn die Seite Icons rendert
      - referrer : strengere Referrer-Policy als die der Anwendung (z. B. no-referrer)
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
@if ($referrer !== null)
<meta name="referrer" content="{{ $referrer }}">
@endif
<title>{{ $title }}</title>
@include('partials.font-bootstrap', ['icons' => (bool) $icons])
@vite($assets)
</head>
<body class="{{ $body }}">
<main class="{{ $main }}">
{{ $slot }}
</main>
@stack('scripts')
</body>
</html>
