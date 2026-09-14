{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : error.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI 1.3 (Feature 149): abgelehnte Authentifizierungsanfrage. Kein ID-Token, kein
  Weiterleiten — nur der maschinelle Grund für den Support. Variable: $reason
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>{{ __('learning.errors.lti.title') }}</title>
</head>
<body>
    <h1>{{ __('learning.errors.lti.title') }}</h1>
    <p>{{ __('learning.errors.lti.failed', ['reason' => $reason]) }}</p>
</body>
</html>
