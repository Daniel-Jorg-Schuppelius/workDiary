<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
     * Selbstkorrektur-Modus fuer vergessene Stempelungen.
     *  - 'request' (Default): Mitarbeiter beantragt → Personalverwaltung genehmigt.
     *  - 'self'             : Eigenkorrekturen werden direkt angewendet (Manual,
     *                         Flag „selbst nachgetragen"), bleiben aber in der
     *                         Korrektur-Inbox sichtbar und revidierbar.
     * Pro Organisation ueberschreibbar via organizations.settings
     * ['attendance']['self_correction'].
     */
    'self_correction' => env('ATTENDANCE_SELF_CORRECTION', 'request'),
];
