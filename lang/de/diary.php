<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Niedrig',
        'normal' => 'Normal',
        'high' => 'Hoch',
        'urgent' => 'Dringend',
    ],
    'location_mode' => [
        'onsite' => 'Vor Ort',
        'remote' => 'Remote',
        'hybrid' => 'Hybrid',
    ],
    'mode' => [
        'fixed' => 'Terminiert',
        'deadline' => 'Deadline',
        'window' => 'Zeitfenster',
        'recurring' => 'Wiederkehrend',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Geplant',
        'Accepted' => 'Angenommen',
        'InProgress' => 'In Bearbeitung',
        'WaitingCustomer' => 'Wartet auf Rückmeldung',
        'WaitingMaterial' => 'Wartet auf Material',
        'Completed' => 'Abgeschlossen',
        'AcceptedFinal' => 'Abgenommen',
        'Invoiced' => 'Berechnet',
        'Cancelled' => 'Storniert',
        'InReview' => 'Zur Prüfung',
        'ReviewQueried' => 'Rückfrage',
        'ReviewApproved' => 'Freigegeben',
    ],
    'planned_duration' => [
        'label' => 'Geplante Dauer (HH:MM)',
        'hint' => 'Leer: Länge des Zeitfensters bzw. Dauer des Termins. Gilt als Plan in Plan/Ist, Auftragstypanalyse und Personal-Kapazität.',
        'format' => 'Bitte geben Sie die geplante Dauer als Stunden:Minuten an, z. B. 1:30.',
        'range' => 'Die geplante Dauer muss zwischen 0:01 und 168:00 liegen.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Plan/Ist',
        'no_plan' => 'ohne Plan',
        'own_only' => 'nur eigene Zeiten',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Annahme zurückgeben',
            'revert_invoiced' => 'Berechnung zurücknehmen',
            'submit_review' => 'Zur Prüfung vorlegen',
            'withdraw_review' => 'Vorlage zurückziehen',
            'approve_review' => 'Freigeben',
            'query_review' => 'Rückfrage stellen',
            'resubmit_review' => 'Erneut vorlegen',
            'approve_and_complete' => 'Freigeben und abschließen',
        ],
        'reject' => [
            'title' => 'Annahme zurückgeben',
            'legend' => 'Rückgabe',
            'hint' => 'Der Auftrag steht danach wieder auf „Geplant“ und kann von einer anderen Person angenommen werden. Die Begründung steht im Verlauf.',
        ],
        'handover' => [
            'legend' => 'Abnahme',
            'accepted_by' => 'Abgenommen von (Name beim Kunden)',
            'hint' => 'Das Protokoll trägt keine Kundenunterschrift. Bitte nennen Sie die Person, die beim Kunden abgenommen hat.',
        ],
        'accepted_final_by' => 'Abgenommen von :name am :date',
        'invoiced_with' => 'Berechnet mit :reference',
        'invoiced' => [
            'transfer_reference' => 'Übergabe Nr. :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Auftrag storniert: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'Der Auftrag lässt sich erst abschließen, wenn keine Stoppuhr mehr läuft. Noch aktiv: :names.',
        ],
    ],
    // Periodenaufträge mit interner Prüfung (Feature 198), Folgeperiode und Demo.
    'period' => [
        'label' => 'Periode',
        'badge' => 'Periode: :period',
        'placeholder' => '2026, 2026-H1, 2026-Q1, 2026-03',
        'hint' => 'Jahr (2026), Halbjahr (2026-H1), Quartal (2026-Q1) oder Monat (2026-03).',
        'invalid' => 'Bitte geben Sie die Periode als Jahr (2026), Halbjahr (2026-H1), Quartal (2026-Q1) oder Monat (2026-03) an.',
        'kind_mismatch' => 'Diese Eintragsart verlangt eine Periode der Art: :kinds.',
    ],
    'product_group' => [
        'label' => 'Produktgruppe',
        'invalid' => 'Diese Produktgruppe gibt es in Ihrer Organisation nicht.',
    ],
    'review' => [
        'required_badge' => 'Prüfpflicht',
        'reviewer_named' => 'Prüfung durch :name',
        'submitted_by' => 'Vorgelegt von :name am :date',
        'reviewer_label' => 'Prüfende Person',
        'reviewer_any' => 'Jede Person mit Prüfrecht',
        'reviewer_hint' => 'Ohne Auswahl prüft jede Person mit dem Recht „Aufträge prüfen“ – nie die Person, die vorlegt.',
        'waive_action' => 'Prüfpflicht abschalten',
        'require_action' => 'Prüfpflicht einschalten',
        'waive_hint' => 'Ohne Prüfpflicht wird der Auftrag direkt abgeschlossen. Die Änderung steht mit Ihrer Begründung im Verlauf und im Audit-Protokoll.',
        'require_hint' => 'Mit Prüfpflicht wird der Auftrag vor dem Abschluss zur Prüfung vorgelegt und von einer zweiten Person freigegeben.',
        'approve_complete_hint' => 'Die Freigabe ist hier zugleich der Abschluss: Sie geben frei und schließen den Auftrag in einem Schritt ab.',
        'query' => [
            'title' => 'Rückfrage zur Prüfung',
            'hint' => 'Der Auftrag geht mit Ihrer Frage zurück an die Bearbeitung und wird danach erneut vorgelegt. Die Frage steht im Verlauf.',
            'legend' => 'Rückfrage',
            'question' => 'Ihre Frage',
        ],
        'error' => [
            'own_submission' => 'Wer einen Auftrag zur Prüfung vorgelegt hat, kann ihn nicht selbst freigeben oder zurückfragen.',
            'requirement_locked' => 'Die Prüfpflicht lässt sich im Status „:status“ nicht mehr ändern.',
            'question_required' => 'Eine Rückfrage braucht einen Text.',
            'not_in_review' => 'Der Auftrag liegt nicht mehr zur Prüfung vor.',
        ],
        'flash' => [
            'waived' => 'Prüfpflicht für diesen Auftrag abgeschaltet.',
            'required' => 'Prüfpflicht für diesen Auftrag eingeschaltet.',
        ],
        'inbox_reference' => 'Vorgelegt von :name am :date',
        'inbox_type' => 'Auftragsprüfung',
        'queue' => [
            'title' => 'Zur Prüfung',
            'subtitle' => 'Aufträge, die Sie prüfen dürfen – nach Fälligkeit, dann nach Vorlagedatum. Eigene Vorlagen erscheinen hier nicht.',
            'empty_title' => 'Nichts zu prüfen',
            'empty_message' => 'Derzeit liegt kein Auftrag zur Prüfung bei Ihnen.',
            'column' => [
                'order' => 'Auftrag',
                'submitted' => 'Vorgelegt',
                'actions' => 'Aktionen',
            ],
        ],
        'widget' => [
            'title' => 'Zur Prüfung',
            'description' => 'Aufträge, die zur internen Prüfung bei Ihnen liegen – nach Fälligkeit, dann nach Vorlagedatum.',
            'open_queue' => 'Alle →',
        ],
    ],
    'follow_up' => [
        'action' => 'Folgeperiode anlegen',
        'title' => 'Folgeperiode :period anlegen',
        'submit' => 'Anlegen',
        'copied' => 'Übernommen werden Kunde, Eintragsart, Produktgruppe, Zuständigkeit, Prüfpflicht und prüfende Person sowie die Prozeduren als neue Läufe. Offene Merker erscheinen im neuen Auftrag.',
        'not_copied' => 'Nicht übernommen werden Inhalt und Notizen, Zeiten und das Ergebnis.',
        'exists_hint' => 'Für :period gibt es diesen Auftrag schon:',
        'content' => 'Folgeperiode :period zu Auftrag #:id.',
        'error' => [
            'no_series' => 'Eine Folgeperiode gibt es nur für Aufträge mit Periode, Kunde und Eintragsart.',
            'exists' => 'Für diese Periode gibt es den Auftrag schon (#:id).',
            'selection' => 'Bitte wählen Sie Eintragsart und Periode.',
        ],
        'flash' => [
            'created' => 'Auftrag für :period angelegt.',
            'bulk' => ':created Aufträge für :period angelegt, :skipped übersprungen.',
        ],
        'bulk' => [
            'title' => 'Folgeperiode gesammelt',
            'subtitle' => 'Für alle Aufträge einer Eintragsart und Periode den Auftrag der nächsten Periode anlegen – erst die Vorschau, dann die Anlage.',
            'choose_title' => 'Eintragsart und Periode wählen',
            'choose_message' => 'Die Vorschau zeigt, welche Aufträge entstehen und welche es schon gibt.',
            'none_title' => 'Keine Aufträge',
            'none_message' => 'Für diese Eintragsart gibt es in :period keine Aufträge.',
            'summary' => ':count Aufträge in :from, davon :creatable neu für :to.',
            'limit' => 'Es werden höchstens :limit Aufträge auf einmal verarbeitet.',
            'submit' => ':count Aufträge anlegen',
            'column' => [
                'source' => 'Auftrag',
                'target' => 'Neue Periode',
                'result' => 'Ergebnis',
            ],
            'result' => [
                'create' => 'wird angelegt',
                'exists' => 'schon vorhanden',
                'no_customer' => 'ohne Kunde',
                'duplicate_source' => 'doppelt in der Vorschau',
            ],
        ],
    ],
    'demo' => [
        'entry_type' => 'Jahresauftrag (Demo)',
        'title' => 'Jahresauftrag :year',
        'content' => 'Jahresauftrag zur Vorführung: Unterlagen sammeln, bearbeiten, zur Prüfung vorlegen.',
        'approval_note' => 'Geprüft, keine Anmerkungen.',
        'summary' => 'Unterlagen vollständig, Ergebnis übermittelt.',
        'carry_forward' => 'Nächstes Jahr die Belege bis Ende Februar anfordern.',
    ],
    'entry_type' => [
        'legend' => 'Periode und Prüfung',
        'description' => 'Periodenaufträge (Jahr, Halbjahr, Quartal, Monat) und interne Prüfung vor dem Abschluss.',
        'requires_period' => 'Periode beim Anlegen Pflicht',
        'requires_period_hint' => 'Ohne Auswahl ist die Periode freiwillig. Bei mehreren Arten passt jede davon.',
        'requires_review' => 'Prüfpflicht vor dem Abschluss (Vier-Augen-Prinzip)',
    ],
];
