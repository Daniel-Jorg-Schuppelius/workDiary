{{-- Druck-Handler für Standalone-Druckseiten (kein app.js geladen):
     bindet [data-print]-Buttons per Delegation an window.print().
     Nonce-fähig — Inline-onclick-Attribute wären unter CSP Stufe 1 blockiert. --}}
<script @cspNonce>
    document.addEventListener('click', function (event) {
        if (event.target instanceof Element && event.target.closest('[data-print]')) {
            window.print();
        }
    });
</script>
