<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diagnostics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Diagnose-Seite: Überwachungszugang (MVP-1618).
return [
    'token' => [
        'heading' => 'Überwachungszugang',
        'intro' => 'Externe Überwachung ruft den Bericht ohne Anmeldung unter :url ab und sendet den Token im Kopf „Authorization: Bearer …“. Namen und Adressen fehlen im Bericht.',
        'create' => 'Zugang anlegen',
        'name' => 'Bezeichnung',
        'name_help' => 'Etwa der Name des Überwachungssystems, damit Sie den Zugang später zuordnen können.',
        'hint' => 'Kennung',
        'created_by' => 'Angelegt',
        'last_used' => 'Letzter Abruf',
        'status' => 'Status',
        'active' => 'Aktiv',
        'revoked_state' => 'Widerrufen',
        'revoke' => 'Widerrufen',
        'revoke_confirm' => 'Zugang widerrufen? Die Überwachung erhält danach keinen Bericht mehr.',
        'shown_once' => 'Kopieren Sie den Token jetzt. Er wird nur dieses eine Mal angezeigt und lässt sich nicht wiederherstellen.',
        'empty' => 'Noch kein Überwachungszugang angelegt.',
        'created' => 'Überwachungszugang angelegt.',
        'revoked' => 'Überwachungszugang widerrufen.',
    ],
];
