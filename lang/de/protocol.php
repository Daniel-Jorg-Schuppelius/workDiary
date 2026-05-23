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
        'photo' => [
            'uploaded' => 'Foto hinzugefügt.',
            'removed' => 'Foto entfernt.',
            'captionUpdated' => 'Bildunterschrift aktualisiert.',
        ],
    ],
    'validation' => [
        'required' => 'Punkt „:label" ist Pflicht.',
        'criticalDefectMissingOpenIssue' => 'Kritischer Mangel „:label" benötigt einen offenen Punkt.',
        'text' => [
            'minLength' => 'Text zu kurz (min. :min Zeichen).',
            'maxLength' => 'Text zu lang (max. :max Zeichen).',
        ],
        'boolean' => [
            'invalid' => 'Es ist ein boolescher Wert erforderlich.',
        ],
        'choice' => [
            'invalid' => 'Es ist eine Auswahl erforderlich.',
            'notInOptions' => 'Auswahl nicht in der Optionsliste enthalten.',
        ],
        'multichoice' => [
            'invalid' => 'Mindestens eine Auswahl erforderlich.',
            'notInOptions' => 'Auswahl nicht in der Optionsliste enthalten.',
        ],
        'number' => [
            'invalid' => 'Numerischer Wert erforderlich.',
            'min' => 'Wert unterschreitet Mindestwert (:bound).',
            'max' => 'Wert überschreitet Höchstwert (:bound).',
        ],
        'date' => [
            'invalid' => 'Ungültiges Datum.',
        ],
        'attachments' => [
            'required' => 'Mindestens ein Anhang erforderlich.',
            'min' => 'Mindestens :min Anhänge erforderlich.',
            'max' => 'Maximal :max Anhänge erlaubt.',
        ],
        'defect' => [
            'severity' => 'Schweregrad muss low/medium/high/critical sein.',
            'description' => 'Beschreibung des Mangels ist Pflicht.',
        ],
        'measurement' => [
            'empty' => 'Mindestens eine Messung erforderlich.',
            'invalidSample' => 'Jede Messung benötigt „at" und „value".',
        ],
        'signature' => [
            'missing' => 'Signatur ist noch nicht verknüpft.',
        ],
        'photo' => [
            'missingPhase' => 'Foto-Punkt „:label": Phase „:phase" benötigt mindestens :need Foto(s) (vorhanden: :have).',
        ],
    ],
    'pdf' => [
        'title' => 'Protokoll – :title',
        'state' => 'Zustand',
        'items' => 'Protokollpunkte',
        'signatures' => 'Unterschriften',
        'col' => [
            'label' => 'Punkt',
            'type' => 'Typ',
            'value' => 'Wert',
            'result' => 'Ergebnis',
            'note' => 'Anmerkung',
        ],
        'footer' => [
            'hash' => 'Prüfsumme',
            'generated' => 'Erstellt am :at',
        ],
    ],
    'signature' => [
        'tokenIssued' => 'Signaturlink wurde erstellt.',
        'tokenExpired' => 'Der Signaturlink ist abgelaufen oder bereits eingelöst.',
        'tokenUnknown' => 'Signaturlink unbekannt.',
        'redeemed' => 'Unterschrift wurde gespeichert.',
    ],
];
