<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : allocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zeit aufteilen',
    'entry_duration' => 'Dauer des Eintrags',
    'hint' => 'Leere Zeilen werden ignoriert; alle Zeilen leeren entfernt die Aufteilung. Die Summe der Anteile darf die Dauer nicht übersteigen.',
    'target' => 'Ziel',
    'minutes' => 'Minuten',
    'quantity' => 'Menge',
    'comment' => 'Kommentar',
    'none_option' => '— kein Anteil —',
    'type' => [
        'task' => 'Aufgaben',
        'asset' => 'Assets',
        'project' => 'Projekte',
        'cost_center' => 'Kostenstellen',
        'site' => 'Standorte',
        'vehicle' => 'Fahrzeuge',
        'activity_category' => 'Tätigkeiten',
    ],
    'action' => [
        'split' => 'Aufteilen',
        'save' => 'Aufteilung speichern',
    ],
    'flash' => [
        'saved' => 'Aufteilung gespeichert.',
    ],
    'error' => [
        'locked' => 'Eintrag ist gesperrt (:reason) — Aufteilung nicht möglich.',
        'invalid_target' => 'Ungültiges oder fremdes Aufteilungsziel.',
        'minutes_min' => 'Jeder Anteil braucht mindestens eine Minute.',
        'sum_exceeds' => 'Die Summe der Anteile (:sum Min.) übersteigt die Dauer des Eintrags (:max Min.).',
    ],
    // Freie Mandanten-Dimensionen (MVP-514 P2)
    'dimensions' => [
        'nav' => 'Zeit-Dimensionen',
        'title' => 'Freie Zeit-Dimensionen',
        'intro' => 'Eigene Dimensionen für die Zeitaufteilung (z. B. ERP-Aufträge) — nur für Ziele ohne vorhandenes WorkDiary-Modell. Die externe ID ist der Anker für eine spätere Provider-Synchronisation.',
        'new_type' => 'Neuer Dimensionstyp',
        'code' => 'Code',
        'name' => 'Name',
        'create_type' => 'Typ anlegen',
        'enabled' => 'Aktiv',
        'disabled' => 'Inaktiv',
        'no_types' => 'Noch keine Dimensionstypen angelegt.',
        'no_values' => 'Noch keine Werte.',
        'external_id' => 'Externe ID',
        'validity' => 'Gültigkeit',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'create_value' => 'Wert anlegen',
        'delete_value' => 'Löschen',
        'flash' => [
            'type_created' => 'Dimensionstyp angelegt.',
            'type_enabled' => 'Dimensionstyp aktiviert.',
            'type_disabled' => 'Dimensionstyp deaktiviert.',
            'value_created' => 'Wert angelegt.',
            'value_deleted' => 'Wert gelöscht.',
        ],
    ],
];
