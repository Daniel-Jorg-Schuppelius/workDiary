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
@php
    $__themeSchemes = array_column(app(\App\Services\UI\ThemeService::class)->builtinThemes(), 'scheme', 'key');
@endphp
<script @cspNonce>
    (function () {
        // Nur mitgelieferte Themes: eigene Org-Themes haben auf Gast-Seiten kein CSS.
        var schemes = @json($__themeSchemes);
        var savedTheme = null;
        try { savedTheme = localStorage.getItem('workDiaryTheme'); } catch (e) {}
        var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
        var theme = savedTheme && schemes[savedTheme]
            ? savedTheme
            : (prefersLight ? @json(config('theme.auto.light')) : @json(config('theme.auto.dark')));
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = schemes[theme] || 'light';
    })();
</script>
