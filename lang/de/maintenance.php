<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maintenance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'window' => [
        'title' => 'Wartungsfenster',
        'subtitle' => 'Geplante Downtimes ankündigen, starten, verlängern und nachvollziehbar abschließen.',
        'read_only_message' => 'Wartungsarbeiten: Die Anwendung ist vorübergehend nur lesend verfügbar.',
        'scope' => [
            'system' => 'Installationsweit',
            'organization' => 'Nur diese Organisation',
        ],
        'mode' => [
            'full' => 'Vollsperre',
            'read_only' => 'Nur-Lesen',
            'block_ingest' => 'Ingest gesperrt',
            'read_only_toggle' => 'Nur-Lesen-Betrieb (Lesezugriff bleibt möglich)',
            'block_ingest_toggle' => 'Terminal-/CTI-/Standort-Ingest während der Wartung sperren',
        ],
        'status' => [
            'planned' => 'Geplant',
            'announced' => 'Angekündigt',
            'active' => 'Aktiv',
            'extended' => 'Verlängert',
            'completed' => 'Abgeschlossen',
            'rolled_back' => 'Rollback',
            'cancelled' => 'Abgesagt',
        ],
        'field' => [
            'window' => 'Zeitfenster',
            'scope' => 'Geltungsbereich',
            'mode' => 'Modus',
            'status' => 'Status',
            'actions' => 'Aktionen',
            'announce_from' => 'Ankündigung ab',
            'starts_at' => 'Beginn',
            'ends_at' => 'Ende',
            'message' => 'Hinweistext',
        ],
        'action' => [
            'plan' => 'Wartungsfenster planen',
            'save' => 'Planen',
            'announce' => 'Ankündigen',
            'start' => 'Jetzt starten',
            'complete' => 'Beenden',
            'extend' => 'Verlängern',
            'rollback' => 'Rollback',
            'cancel' => 'Absagen',
        ],
        'banner' => [
            'upcoming' => 'Geplante Wartung: :from bis :to — bitte Arbeiten rechtzeitig speichern.',
            'read_only' => 'Wartung aktiv bis :to — Änderungen sind vorübergehend nicht möglich.',
        ],
        'hint' => [
            'message' => 'Optional: Was wird gewartet, was ist zu erwarten?',
        ],
        'empty' => [
            'title' => 'Keine Wartungsfenster',
            'message' => 'Es sind keine Wartungsfenster geplant.',
        ],
        'flash' => [
            'planned' => 'Wartungsfenster geplant.',
            'announce' => 'Wartungsfenster angekündigt.',
            'start' => 'Wartungsfenster gestartet.',
            'complete' => 'Wartungsfenster beendet.',
            'extend' => 'Wartungsfenster verlängert.',
            'rollback' => 'Wartung als Rollback abgeschlossen.',
            'cancel' => 'Wartungsfenster abgesagt.',
        ],
    ],
];
