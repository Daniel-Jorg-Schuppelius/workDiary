<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : branding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Defaults für die organisationsweite Personalisierung (Branding).
 * Werden von Organization::brandingSettings() mit den Overrides aus
 * organizations.settings['branding'] rekursiv gemerged.
 */

return [
    // Anzeigename überschreibt config('app.name') in Layouts & PDFs.
    // Leer = Fallback auf config('app.name').
    'app_name' => null,
    'slogan' => null,

    // Kontakt-/Stammdaten der Firma. Genutzt in PDF-Headern/-Footern.
    'contact' => [
        'street' => null,
        'postal_code' => null,
        'city' => null,
        'country' => null,
        'phone' => null,
        'email' => null,
        'web' => null,
    ],

    // Rechnungs-/Steuerangaben für PDF-Footer.
    'legal' => [
        'vat_id' => null,
        'tax_number' => null,
        'iban' => null,
        'bic' => null,
        'register' => null,
        'footer_text' => null,
    ],

    // Primärfarbe als HEX (#rrggbb). Wird im Layout in --color-primary
    // injiziert. Leer = DaisyUI-Theme-Default.
    'colors' => [
        'primary' => null,
        'accent' => null,
    ],

    // PDF-Konfiguration pro Typ. Werte:
    //   logo: 'light'|'dark'|null   – welche Logo-Variante einbetten
    //   show_contact: bool          – Firmen-Stammdaten im Header
    //   show_footer: bool           – Footer-Text + Legal im Footer
    'pdf' => [
        'timesheet' => [
            'logo' => 'light',
            'show_contact' => true,
            'show_footer' => true,
        ],
        'invoice' => [
            'logo' => 'light',
            'show_contact' => true,
            'show_footer' => true,
        ],
        'diary' => [
            'logo' => 'light',
            'show_contact' => false,
            'show_footer' => true,
        ],
        'report' => [
            'logo' => 'light',
            'show_contact' => true,
            'show_footer' => true,
        ],
    ],

    // Upload-Limits in KB.
    'limits' => [
        'logo_kb' => 2048,
        'avatar_kb' => 1024,
    ],
];
