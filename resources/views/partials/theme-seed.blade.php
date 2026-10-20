{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : theme-seed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Theme-Seed + Anti-Flash für angemeldete Layouts (App und Kundenportal,
    MVP-1241) — EINZIGE Quelle, Gegenstück zu partials/theme-bootstrap für
    Gastseiten ohne Konto. Erwartet `$theme` (ThemeService, View-Composer).
    Läuft VOR jeglichem CSS, damit data-theme synchron beim ersten Paint passt;
    den Umschalter macht zentral resources/js/layout.js.
--}}
<style>
    /* Frühe Basis vor dem CSS-Bundle, damit der erste Paint nicht
       als unstyled white-on-Times-Roman sichtbar wird, bevor Vite
       das CSS injiziert hat (FOUC im Dev-Modus). */
    html { color-scheme: light dark; background: Canvas; color: CanvasText; }
    html[data-theme="dim"] { color-scheme: dark; background: #1d232a; color: #e7e9ea; }
    html[data-theme="corporate-dark"] { color-scheme: dark; background: #1d232a; color: #e7e9ea; }
    html[data-theme="corporate"] { color-scheme: light; background: #ffffff; color: #1f2937; }
    body { font-family: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
</style>
{{-- Theme-Seed (User-/Org-Auflösung) + Custom-Theme-Definitionen der
     Organisation. Beides MUSS vor dem Anti-Flash-Skript und vor dem
     CSS-Bundle stehen, damit der erste Paint das richtige (auch ein
     org-eigenes) Theme trägt. Die Farbwerte sind durch ThemeDefinition
     sanitisiert (nur HEX/erlaubte Tokens) → kein CSS-Injection-Risiko. --}}
@if (isset($theme) && $theme)
    <script @cspNonce>window.__theme = @json($theme->seed());</script>
    @php $__customThemesCss = $theme->customThemesCss(); @endphp
    @if ($__customThemesCss !== '')
        <style @cspNonce>{!! $__customThemesCss !!}</style>
    @endif
@endif
<script @cspNonce>
    (function () {
        var root = document.documentElement;
        var seed = window.__theme || {};
        var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
        var choice;
        if (seed.authenticated) {
            // Eingeloggt: ALLEIN die serverseitig aufgelöste Wahl zählt
            // (persönliches Theme aus der DB, sonst 'auto' → Org-Hell/Dunkel-
            // Paar). KEIN localStorage-Override — so kann kein veralteter
            // lokaler Zustand den Org-Standard überstimmen. Der Header-
            // Umschalter persistiert seine Wahl serverseitig
            // (account.theme.update) und kommt damit beim nächsten Render
            // bereits über seed.active zurück.
            choice = seed.active || 'auto';
        } else {
            // Gast (z. B. Login): localStorage führt, gegen die Allowlist
            // geprüft, sonst Server-Default bzw. 'auto'.
            var saved = null;
            try { saved = localStorage.getItem('workDiaryTheme'); } catch (e) {}
            if (saved && seed.allowed && seed.allowed.indexOf(saved) === -1) saved = null;
            choice = saved || seed.active || 'auto';
        }
        var theme = choice;
        if (choice === 'auto') {
            theme = prefersLight ? (seed.autoLight || 'corporate') : (seed.autoDark || 'corporate-dark');
        }
        var scheme = (seed.schemes && seed.schemes[theme])
            || (theme === 'corporate-dark' || theme === 'dim' || theme === 'dark' || theme === 'business' ? 'dark' : 'light');
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = scheme;
        root.setAttribute('data-scheme', scheme);
    })();
</script>
