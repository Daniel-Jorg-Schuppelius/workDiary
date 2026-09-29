{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : font-bootstrap.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Schriften vor dem ersten Paint, einzige Quelle für jedes Dokument mit
    App-Bundle. Die Preloads starten den Download parallel zum CSS. Bis die
    Icon-Schrift geladen ist, bleiben Material-Symbols-Ligaturen in einer
    1em-Box unsichtbar: Auch verkleinert ist die Datei ~0,8 MB groß, bei
    schwacher Leitung endet die Blockphase von font-display: block vorher,
    und „login"/„light_mode" stünden als Text im Layout.

    Das Skript lädt die Icon-Schrift selbst per FontFace (gleiche URL wie der
    Preload): document.fonts.ready löst hier im <head> sofort auf, weil das
    Stylesheet mit dem @font-face noch nicht geparst ist.

    Ohne DB-/Session-Zugriff (errors/_page im safe-Modus).

    Parameter:
      icons  false = Icon-Schrift nicht vorladen, für Seiten ohne Icons
             (ein Preload lädt die Datei auch ungenutzt)
--}}
@php
    $iconFontKey = 'resources/fonts/material-symbols-outlined.woff2';
    $fontKeys = [
        'node_modules/@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-400-normal.woff2',
        'node_modules/@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-600-normal.woff2',
        'node_modules/@fontsource/space-grotesk/files/space-grotesk-latin-700-normal.woff2',
    ];
    if ($icons ?? true) {
        $fontKeys[] = $iconFontKey;
    }
    $fontUrls = [];
    $hotFile = public_path('hot');
    $manifestFile = public_path('build/manifest.json');
    if (is_file($hotFile)) {
        $devUrl = rtrim((string) @file_get_contents($hotFile), "\n\r ");
        foreach ($fontKeys as $key) {
            $fontUrls[$key] = $devUrl . '/' . $key;
        }
    } elseif (is_file($manifestFile)) {
        $manifest = json_decode((string) @file_get_contents($manifestFile), true) ?: [];
        foreach ($fontKeys as $key) {
            if (isset($manifest[$key]['file'])) {
                $fontUrls[$key] = asset('build/' . $manifest[$key]['file']);
            }
        }
    }
    $iconFontUrl = $fontUrls[$iconFontKey] ?? null;
@endphp
@foreach ($fontUrls as $href)
    <link rel="preload" as="font" type="font/woff2" href="{{ $href }}" crossorigin>
@endforeach
<style>
    .material-symbols-outlined {
        visibility: hidden;
        display: inline-block;
        width: 1em;
        height: 1em;
        line-height: 1;
        overflow: hidden;
        vertical-align: middle;
    }
    html.fonts-loaded .material-symbols-outlined {
        visibility: visible;
        width: auto;
        height: auto;
        overflow: visible;
    }
</style>
<script @cspNonce>
    (function () {
        var root = document.documentElement;
        var reveal = function () {
            root.classList.add('fonts-loaded');
        };
        var url = @json($iconFontUrl);
        // Ohne URL oder Font-Loading-API blieben die Icons sonst dauerhaft unsichtbar.
        if (!url || !window.FontFace || !document.fonts) {
            reveal();
            return;
        }
        var face = new FontFace('Material Symbols Outlined', 'url(' + JSON.stringify(url) + ') format("woff2")', {
            weight: '100 700',
            display: 'block',
        });
        document.fonts.add(face);
        face.load().then(reveal, reveal);
    })();
</script>
