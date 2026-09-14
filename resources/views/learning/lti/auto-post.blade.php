{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : auto-post.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI 1.3 (Feature 149): schickt ein Formular selbst ab — das ID-Token an das Tool
  oder die Authentifizierungsanfrage einmal same-site an sich selbst. Ohne App-Layout,
  die Antwort bringt ihre eigene CSP mit. Variablen: $action, $fields, $nonce
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>{{ __('learning.lti.redirecting') }}</title>
</head>
<body>
    <form id="lti-auto-post" method="POST" action="{{ $action }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <p>{{ __('learning.lti.redirecting') }}</p>
        <noscript><button type="submit">{{ __('learning.lti.continue') }}</button></noscript>
    </form>
    <script nonce="{{ $nonce }}">document.getElementById('lti-auto-post').submit();</script>
</body>
</html>
