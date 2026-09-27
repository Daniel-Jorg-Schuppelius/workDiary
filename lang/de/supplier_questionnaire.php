<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : supplier_questionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lieferanten-Selbstauskunft (MVP-937).
return [
    'title' => 'Lieferanten-Selbstauskunft',
    'subtitle' => 'Fragebögen an Lieferanten (z. B. Nachhaltigkeit, Lieferkette, Qualität) mit Einmal-Link, Prüfung und Gültigkeit.',
    'card' => 'Selbstauskunft',
    'questionnaires' => 'Fragebögen',
    'recent' => 'Letzte Anfragen',
    'create' => 'Fragebogen anlegen',
    'edit' => 'Fragebogen bearbeiten',
    'save' => 'Speichern',
    'send' => 'Selbstauskunft anfragen',
    'send_hint' => 'Der Lieferant erhält einen Link per E-Mail, der :days Tage gilt. Eine offene Anfrage desselben Fragebogens wird zurückgezogen.',
    'open' => 'Öffnen',
    'none' => 'Noch keine Selbstauskunft angefragt.',
    'empty' => 'Noch keine Fragebögen.',
    'no_requests' => 'Noch keine Anfragen.',
    'inactive' => 'inaktiv',
    'valid_until' => 'gültig bis :date',
    'submitted_at' => 'eingereicht am :date',
    'waiting' => 'Wartet auf Antwort von :email (Link gültig bis :date).',
    'accept' => 'Annehmen',
    'reject' => 'Zur Nachbesserung zurückgeben',
    'public_title' => 'Selbstauskunft für :org',
    'public_submit' => 'Auskunft absenden',
    'public_thanks' => 'Vielen Dank, Ihre Angaben sind eingegangen.',
    'public_rework' => 'Bitte ergänzen Sie Ihre Angaben: :note',
    'field' => [
        'name' => 'Fragebogen',
        'description' => 'Hinweis für den Lieferanten',
        'questions' => 'Fragen',
        'validity_months' => 'Gültigkeit (Monate)',
        'is_active' => 'Aktiv',
        'requests' => 'Anfragen',
        'recipient_email' => 'E-Mail des Lieferanten',
        'sent_at' => 'Angefragt',
        'status' => 'Status',
        'valid_until' => 'Gültig bis',
        'note' => 'Anmerkung',
    ],
    'status' => [
        'sent' => 'Angefragt',
        'submitted' => 'Eingereicht',
        'accepted' => 'Angenommen',
        'rejected' => 'Zur Nachbesserung',
        'withdrawn' => 'Zurückgezogen',
    ],
    'flash' => [
        'saved' => 'Fragebogen gespeichert.',
        'sent' => 'Anfrage an :email gesendet.',
        'reviewed' => 'Prüfung gespeichert.',
    ],
    'error' => [
        'transition' => 'Die Auskunft kann nicht von „:from“ nach „:to“ wechseln.',
    ],
    'mail' => [
        'subject' => 'Selbstauskunft für :org',
        'body' => "Guten Tag,\n\n:org bittet Sie um die Selbstauskunft „:name“. Bitte füllen Sie den Fragebogen bis :until aus:\n:url\n\nVielen Dank.",
    ],
];
