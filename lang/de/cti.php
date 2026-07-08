<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Telefonie / CTI',
    'intro' => 'Eingehende Anrufe bekannter Kunden werden als Kommunikationseintrag protokolliert (nur Metadaten: Richtung, Nummer, Zeitpunkt, Dauer — nie Gesprächsinhalte). Der Provider (sipgate u. a.) meldet Anrufe an die unten erzeugte Webhook-URL. WorkDiary ist keine Telefonanlage.',

    'note' => [
        'subject_inbound' => 'Eingehender Anruf von :number',
        'subject_outbound' => 'Ausgehender Anruf an :number',
    ],

    // Anrufer-Pop-up (MVP-118, Rang 9) — In-App-Benachrichtigung an den
    // Mitarbeiter mit passender Opt-in-Durchwahl.
    'popup' => [
        'title_customer' => 'Anruf von :name',
        'title_unknown' => 'Anruf von :number',
        'message' => 'Eingehender Anruf (:number).',
        'unknown_number' => 'unbekannte Nummer',
    ],

    'profile' => [
        'heading' => 'Anrufer-Pop-up',
        'extension_label' => 'Eigene Durchwahl',
        'extension_help' => 'Bei einem eingehenden Anruf auf diese Nummer erhältst du ein Pop-up mit dem Anrufer und – falls bekannt – einen Link zur Kundenakte. Leer lassen = kein Pop-up.',
        'extension_placeholder' => 'z. B. +49 30 1234-56',
        'invalid' => 'Bitte eine gültige Telefonnummer angeben.',
    ],

    'new_heading' => 'Neue Webhook-URL',
    'new_hint' => 'Jetzt in die Telefonanlage/den Provider eintragen — der Token wird nur dieses eine Mal angezeigt.',

    'issue_heading' => 'Anbindung ausstellen',
    'connections_heading' => 'Anbindungen',
    'no_connections' => 'Noch keine Anbindung ausgestellt.',

    'field' => [
        'name' => 'Bezeichnung',
        'name_placeholder' => 'z. B. Zentrale sipgate',
        'provider' => 'Anbieter',
    ],

    'action' => [
        'issue' => 'Ausstellen',
        'disconnect' => 'Deaktivieren',
    ],

    'col' => [
        'status' => 'Status',
        'last_event' => 'Letztes Ereignis',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'flash' => [
        'issued' => 'CTI-Anbindung ausgestellt.',
        'disconnected' => 'CTI-Anbindung deaktiviert.',
    ],
];
