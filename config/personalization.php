<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : personalization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Defaults für persönliche Benutzerpräferenzen. Werden von
 * User::preferences() mit dem JSON aus users.preferences gemerged.
 */

return [
    // Erlaubte DaisyUI-Themes für den Theme-Picker. 'auto' => System-Default.
    'themes' => ['auto', 'corporate', 'dim', 'dark', 'light', 'business', 'wireframe'],

    'defaults' => [
        // null = an Organisations-/App-Locale ausrichten
        'theme' => 'auto',
        'locale' => null,
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
        // Routen-Name oder null = an Mode-Default (Home-Controller)
        'startpage' => null,
        // Bevorzugter Arbeitsmodus (legacy|new). Greift als Default, wenn die
        // Session keinen work_mode trägt (frische Session/F5). Wird über die
        // tatsächlichen Zugriffsrechte normalisiert (User::preferredWorkMode()).
        'work_mode' => 'legacy',
    ],

    // Organisations-/App-weite Standardformate. Org-überschreibbar via
    // Setting::get('personalization.date_format') (organization.settings →
    // dieser Default). User-Preferences (defaults oben) überschreiben pro Person.
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',

    // Kuratierte, flatpickr-kompatible Auswahl. Die Tokens d/m/Y/H/i/F/j/M
    // decken sich zwischen PHP und flatpickr, sodass dasselbe Format serverseitig
    // (->translatedFormat) und im Datepicker (altFormat) gilt.
    'date_formats' => ['d.m.Y', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd. F Y', 'j. M Y'],
    'time_formats' => ['H:i', 'h:i A'],

    // Whitelist für die Auswahl auf der Profilseite.
    'startpages' => [
        'dashboard',
        'diary.index',
        'week.index',
        'kanban.index',
        'duties.index',
    ],
];
