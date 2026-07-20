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
        'stateInitial' => 'Zustand vorher',
        'state_final' => 'Zustand nachher',
        'stateFinal' => 'Zustand nachher',
        'occurred_at' => 'Datum / Zeitpunkt',
        'occurredAt' => 'Datum / Zeitpunkt',
        'createdBy' => 'Erstellt von',
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
            'reordered' => 'Reihenfolge aktualisiert.',
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
        'photos' => ['more' => ':count weitere Foto(s)'],
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
        'tokenRevoked' => 'Signaturlink wurde widerrufen.',
        'tokenList' => 'Externe Signaturlinks',
        'tokenUsed' => 'eingelöst',
        'tokenOpen' => 'offen',
        'revoke' => 'Link widerrufen',
        'externalLink' => 'Externer Link',
        'tokenExpired' => 'Der Signaturlink ist abgelaufen oder bereits eingelöst.',
        'tokenUnknown' => 'Signaturlink unbekannt.',
        'redeemed' => 'Unterschrift wurde gespeichert.',
        'rejected' => 'Ihre Ablehnung wurde erfasst. Die gemeldeten Punkte wurden weitergeleitet.',
        'alreadyDecided' => 'Über diesen Vorgang wurde bereits entschieden.',
        'customer' => 'Kunde',
        'approveHeading' => 'Freigeben & unterschreiben',
        'rejectHeading' => 'Ablehnen',
        'rejectHint' => 'Bitte begründen Sie die Ablehnung. Einzelne Mängel werden als offene Punkte erfasst.',
        'rejectReason' => 'Begründung',
        'rejectIssues' => 'Einzelne Mängel (je Zeile ein Punkt, optional)',
        'rejectIssuesPlaceholder' => 'z. B. Fuge an Fenster undicht',
        'rejectSubmit' => 'Ablehnen',
        'rejectIssueDescription' => 'Vom Kunden bei der Ablehnung des Vorgangs „:protocol" gemeldet (:name).',
        'queryHeading' => 'Rückfrage stellen',
        'queryQuestion' => 'Ihre Frage',
        'querySubmit' => 'Rückfrage senden',
        'queryRaised' => 'Ihre Rückfrage wurde übermittelt.',
        'queryHistory' => 'Ihre Rückfragen',
        'queryAnswer' => 'Antwort',
        'queryPending' => 'Noch nicht beantwortet.',
    ],
];
