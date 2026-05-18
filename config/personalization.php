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
    ],

    // Whitelist für die Auswahl auf der Profilseite.
    'startpages' => [
        'dashboard',
        'diary.index',
        'week.index',
        'kanban.index',
        'duties.index',
    ],
];
