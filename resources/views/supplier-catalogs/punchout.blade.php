{{--
  Created on   : Fri Jul 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : punchout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Absprung in den Lieferanten-Shop (MVP-096 OCI, MVP-1071 IDS-Connect):
    Durchgangsseite, die die Setup-Felder per POST an den Shop absendet. Felder
    und Kodierung kommen aus dem Controller.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>{{ __('procurement.oci.punchout.title') }}</title>
</head>
<body>
    <p>{{ __('procurement.oci.punchout.redirecting', ['shop' => $source->name]) }}</p>
    <form id="punchout-form" method="POST" action="{{ $source->punchout_url }}" @if ($multipart) enctype="multipart/form-data" @endif>
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <noscript>
            <button type="submit">{{ __('procurement.oci.punchout.continue') }}</button>
        </noscript>
    </form>
    <script @cspNonce>
        document.getElementById('punchout-form').submit();
    </script>
</body>
</html>
