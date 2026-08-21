<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : warranty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Gewährleistungsfristen (Feature 115, MVP-604).
return [
    'title' => 'Gewährleistung',
    'subtitle' => 'Eigene Haftung und einforderbare Sub-Fristen nebeneinander',
    'empty' => 'Noch keine Gewährleistungsfrist erfasst.',
    'overridden' => '(abweichend)',
    'created' => 'Gewährleistungsfrist erfasst.',
    'closed' => 'Frist abgeschlossen.',
    'dialog_hint' => 'Ohne eigenes Enddatum ergibt es sich aus der Rechtsgrundlage. Fristbeginn ist der Abnahmetag — nicht das Rechnungs- oder Fertigstellungsdatum.',
    'override_reason' => 'Begründung für ein abweichendes Ende',
    'override_reason_hint' => 'Pflicht, sobald das Enddatum von der Rechtsgrundlage abweicht.',
    'custom_needs_end' => 'Bei freier Vereinbarung muss ein Enddatum angegeben werden.',
    'end_before_start' => 'Das Fristende muss nach dem Beginn liegen.',
    'override_needs_reason' => 'Ein vom Regelfall abweichendes Fristende braucht eine Begründung.',
    'not_open' => 'Diese Frist ist nicht mehr offen.',
    'action' => [
        'create' => 'Frist erfassen',
        'close' => 'Abschließen',
    ],
    'kpi' => [
        'owed' => 'Eigene Haftung',
        'owed_hint' => 'Fristen, die wir dem Auftraggeber schulden.',
        'claimable' => 'Einforderbar',
        'claimable_hint' => 'Fristen gegenüber Subunternehmern.',
        'expiring' => 'Läuft in 6 Monaten ab',
        'critical' => 'Sub-Frist endet zuerst',
        'critical_hint' => 'Nach Ablauf haftet man allein für einen Mangel, den ein anderer verursacht hat.',
    ],
    'critical' => [
        'heading' => 'Sub-Fristen enden vor der eigenen Haftung',
        'hint' => 'Jetzt prüfen und im Zweifel rügen — danach ist der Anspruch gegen den Subunternehmer verloren, die eigene Haftung läuft aber weiter.',
    ],
    'column' => [
        'side' => 'Seite',
        'project' => 'Projekt',
        'party' => 'Gegenpartei',
        'trade' => 'Gewerk',
        'basis' => 'Grundlage',
        'starts_on' => 'Beginn',
        'ends_on' => 'Ende',
        'status' => 'Status',
        'protocol' => 'Abnahmeprotokoll',
        'customer' => 'Kunde',
        'supplier' => 'Subunternehmer',
        'responsible' => 'Zuständig',
        'note' => 'Notiz',
    ],
    'filter' => [
        'side' => 'Seite',
        'status' => 'Status',
    ],
];
