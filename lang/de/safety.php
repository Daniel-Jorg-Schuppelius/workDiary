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

    // Arbeitsschutz-Register (Feature 132): GBU, Unterweisung, Vorsorge.
    'register' => [
        'section' => 'Arbeitsschutz',
        'nav' => [
            'assessments' => 'Gefährdungsbeurteilungen',
            'instructions' => 'Unterweisungen',
            'checkups' => 'Vorsorge',
        ],
        'title' => [
            'assessments' => 'Gefährdungsbeurteilungen',
            'instructions' => 'Unterweisungen',
            'checkups' => 'Arbeitsmedizinische Vorsorge',
        ],
        'subtitle' => [
            'assessments' => 'Gefährdungsbeurteilungen nach § 5 ArbSchG — versioniert, mit Wiedervorlage.',
            'instructions' => 'Unterweisungen nach DGUV Vorschrift 1 § 4 mit Teilnahme-Nachweis je Person.',
            'checkups' => 'Arbeitsmedizinische Vorsorge nach ArbMedVV — nur Art, Termin und Bescheinigung, keine Gesundheitsdaten.',
        ],
        'field' => [
            'assessment_no' => 'Nummer',
            'version' => 'Version',
            'area' => 'Bereich',
            'activity' => 'Tätigkeit',
            'description' => 'Beschreibung',
            'status' => 'Status',
            'review_due_on' => 'Wiedervorlage',
            'approved_by' => 'Freigegeben von',
            'approved_at' => 'Freigegeben am',
            'created_by' => 'Angelegt von',
            'supersedes' => 'Ersetzt',
            'superseded_by' => 'Ersetzt durch',
            'items' => 'Gefährdungen',
            'position' => 'Pos.',
            'hazard' => 'Gefährdung',
            'measure' => 'Maßnahme',
            'severity' => 'Schwere (S)',
            'likelihood' => 'Wahrscheinlichkeit (W)',
            'risk_before' => 'Risiko vorher',
            'risk_after' => 'Risiko nachher',
            'before' => 'Vor Maßnahme',
            'after' => 'Nach Maßnahme',
            'instruction_no' => 'Nummer',
            'topic' => 'Thema',
            'held_on' => 'Datum',
            'instructor' => 'Unterweisende:r',
            'assessment' => 'Gefährdungsbeurteilung',
            'repeat_interval_months' => 'Wiederholung (Monate)',
            'notes' => 'Hinweise',
            'participants' => 'Teilnehmende',
            'signed' => 'Bestätigt',
            'signed_at' => 'Bestätigt am',
            'method' => 'Nachweisform',
            'next_due_on' => 'Nächste Fälligkeit',
            'user' => 'Person',
            'kind' => 'Art',
            'occasion' => 'Anlass',
            'performed_on' => 'Durchgeführt am',
            'certificate_on_file' => 'Bescheinigung liegt vor',
        ],
        'action' => [
            'create_assessment' => 'Gefährdungsbeurteilung anlegen',
            'edit' => 'Bearbeiten',
            'save' => 'Speichern',
            'delete' => 'Löschen',
            'show' => 'Ansehen',
            'back' => 'Zurück',
            'transition' => 'Status ändern',
            'new_version' => 'Folgeversion anlegen',
            'add_item' => 'Gefährdung hinzufügen',
            'edit_item' => 'Gefährdung bearbeiten',
            'create_instruction' => 'Unterweisung erfassen',
            'sign' => 'Teilnahme bestätigen',
            'create_checkup' => 'Vorsorge erfassen',
        ],
        'filter' => [
            'all' => 'Alle',
            'current_only' => 'Nur aktuelle Stände',
            'open_only' => 'Nur mit offenen Bestätigungen',
            'due_only' => 'Nur fällige',
        ],
        'kpi' => [
            'review_due' => 'Wiedervorlage fällig',
            'instruction_due' => 'Wiederholung fällig',
            'checkup_due' => 'Vorsorge fällig',
        ],
        'empty' => [
            'assessments' => 'Noch keine Gefährdungsbeurteilung angelegt.',
            'items' => 'Noch keine Gefährdung erfasst.',
            'instructions' => 'Noch keine Unterweisung erfasst.',
            'participants' => 'Keine Teilnehmenden.',
            'checkups' => 'Noch keine Vorsorge erfasst.',
        ],
        'hint' => [
            'frozen' => 'Dieser Stand ist freigegeben und eingefroren. Änderungen erfolgen über eine Folgeversion.',
            'approve_requires_items' => 'Die Freigabe erfordert mindestens eine Gefährdung.',
            'sign_self' => 'Bestätigen Sie Ihre Teilnahme — Name, Zeitpunkt und IP-Adresse werden als Nachweis festgehalten.',
            'no_health_data' => 'Es werden keine Befunde oder Diagnosen gespeichert — nur Art, Termin und ob die Bescheinigung vorliegt.',
            'after_optional' => 'Risiko nach Maßnahme optional — beide Werte gemeinsam angeben.',
            'pdf_not_in_mvp' => 'PDF-Nachweis folgt in einer späteren Ausbaustufe.',
        ],
        'confirm' => [
            'delete_assessment' => 'Gefährdungsbeurteilung löschen?',
            'delete_item' => 'Gefährdung löschen?',
            'delete_instruction' => 'Unterweisung löschen?',
            'delete_checkup' => 'Vorsorge-Eintrag löschen?',
            'sign' => 'Teilnahme jetzt verbindlich bestätigen?',
        ],
        'flash' => [
            'assessment_created' => 'Gefährdungsbeurteilung wurde angelegt.',
            'assessment_updated' => 'Gefährdungsbeurteilung wurde aktualisiert.',
            'assessment_transitioned' => 'Status wurde geändert.',
            'assessment_version_created' => 'Folgeversion :version wurde angelegt.',
            'assessment_deleted' => 'Gefährdungsbeurteilung wurde gelöscht.',
            'item_created' => 'Gefährdung wurde hinzugefügt.',
            'item_updated' => 'Gefährdung wurde aktualisiert.',
            'item_deleted' => 'Gefährdung wurde entfernt.',
            'instruction_created' => 'Unterweisung wurde erfasst.',
            'instruction_updated' => 'Unterweisung wurde aktualisiert.',
            'instruction_deleted' => 'Unterweisung wurde gelöscht.',
            'participation_signed' => 'Teilnahme wurde bestätigt.',
            'checkup_created' => 'Vorsorge wurde erfasst.',
            'checkup_updated' => 'Vorsorge wurde aktualisiert.',
            'checkup_deleted' => 'Vorsorge-Eintrag wurde gelöscht.',
        ],
        'error' => [
            'assessment_frozen' => 'Freigegebene Gefährdungsbeurteilungen sind eingefroren — bitte eine Folgeversion anlegen.',
            'approve_requires_items' => 'Die Freigabe erfordert mindestens eine Gefährdung.',
            'new_version_requires_approved' => 'Eine Folgeversion ist nur aus einem freigegebenen Stand möglich.',
            'after_pair_incomplete' => 'Risiko nach Maßnahme: Schwere und Wahrscheinlichkeit gemeinsam angeben.',
            'sign_only_self' => 'Nur die eingetragene Person kann ihre Teilnahme bestätigen.',
            'already_signed' => 'Die Teilnahme ist bereits bestätigt.',
            'delete_with_signatures' => 'Unterweisungen mit bestätigten Nachweisen können nicht gelöscht werden.',
        ],
        'status_summary' => ':signed von :total bestätigt',
    ],
];
