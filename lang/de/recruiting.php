<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recruiting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Recruiting-Ergänzungen (MVP-924/925).
return [
    'suitability' => [
        'title' => 'Eignungsmatrix',
        'subtitle' => 'Kompetenz-Soll der Stelle „:title“ und Einschätzung der Bewerbungen — eine Hilfe zur Auswahl, keine automatische Entscheidung.',
        'requirements' => 'Kompetenz-Soll',
        'no_requirements' => 'Noch kein Soll festgelegt.',
        'no_competencies' => 'Es sind keine Kompetenzen angelegt (Kompetenzkatalog der Lernplattform).',
        'competency' => 'Kompetenz',
        'level' => 'Stufe',
        'add' => 'Soll hinzufügen',
        'remove' => 'Entfernen',
        'confirm_remove' => 'Diese Soll-Kompetenz entfernen?',
        'required' => 'Soll :level',
        'matrix' => 'Bewerbungen',
        'candidate' => 'Bewerbung',
        'gaps' => 'Lücken',
        'score' => 'Erfüllung',
        'no_applications' => 'Keine Bewerbungen zu dieser Stelle.',
        'rating_title' => 'Kompetenz-Einschätzung',
        'note' => 'Begründung (intern)',
        'save' => 'Speichern',
        'flash' => [
            'requirement' => 'Soll gespeichert.',
            'requirement_removed' => 'Soll entfernt.',
            'rated' => 'Einschätzung gespeichert.',
        ],
    ],
    'offer' => [
        'title' => 'Termine zur Auswahl anbieten',
        'slot' => 'Termin :n',
        'mode' => 'Gesprächsart',
        'duration' => 'Dauer (Minuten)',
        'valid_days' => 'Gültig (Tage)',
        'interviewer' => 'Gesprächsführung',
        'send' => 'Einladung senden',
        'ics_title' => 'Vorstellungsgespräch',
        'public_title' => 'Gesprächstermin wählen',
        'public_intro' => 'Bitte wählen Sie einen Termin (:minutes Minuten, :mode).',
        'choose' => 'Angebotene Termine',
        'confirm' => 'Termin verbindlich wählen',
        'confirmed_title' => 'Termin bestätigt',
        'confirmed_text' => 'Wir freuen uns auf das Gespräch am :when mit :org. Eine Bestätigung mit Kalendereintrag ist unterwegs.',
        'flash' => [
            'sent' => 'Einladung mit :count Terminen versendet.',
        ],
        'error' => [
            'no_email' => 'Für diese Bewerbung ist keine gültige E-Mail-Adresse hinterlegt.',
            'no_slots' => 'Bitte mindestens einen künftigen Termin angeben.',
            'unavailable' => 'Dieser Termin ist nicht mehr verfügbar.',
        ],
        'mail' => [
            'subject' => 'Ihr Vorstellungsgespräch: bitte Termin wählen (:title)',
            'body' => "Guten Tag :name,\n\nwir möchten Sie gern kennenlernen. Bitte wählen Sie bis :until einen der folgenden Termine:\n:slots\n\nTermin wählen: :url",
            'confirmed_subject' => 'Bestätigung Ihres Gesprächstermins',
            'confirmed_body' => "Guten Tag :name,\n\nIhr Gespräch findet am :when statt (:mode). Den Termin finden Sie im Anhang als Kalendereintrag.",
        ],
    ],
];
