{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : theme-bootstrap.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Anti-Flash-Theme-Bootstrap (Vollaudit 2026-07, M51) — EINZIGE Quelle des
    früher 17-fach kopierten Inline-Skripts. Setzt vor dem ersten Paint das
    gespeicherte bzw. per prefers-color-scheme abgeleitete Theme. Nur das
    initiale Theme — den Umschalter macht zentral resources/js/layout.js
    (ein zweiter Click-Handler würde doppelt schalten).
--}}
<script @cspNonce>
    (function () {
        var savedTheme = localStorage.getItem('workDiaryTheme');
        var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
        var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
    })();
</script>
