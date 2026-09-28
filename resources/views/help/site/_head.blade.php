{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _head.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf der statischen Doku-Website (MVP-971): eigenes Stylesheet, kein App-Bundle. --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $pageTitle }} – {{ __(':app-Dokumentation', ['app' => config('app.name', 'WorkDiary')]) }}</title>
<link rel="stylesheet" href="{{ $base }}site.css">
