<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : theme.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Theming-Defaults. Werden von Organization::themeSettings() mit den
 * Overrides aus organizations.settings['theme'] gemerged.
 *
 *   - `builtin`  : kuratierte DaisyUI-Built-in-Themes (build-time in
 *                  resources/css/app.css über die @plugin-Liste aktiviert).
 *                  key = data-theme-Wert, scheme = light|dark (für color-scheme
 *                  + die auto-Verzweigung), label = Anzeigename im Picker.
 *                  WICHTIG: Diese Liste MUSS mit der @plugin-themes-Liste in
 *                  resources/css/app.css synchron bleiben — sonst validiert ein
 *                  Theme grün, existiert aber nicht im CSS-Build.
 *   - `auto`     : welches Built-in 'auto' bei hellem/dunklem System wählt.
 *   - `geometry` : Default-Geometrie für Custom-Themes (Custom-Themes ohne
 *                  eigene Geometrie erben diese Werte).
 *
 * Custom-Themes der Organisation liegen NICHT hier, sondern in
 * organizations.settings['theme']['custom'][] (Liste von Theme-Definitionen);
 * der Org-Default in settings['theme']['default']. Es dürfen hier KEINE
 * `custom`-Defaults stehen (array_replace_recursive würde Listen index-weise
 * mergen statt ersetzen).
 */

return [
    'builtin' => [
        'corporate' => ['label' => 'Corporate', 'scheme' => 'light'],
        'dim' => ['label' => 'Dim', 'scheme' => 'dark'],
        'business' => ['label' => 'Business', 'scheme' => 'dark'],
        'emerald' => ['label' => 'Emerald', 'scheme' => 'light'],
        'nord' => ['label' => 'Nord', 'scheme' => 'light'],
        'light' => ['label' => 'Light', 'scheme' => 'light'],
        'dark' => ['label' => 'Dark', 'scheme' => 'dark'],
        'wireframe' => ['label' => 'Wireframe', 'scheme' => 'light'],
    ],

    // 'auto' folgt prefers-color-scheme und wählt eines dieser Built-ins.
    'auto' => [
        'light' => 'corporate',
        'dark' => 'dim',
    ],

    // Default-Geometrie für Custom-Themes (DaisyUI-v5-Defaults).
    'geometry' => [
        'radius-box' => '1rem',
        'radius-field' => '0.5rem',
        'radius-selector' => '0.25rem',
        'border' => '1px',
    ],

    // Obergrenze an Custom-Themes je Organisation (UI-/Payload-Schutz).
    'max_custom' => 12,
];
