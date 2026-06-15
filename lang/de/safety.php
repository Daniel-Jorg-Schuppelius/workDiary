<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Sicherheitsereignisse',
    ],
    'subtitle' => [
        'index' => 'Unfälle, Beinaheunfälle, Gefährdungen und Mängel erfassen und nachverfolgen.',
    ],
    'empty' => 'Noch keine Sicherheitsereignisse erfasst.',

    'field' => [
        'event_no' => 'Nummer',
        'kind' => 'Art',
        'severity' => 'Schweregrad',
        'status' => 'Status',
        'occurred_at' => 'Zeitpunkt',
        'location' => 'Ort',
        'affected_person' => 'Betroffene Person',
        'reporter' => 'Gemeldet von',
        'subject' => 'Verknüpft mit',
        'description' => 'Beschreibung',
        'immediate_action' => 'Sofortmaßnahme',
        'root_cause' => 'Ursachenanalyse',
        'closed_at' => 'Geschlossen am',
        'closed_by' => 'Geschlossen von',
        'followup_title' => 'Titel der Folgemaßnahme',
        'followup_description' => 'Beschreibung (optional)',
    ],

    'section' => [
        'status' => 'Status ändern',
        'followup' => 'Folgemaßnahme',
        'attachments' => 'Anhänge',
        'followups' => 'Folgemaßnahmen',
    ],

    'no_attachments' => 'Keine Anhänge.',
    'no_followups' => 'Noch keine Folgemaßnahmen.',

    'action' => [
        'create' => 'Ereignis melden',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'show' => 'Ansehen',
        'back' => 'Zurück',
        'create_followup' => 'Folgemaßnahme anlegen',
    ],

    'transition' => [
        'investigating' => 'Untersuchung starten',
        'measuresDefined' => 'Maßnahmen definiert',
        'closed' => 'Schließen',
    ],

    'hint' => [
        'root_cause_for_close' => 'Für den Abschluss wird eine Ursachenanalyse benötigt.',
        'followup' => 'Legt einen offenen Punkt als Nacharbeit zu diesem Ereignis an.',
    ],

    'flash' => [
        'created' => 'Sicherheitsereignis wurde erfasst.',
        'updated' => 'Sicherheitsereignis wurde aktualisiert.',
        'deleted' => 'Sicherheitsereignis wurde gelöscht.',
        'followup_created' => 'Folgemaßnahme wurde angelegt.',
        'status' => [
            'reported' => 'Ereignis wurde zurückgesetzt.',
            'investigating' => 'Untersuchung wurde gestartet.',
            'measuresDefined' => 'Maßnahmen wurden definiert.',
            'closed' => 'Ereignis wurde geschlossen.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Ungültiger Statuswechsel: :from → :to.',
        'close_requires_root_cause' => 'Der Abschluss erfordert eine Ursachenanalyse.',
    ],

    'report' => [
        'title' => 'Sicherheits-Auswertung',
        'nav' => 'Arbeitsschutz',
        'subtitle' => 'Sicherheitsereignisse nach Art und Schweregrad im Zeitraum.',
        'by_kind' => 'Nach Art',
        'by_severity' => 'Nach Schweregrad',
        'kpi' => [
            'total' => 'Ereignisse gesamt',
            'open' => 'Offen',
            'closed' => 'Geschlossen',
            'critical' => 'Kritisch',
        ],
    ],
];
