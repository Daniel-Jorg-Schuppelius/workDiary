<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : protocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Protokolle',
        'show' => 'Protokoll #:id',
        'create' => 'Protokoll anlegen',
        'edit' => 'Protokoll bearbeiten',
    ],
    'field' => [
        'type' => 'Typ',
        'title' => 'Titel',
        'description' => 'Beschreibung',
        'state_initial' => 'Zustand vorher',
        'state_final' => 'Zustand nachher',
        'occurred_at' => 'Datum / Zeitpunkt',
        'visibility' => 'Sichtbarkeit',
        'status' => 'Status',
        'revision' => 'Revision',
        'subject' => 'Bezug',
    ],
    'action' => [
        'create' => 'Anlegen',
        'update' => 'Speichern',
        'requestReview' => 'Zur Prüfung einreichen',
        'returnToDraft' => 'Zurück in Entwurf',
        'sign' => 'Abschließen / Unterschreiben',
        'archive' => 'Archivieren',
        'supersede' => 'Korrektur-Revision erstellen',
        'addItem' => 'Punkt hinzufügen',
        'fillItem' => 'Punkt erfassen',
        'removeItem' => 'Punkt entfernen',
        'delete' => 'Löschen',
    ],
    'flash' => [
        'created' => 'Protokoll angelegt.',
        'updated' => 'Protokoll aktualisiert.',
        'deleted' => 'Protokoll gelöscht.',
        'transition' => [
            'requestReview' => 'Protokoll zur Prüfung eingereicht.',
            'returnToDraft' => 'Protokoll wieder in Entwurf gesetzt.',
            'sign' => 'Protokoll unterschrieben und festgeschrieben.',
            'archive' => 'Protokoll archiviert.',
            'supersede' => 'Korrektur-Revision angelegt.',
        ],
        'item' => [
            'added' => 'Punkt hinzugefügt.',
            'filled' => 'Punkt erfasst.',
            'removed' => 'Punkt entfernt.',
        ],
    ],
];
