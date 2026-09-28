<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Digitale Personalakte (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Personalakte',
        'title_mine' => 'Meine Personalakte',
        'nav' => 'Meine Personalakte',
        'subtitle' => 'Personalakte von :name — vertraulich, sichtbar nur für den Personalakten-Kreis und die betroffene Person.',
        'subtitle_mine' => 'Ihre eigene Personalakte: lesen, Lesebestätigungen abgeben und Unterlagen einreichen.',
        'back' => 'Zur Mitarbeiterliste',
        'empty' => 'Noch keine Dokumente in der Personalakte.',
        'confidential_fixed' => 'Personalakten sind immer vertraulich — der Schalter entfällt, das Merkmal wird erzwungen.',
        'retention_pending' => 'ab Austritt',
        'confirm_delete' => 'Dokument endgültig aus der Personalakte vernichten? Dateien und Versionen werden gelöscht; das Audit-Protokoll bleibt.',
        'field' => [
            'is_ack_required' => 'Lesebestätigung anfordern',
            'note' => 'Hinweis',
            'review_note' => 'Grund der Ablehnung',
            'title' => 'Titel',
            'category' => 'Kategorie',
            'validity' => 'Gültigkeit',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'retention_until' => 'Aufbewahrung bis',
            'version' => 'Version',
            'updated_at' => 'Aktualisiert',
            'description' => 'Beschreibung',
            'file' => 'Datei',
            'version_note' => 'Versionshinweis',
            'documents' => 'Dokumente',
        ],
        'action' => [
            'submit' => 'Unterlage einreichen',
            'accept' => 'Übernehmen',
            'reject' => 'Ablehnen',
            'acknowledge' => 'Gelesen',
            'upload' => 'Dokument aufnehmen',
            'edit' => 'Bearbeiten',
            'save' => 'Speichern',
            'download' => 'Herunterladen',
            'versions' => 'Versionen',
            'delete' => 'Vernichten',
        ],
        'flash' => [
            'submitted' => 'Unterlage wurde eingereicht; die Personalabteilung entscheidet über die Aufnahme.',
            'accepted' => 'Einreichung wurde in die Personalakte übernommen.',
            'rejected' => 'Einreichung wurde abgelehnt.',
            'acknowledged' => 'Lesebestätigung wurde gespeichert.',
            'created' => 'Dokument wurde in die Personalakte aufgenommen.',
            'updated' => 'Personalakten-Dokument wurde aktualisiert.',
        ],
        'hint' => [
            'ack' => 'Die betroffene Person bestätigt das Lesen in ihrer Akte; eine neue Version verlangt eine neue Bestätigung.',
            'submit' => 'Die Personalabteilung prüft die Unterlage und nimmt sie in Ihre Akte auf oder lehnt sie mit Begründung ab.',
        ],
        'ack' => [
            'open' => 'Lesebestätigung offen',
            'done' => 'Gelesen am :date',
            'confirm' => 'Bestätigen Sie, dass Sie dieses Dokument gelesen haben?',
        ],
        'submission' => [
            'title' => 'Einreichungen',
            'subtitle' => 'Von Mitarbeitenden eingereichte Unterlagen, die auf die Aufnahme in die Personalakte warten.',
            'person' => 'Person',
            'submitted_at' => 'Eingereicht am',
            'empty' => 'Keine offenen Einreichungen.',
            'reason' => 'Abgelehnt: :reason',
        ],
        'error' => [
            'ack_not_requested' => 'Für dieses Dokument ist keine Lesebestätigung angefordert.',
            'submission_decided' => 'Über diese Einreichung wurde bereits entschieden.',
            'submission_file_missing' => 'Die eingereichte Datei ist nicht mehr vorhanden.',
        ],
        'notification' => [
            'ack_requested_title' => 'Lesebestätigung erbeten: :title',
            'ack_requested_message' => 'Bitte bestätigen Sie in Ihrer Personalakte, dass Sie das Dokument gelesen haben.',
            'submission_received_title' => 'Neue Unterlage für eine Personalakte eingereicht',
            'submission_received_message' => 'Die Einreichung wartet auf Übernahme oder Ablehnung.',
            'submission_accepted_title' => 'In die Personalakte übernommen: :title',
            'submission_rejected_title' => 'Nicht in die Personalakte übernommen: :title',
            'submission_rejected_message' => 'Begründung: :reason',
        ],
    ],
    // Personal-Kapazität (MVP-940).
    'capacity' => [
        'title' => 'Personal-Kapazität',
        'button' => 'Kapazität',
        'subtitle' => 'Geplanter Bedarf (zugewiesene Aufträge) gegen die Sollzeit der Teammitglieder je Woche; Feiertage und genehmigter Urlaub sind abgezogen.',
        'team' => 'Team',
        'week' => 'Woche ab :date',
        'members' => ':count Mitglieder',
        'empty' => 'Noch keine Teams.',
        'hint' => 'Angaben in Stunden: geplant / verfügbar.',
        'open_requisitions' => 'Offene Stellen gesamt: :count.',
    ],
    // Vertretungen beim Austritt (MVP-941).
    'offboarding' => [
        'deputies' => 'Vertretungen neu besetzen',
        'deputies_hint' => 'Diese Personen haben das austretende Mitglied als Vertretung eingetragen. Ohne Auswahl endet die Vertretung.',
        'deputy_for' => 'Neue Vertretung für :name',
        'no_deputy' => '— keine Vertretung —',
    ],
    // Arbeitsvertrag zur Unterschrift (MVP-939).
    'employment' => [
        'title' => 'Arbeitsvertrag zur Unterschrift',
        'intro' => 'Der Vertrag geht per Link an die Person; danach zeichnet die Organisation gegen. Die unterschriebene Fassung landet in der Personalakte.',
        'send' => 'Zur Unterschrift senden',
        'default_title' => 'Arbeitsvertrag :name',
        'default_declaration' => 'Ich habe den Arbeitsvertrag gelesen und stimme ihm zu.',
        'filed_note' => 'Unterschriebene Fassung aus Vertrag :number.',
        'field' => [
            'title' => 'Bezeichnung',
            'starts_on' => 'Beginn',
            'email' => 'E-Mail der Person',
            'declaration_text' => 'Zustimmungserklärung',
            'file' => 'Vertrag (PDF)',
        ],
        'flash' => [
            'sent' => 'Arbeitsvertrag an :email zur Unterschrift gesendet.',
        ],
        'error' => [
            'email' => 'Bitte eine E-Mail-Adresse angeben.',
        ],
    ],
];
