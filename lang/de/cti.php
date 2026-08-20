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
        'dial_saved' => 'Click-to-Dial gespeichert.',
        'issued' => 'CTI-Anbindung ausgestellt.',
        'disconnected' => 'CTI-Anbindung deaktiviert.',
    ],

    'dial' => [
        'action' => 'Anrufen',
        'confirm' => 'Anruf an :number starten? Die Telefonanlage ruft zuerst Ihre Durchwahl an.',
        'started' => 'Anruf an :number wird aufgebaut — bitte Durchwahl abheben.',
        'no_connection' => 'Keine wählfähige Telefonanbindung eingerichtet (Click-to-Dial in den CTI-Einstellungen aktivieren).',
        'not_configured' => 'Für diese Anbindung fehlen API-Zugang oder eigene Durchwahl.',
        'no_base_url' => 'Für diese Anlage ist keine API-Adresse hinterlegt.',
        'invalid_number' => 'Die Rufnummer ist nicht wählbar.',
        'rejected' => 'Die Telefonanlage hat den Anruf abgelehnt (HTTP :status).',
        'settings' => 'Click-to-Dial',
        'enabled' => 'Anrufe aus workDiary starten',
        'api_token' => 'API-Token',
        'api_token_help' => 'Leer lassen, um das gespeicherte Token zu behalten.',
        'api_base_url' => 'API-Adresse',
        'extension' => 'Eigene Durchwahl',
        'extension_help' => 'Von dieser Durchwahl aus wird gewählt; die Anlage ruft sie zuerst an.',
    ],
];
