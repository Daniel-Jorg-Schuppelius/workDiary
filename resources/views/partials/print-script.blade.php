{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : print-script.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
