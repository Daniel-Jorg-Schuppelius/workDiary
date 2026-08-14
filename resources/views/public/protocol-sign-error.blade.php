{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : protocol-sign-error.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="de" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Signaturlink ungültig') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-xl p-4">
    <div class="alert alert-error">
        <span>{{ $message ?? __('protocol.signature.tokenExpired') }}</span>
    </div>
</main>
</body>
</html>
