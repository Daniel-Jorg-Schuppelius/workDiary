<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : delegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vertretung bei Abwesenheit (Feature 202, MVP-1647).
return [
    'title' => 'Vertretungen',
    'subtitle' => 'Wer wen während einer Abwesenheit vertritt. Die Vertretung sieht die zugewiesene Arbeit im Arbeitsvorrat und erhält deren Benachrichtigungen — Rechte bekommt sie dadurch nicht.',
    'create' => 'Vertretung anlegen',
    'from_absence' => 'Vertretung anlegen',
    'filter' => [
        'state' => 'Zeitraum',
        'current' => 'Laufend und geplant',
        'past' => 'Beendet und abgelaufen',
        'all' => 'Alle der Organisation',
    ],
    'empty' => [
        'title' => 'Keine Vertretungen',
        'message' => 'Hier erscheinen Vertretungen, an denen Sie beteiligt sind.',
    ],
    'column' => [
        'represented' => 'Vertretene Person',
        'deputy' => 'Vertretung',
        'period' => 'Zeitraum',
        'state' => 'Stand',
        'work' => 'Übernimmt',
    ],
    'open_end' => 'bis auf Weiteres',
    'state' => [
        'active' => 'Läuft',
        'upcoming' => 'Geplant',
        'past' => 'Abgelaufen',
        'ended' => 'Beendet',
    ],
    'ended_by' => 'Beendet von :name am :date',
    'end' => 'Vertretung beenden',
    'end_confirm' => 'Vertretung jetzt beenden? Die Arbeit liegt dann wieder nur bei der vertretenen Person.',
    'dialog' => [
        'title' => 'Vertretung anlegen',
        'submit' => 'Vertretung anlegen',
        'no_rights_hint' => 'Die Vertretung sieht und bearbeitet die zugewiesene Arbeit und erhält deren Benachrichtigungen. Sie erhält keine zusätzlichen Rechte und keinen Einblick in Zeiten, Abwesenheiten oder Personalakte.',
        'people' => 'Personen',
        'deputy_hint' => 'Vorbelegt mit der benannten Stellvertretung der Person.',
        'choose_deputy' => 'Bitte wählen',
        'from' => 'Von',
        'until' => 'Bis',
        'until_hint' => 'Ohne Enddatum gilt die Vertretung, bis jemand sie beendet.',
        'kind' => 'Anlass',
        'note' => 'Notiz',
        'work_heading' => 'Das übernimmt die Vertretung von :name',
        'work_none' => 'Zurzeit keine offene zugewiesene Arbeit',
        'work_hint' => 'Zuständig bleibt die vertretene Person; nach dem Ende ist nichts umzuhängen.',
    ],
    'banner' => [
        'represents' => 'Sie vertreten :names.',
        'represented_by' => 'Vertreten durch :names.',
        'person_until' => ':name (bis :date)',
        'work_queue' => 'Zum Arbeitsvorrat',
        'manage' => 'Vertretungen',
    ],
    'notification' => [
        'on_behalf_of' => 'In Vertretung für :name:',
        'created' => 'Sie vertreten :name vom :from bis :until.',
        'created_open' => 'Sie vertreten :name ab :from bis auf Weiteres.',
    ],
    'flash' => [
        'created' => 'Vertretung durch :name angelegt.',
        'ended' => 'Vertretung beendet.',
    ],
    'error' => [
        'past_start' => 'Eine Vertretung beginnt frühestens heute.',
        'end_before_start' => 'Das Ende liegt vor dem Beginn.',
        'absence_mismatch' => 'Die Abwesenheit gehört nicht zu dieser Person oder ist nicht genehmigt.',
        'overlap' => ':name hat in diesem Zeitraum bereits eine Vertretung.',
        'deputy_absent' => ':name wird in diesem Zeitraum selbst vertreten und kann nicht vertreten.',
        'represented_is_deputy' => ':name vertritt in diesem Zeitraum selbst jemanden; Kettenvertretungen gibt es nicht.',
        'already_ended' => 'Diese Vertretung ist bereits beendet.',
        'foreign_organization' => 'Vertretungen gibt es nur innerhalb der eigenen Organisation.',
        'self' => 'Eine Person kann sich nicht selbst vertreten.',
        'deactivated' => 'Deaktivierte Konten können weder vertreten noch vertreten werden.',
    ],
    // Endgültige Übergabe ohne Austritt und Mehrfachauswahl in der Auftragsliste (MVP-1648).
    'handover' => [
        'action' => 'Zuständigkeiten übergeben',
        // Personenwahl auf der Seite „Vertretungen“ (Einstieg ohne Mitgliederliste).
        'choose_title' => 'Wessen Zuständigkeiten übergeben?',
        'choose_hint' => 'Wählen Sie die Person, deren Arbeit endgültig an Nachfolgen geht. Je Bereich wählen Sie danach die Nachfolge.',
        'choose_empty' => 'Es gibt keine Person, deren Arbeit Sie übergeben dürfen.',
        'title' => 'Zuständigkeiten von :name übergeben',
        'intro' => 'Endgültige Übergabe ohne Austritt: Die gewählten Bereiche gehen dauerhaft an die Nachfolge; die Person bleibt Mitglied. Für eine vorübergehende Abwesenheit ist eine Vertretung der richtige Weg.',
        'areas' => 'Bereiche und Nachfolge',
        'hint' => 'Je Bereich eine eigene Nachfolge oder keine. Übertragen wird nur, was beim Speichern offen und der Person zugewiesen ist; die Anzahl je Bereich wird protokolliert.',
        'submit' => 'Endgültig übergeben',
        'empty' => 'Der Person ist zurzeit nichts zugewiesen, was übergeben werden kann.',
        'single_records' => 'Einzelne Aufträge übergeben Sie in der Auftragsliste über die Auswahl „Zuständigkeit ändern“.',
        'help' => 'Hilfe',
        'flash' => '{0} Nichts übergeben.|{1} Ein Vorgang von :name übergeben.|[2,*] :count Vorgänge von :name übergeben.',
        'area' => [
            'assigned_orders' => '{1} Ein zugewiesener Auftrag|[2,*] :count zugewiesene Aufträge',
            'assigned_tasks' => '{1} Eine offene Aufgabe|[2,*] :count offene Aufgaben',
            'assigned_service_tickets' => '{1} Ein zugewiesenes Service-Ticket|[2,*] :count zugewiesene Service-Tickets',
            'assigned_procedure_runs' => '{1} Ein laufender Prozedurlauf|[2,*] :count laufende Prozedurläufe',
        ],
        'error' => [
            'inactive' => 'Übergeben wird nur die Arbeit aktiver Mitglieder; beim Austritt gilt der Austritts-Dialog.',
            'successor' => 'Die Nachfolge muss ein anderes aktives Mitglied derselben Organisation sein.',
            'none' => 'Bitte wählen Sie mindestens für einen Bereich eine Nachfolge.',
        ],
    ],
    'reassign' => [
        'selected' => ':n Aufträge ausgewählt',
        'choose' => 'Neue Zuständigkeit wählen…',
        'submit' => 'Zuständigkeit ändern',
        'select_entry' => 'Auftrag „:title“ auswählen',
        'unknown_person' => 'Diese Person gibt es in Ihrer Organisation nicht oder sie ist deaktiviert.',
        'done' => '{0} Keine Zuständigkeit geändert.|{1} Ein Auftrag ist jetzt bei :name.|[2,*] :count Aufträge sind jetzt bei :name.',
        'skipped' => '{1} Ein Auftrag wurde übersprungen (kein Bearbeitungsrecht, schon zuständig oder Qualifikation fehlt).|[2,*] :count Aufträge wurden übersprungen (kein Bearbeitungsrecht, schon zuständig oder Qualifikation fehlt).',
    ],
    'demo' => [
        'note' => 'Beispiel: Vertretung während einer Fortbildung.',
    ],
];
