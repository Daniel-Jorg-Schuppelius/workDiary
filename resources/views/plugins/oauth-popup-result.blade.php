{{--
    Popup-Abschlussseite des OAuth-Connect-Flows: meldet das Ergebnis an das
    Opener-Fenster (postMessage, streng origin-geprüft) und schließt sich selbst.
    Der eigentliche Erfolg/Fehler steckt als Flash in der Session — das
    Opener-Fenster lädt beim Empfang die Übersicht neu und zeigt ihn dort an.
    Bewusst ohne App-Layout/Vite: eine minimale, eigenständige HTML-Seite.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Anmeldung') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; padding: 1.5rem; color: #1f2937; background: #f8fafc; }
        p { font-size: 0.95rem; line-height: 1.5; }
    </style>
</head>
<body>
    <p>{{ $success ? __('Verbindung hergestellt. Dieses Fenster schließt sich automatisch.') : __('Vorgang beendet. Dieses Fenster schließt sich automatisch.') }}</p>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (function () {
            var origin = {!! json_encode(request()->getSchemeAndHttpHost(), JSON_UNESCAPED_SLASHES) !!};
            var payload = { source: "workdiary-oauth", status: {!! json_encode($success ? 'success' : 'error') !!} };
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, origin);
                }
            } catch (e) { /* Opener nicht erreichbar — Fenster schließt sich trotzdem. */ }
            window.setTimeout(function () { window.close(); }, 150);
        })();
    </script>
</body>
</html>
