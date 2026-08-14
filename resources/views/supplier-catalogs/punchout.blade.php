{{--
  Created on   : Fri Jul 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : punchout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Aktiver OCI-Punchout-Absprung (Feature 050, MVP-096): Durchgangsseite, die
    die OCI-Setup-Felder per POST an den Lieferanten-Shop absendet. Die
    HOOK_URL ist eine zeitlich begrenzte signierte Rücksprung-URL.
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
    <form id="punchout-form" method="POST" action="{{ $source->punchout_url }}">
        <input type="hidden" name="USERNAME" value="{{ $source->punchout_username }}">
        <input type="hidden" name="PASSWORD" value="{{ $source->punchout_password }}">
        <input type="hidden" name="HOOK_URL" value="{{ $hookUrl }}">
        <input type="hidden" name="OCI_VERSION" value="4.0">
        <input type="hidden" name="RETURNTARGET" value="_top">
        <noscript>
            <button type="submit">{{ __('procurement.oci.punchout.continue') }}</button>
        </noscript>
    </form>
    <script @cspNonce>
        document.getElementById('punchout-form').submit();
    </script>
</body>
</html>
