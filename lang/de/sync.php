<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Offline-Synchronisierung (Feature 004). Ablehnungsgründe für Befehle aus
 * der Offline-Warteschlange.
 */

return [
    'error' => [
        'stamp_in_future' => 'Der Zeitstempel liegt in der Zukunft.',
        'stamp_too_old' => 'Der Zeitstempel liegt mehr als :days Tage zurück und wird nicht mehr übernommen.',
        'day_locked' => 'Der Tag ist abgeschlossen oder der Monat freigegeben — bitte eine Zeitkorrektur beantragen.',
        'stamp_overlaps' => 'Für diesen Zeitpunkt gibt es bereits eine Stempelung.',
    ],
];
