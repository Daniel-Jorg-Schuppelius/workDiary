<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : crisis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Offline-Krisenmappe (MVP-914).
    'offline' => [
        'save' => 'Krisenmappe offline speichern',
        'hint' => 'Speichert die aktiven Krisen mit Lage, Maßnahmen und Erreichbarkeit des Krisenstabs auf diesem Gerät; ohne Netz auf der Offline-Seite lesbar. Beim Abmelden gelöscht.',
    ],
    // Öffentliche Statusseite (MVP-915).
    'status_page' => [
        'title' => 'Statusseite (öffentlich)',
        'intro' => 'Die Statusseite zeigt versandte Krisenmitteilungen an die Öffentlichkeit ohne Anmeldung; Mitteilungen an Kunden erscheinen zusätzlich im Kundenportal. Gezeigt werden nur Betreff, Text und Zeitpunkt, nie die Krisenakte.',
        'token_once' => 'Dieser Link wird nur jetzt angezeigt — er ist nirgends gespeichert.',
        'state' => 'Status',
        'state_none' => 'Nicht eingerichtet',
        'state_active' => 'Öffentlich erreichbar',
        'state_paused' => 'Pausiert',
        'hint' => 'Kennung',
        'issued_at' => 'Ausgestellt am',
        'action' => [
            'issue' => 'Link ausstellen',
            'rotate' => 'Link erneuern',
            'revoke' => 'Zugang entziehen',
            'pause' => 'Pausieren',
            'resume' => 'Freischalten',
        ],
        'confirm' => [
            'rotate' => 'Neuen Link ausstellen? Der bisherige Link funktioniert danach nicht mehr.',
            'revoke' => 'Zugang entziehen? Die Statusseite ist danach nicht mehr öffentlich erreichbar.',
        ],
        'flash' => [
            'issued' => 'Neuer Link ausgestellt.',
            'revoked' => 'Zugang entzogen.',
            'saved' => 'Gespeichert.',
        ],
        'public_title' => 'Aktuelle Lage – :org',
        'public_intro' => 'Hier informieren wir über laufende Störungen und Vorfälle.',
        'all_clear' => 'Derzeit liegen keine Störungen vor.',
        'resolved' => 'entwarnt',
    ],
    // BIA-Register (MVP-943).
    'bia' => [
        'title' => 'BIA-Register',
        'subtitle' => 'Geschäftsprozesse mit Kritikalität, Wiederanlaufzielen (RTO/RPO) und maximal tolerierbarer Ausfallzeit (MTPD).',
        'create' => 'Prozess anlegen',
        'edit' => 'Prozess bearbeiten',
        'save' => 'Speichern',
        'empty' => 'Noch keine Prozesse im Register.',
        'inactive' => 'inaktiv',
        'import' => 'Aus Registern übernehmen',
        'import_hint' => 'Vorschläge aus dem Verzeichnis der Verarbeitungstätigkeiten, den ISMS-Risiken und aktiven Prozedurvorlagen. Übernommen wird nur, was Sie auswählen.',
        'import_submit' => 'Auswahl übernehmen',
        'adopt' => 'Aus BIA-Register übernehmen',
        'kind' => [
            'processing_activity' => 'Verarbeitungstätigkeit',
            'isms_risk' => 'ISMS-Risiko',
            'procedure_template' => 'Prozedurvorlage',
        ],
        'criticality' => [
            'low' => 'gering',
            'medium' => 'mittel',
            'high' => 'hoch',
            'critical' => 'kritisch',
        ],
        'field' => [
            'name' => 'Prozess',
            'description' => 'Beschreibung',
            'criticality' => 'Kritikalität',
            'rto_hours' => 'RTO (Stunden)',
            'rpo_hours' => 'RPO (Stunden)',
            'mtpd_hours' => 'MTPD (Stunden)',
            'owner' => 'Verantwortlich',
            'dependencies' => 'Abhängigkeiten (Systeme, Lieferanten, Personen)',
            'review_due_on' => 'Überprüfung fällig',
            'is_active' => 'Aktiv',
        ],
        'flash' => [
            'saved' => 'Prozess gespeichert.',
            'imported' => ':count Prozesse übernommen.',
            'adopted' => 'Prozess aus dem BIA-Register übernommen.',
        ],
    ],
    // BCM-Auswertung (MVP-944).
    'bcm_report' => [
        'title' => 'BCM-Auswertung',
        'subtitle' => 'Kennzahlen nach ISO 22301: Übungen, Maßnahmen, Nachbetrachtungen und BIA-Stand.',
        'back' => 'Krisenmanagement',
        'disclaimer' => 'Kennzahlen aus den erfassten Daten; keine Aussage über eine Zertifizierungsfähigkeit.',
        'overdue' => ':count überfällig',
        'row' => [
            'exercises' => 'Übungen seit :date',
            'effectiveness' => 'Wirksamkeit',
            'exercises_due' => 'Fällige Übungen',
            'actions_open' => 'Offene Maßnahmen',
            'reviews' => 'Beendete Krisen mit Nachbetrachtung',
            'processes' => 'Prozesse im BIA-Register',
            'without_rto' => 'Prozesse ohne RTO',
            'review_due' => 'Prozesse mit fälliger Überprüfung',
        ],
        'effectiveness' => [
            'effective' => 'wirksam',
            'partly' => 'teilweise',
            'ineffective' => 'unwirksam',
            'open' => 'ohne Bewertung',
        ],
    ],
    // Krisenraum (MVP-963).
    'room' => [
        'title' => 'Krisenraum',
        'present' => 'Anwesend',
        'nobody' => 'niemand sonst',
        'no_markers' => 'Keine Orte: Assets oder Kunden mit Koordinaten verknüpfen oder Lagepunkte setzen.',
        'add_point' => 'Lagepunkt setzen',
        'field' => [
            'label' => 'Bezeichnung',
            'kind' => 'Art',
            'lat' => 'Breite',
            'lng' => 'Länge',
        ],
        'kind' => [
            'incident' => 'Schadensort',
            'assembly' => 'Sammelpunkt',
            'closure' => 'Sperrung',
            'resource' => 'Einsatzmittel',
            'other' => 'Sonstiges',
        ],
        'layer' => [
            'linked' => 'Verknüpfte Objekte',
        ],
        'link' => [
            'asset' => 'Asset',
            'customer' => 'Kunde',
        ],
        'flash' => [
            'point_saved' => 'Lagepunkt gesetzt.',
            'point_deleted' => 'Lagepunkt entfernt.',
        ],
    ],
    // Meldefristen je Organisation (MVP-1589).
    'deadlines' => [
        'title' => 'Meldefristen',
        'subtitle' => 'Fristen je Krisen-Kategorie. Eigene Fristen einer Kategorie ersetzen deren Vorgaben; die Termine einer Krisenakte laufen ab der Aktivierung.',
        'create' => 'Meldefrist anlegen',
        'edit' => 'Meldefrist bearbeiten',
        'save' => 'Speichern',
        'defaults' => 'Vorgaben',
        'empty' => 'Noch keine eigenen Fristen. Es gelten die Vorgaben.',
        'no_defaults' => 'Keine Vorgaben hinterlegt.',
        'inactive' => 'inaktiv',
        'replaced' => 'ersetzt',
        'immediate' => 'unverzüglich',
        'adopt' => 'Als eigene Fristen übernehmen',
        'activate' => 'Aktivieren',
        'deactivate' => 'Deaktivieren',
        'form_hint' => 'Sobald eine Kategorie eine aktive eigene Frist hat, gelten für sie nur noch die eigenen Fristen. Übernehmen Sie zuerst die Vorgaben, wenn Sie nur eine Frist ändern möchten.',
        'field' => [
            'category' => 'Kategorie',
            'label' => 'Bezeichnung',
            'offset_hours' => 'Frist (Stunden)',
            'offset_hours_hint' => 'Stunden ab Aktivierung der Krise. Leer = unverzüglich.',
            'source' => 'Rechtsgrundlage',
            'active' => 'Aktiv',
        ],
        'flash' => [
            'saved' => 'Meldefrist gespeichert.',
            'adopted' => 'Vorgaben als eigene Fristen übernommen.',
            'already_own' => 'Für diese Kategorie gibt es bereits eigene Fristen.',
            'activated' => 'Meldefrist aktiviert.',
            'deactivated' => 'Meldefrist deaktiviert.',
        ],
    ],
    // Abhängigkeiten, Ersatzprozess, Anwesenheitsliste, Eröffnen aus Vorfall (MVP-1605).
    'link' => [
        'dialog_title' => 'Vorgang verknüpfen',
        'target' => 'Vorgang',
        'target_hint' => 'Je Art stehen die jüngsten Vorgänge zur Wahl, die Sie lesen dürfen.',
        'note' => 'Notiz zur Verknüpfung',
        'none_available' => 'Es gibt keine Vorgänge, die Sie verknüpfen können.',
        'opened_from' => 'Krise aus diesem Vorfall eröffnet',
        'type' => [
            'service_ticket' => 'Service-Ticket',
            'isms_incident' => 'Sicherheitsvorfall',
            'privacy_incident' => 'Datenschutzvorfall',
            'safety_event' => 'Arbeitsschutzereignis',
            'procedure_run' => 'Playbook-/Prozedurlauf',
            'document' => 'Dokument',
            'asset' => 'Asset',
            'customer' => 'Kunde',
        ],
    ],
    'open' => [
        'action' => 'Krise eröffnen',
        'from' => 'Die Krise wird aus :source eröffnet und mit dem Vorfall verknüpft.',
    ],
    'action' => [
        'depends_on' => 'Vorgänger',
        'no_predecessor' => 'ohne Vorgänger',
        'after' => 'nach: :title',
        'blocked_by' => 'Die Maßnahme lässt sich erst erledigen, wenn „:title“ erledigt oder abgebrochen ist.',
        'depends_foreign' => 'Der Vorgänger gehört nicht zu dieser Krisenakte.',
        'depends_cycle' => 'Der Vorgänger wartet selbst auf diese Maßnahme — das ergäbe einen Kreis.',
        'predecessor_saved' => 'Vorgänger gespeichert.',
    ],
    'bcm' => [
        'substitute_process' => 'Ersatzprozess',
        'residual_note' => 'Verbleibende Einschränkung',
    ],
    'presence' => [
        'open' => 'Notfall-Anwesenheitsliste',
    ],
];
