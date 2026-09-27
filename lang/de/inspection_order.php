<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_order.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfaufträge an Dienstleister (MVP-938).
return [
    'title' => 'Prüfaufträge',
    'subtitle' => 'Fällige Prüfungen an einen Prüfdienstleister vergeben: Angebot, Annahme, Ergebnisse je Prüfmittel und Übernahme als Prüfnachweis.',
    'create' => 'Prüfauftrag anlegen',
    'send' => 'Auftrag senden',
    'open' => 'Öffnen',
    'empty' => 'Noch keine Prüfaufträge.',
    'no_schedules' => 'Keine offenen Prüftermine.',
    'due' => 'fällig :date',
    'accept' => 'Angebot annehmen',
    'reject' => 'Angebot ablehnen',
    'take_over' => 'Ergebnisse übernehmen',
    'taken_over' => 'übernommen',
    'cancel' => 'Auftrag stornieren',
    'confirm_cancel' => 'Auftrag stornieren? Die Prüftermine werden wieder freigegeben.',
    'public_title' => 'Prüfauftrag von :org',
    'public_offer' => 'Angebot abgeben',
    'public_submit_offer' => 'Angebot senden',
    'public_report' => 'Ergebnisse melden',
    'public_submit_report' => 'Ergebnisse senden',
    'public_reported' => 'Die Ergebnisse sind übermittelt. Vielen Dank.',
    'field' => [
        'title' => 'Bezeichnung',
        'supplier' => 'Prüfdienstleister',
        'recipient_email' => 'E-Mail des Dienstleisters',
        'items' => 'Prüfmittel',
        'status' => 'Status',
        'offer_amount' => 'Angebotspreis',
        'offer_planned_on' => 'Geplanter Termin',
        'offer_note' => 'Anmerkung zum Angebot',
        'asset' => 'Prüfmittel',
        'result' => 'Ergebnis',
        'performed_on' => 'Geprüft am',
        'valid_until' => 'Gültig bis',
        'certificate_no' => 'Zertifikatsnummer',
        'certificate_file' => 'Zertifikat (PDF)',
        'event' => 'Prüfnachweis',
    ],
    'status' => [
        'requested' => 'Angefragt',
        'offered' => 'Angebot liegt vor',
        'accepted' => 'Beauftragt',
        'reported' => 'Ergebnisse gemeldet',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
    ],
    'flash' => [
        'sent' => 'Prüfauftrag an :email gesendet.',
        'decided' => 'Entscheidung gespeichert.',
        'taken_over' => ':count Prüfnachweise übernommen.',
        'cancelled' => 'Auftrag storniert.',
        'offered' => 'Vielen Dank, Ihr Angebot ist eingegangen.',
        'reported' => 'Vielen Dank, die Ergebnisse sind eingegangen.',
    ],
    'error' => [
        'no_schedules' => 'Bitte mindestens einen offenen Prüftermin wählen.',
        'nothing_reported' => 'Bitte mindestens ein Ergebnis angeben.',
        'transition' => 'Der Auftrag kann nicht von „:from“ nach „:to“ wechseln.',
    ],
    'mail' => [
        'subject' => 'Prüfauftrag von :org: :title',
        'body' => "Guten Tag,\n\n:org bittet Sie um ein Angebot für die Prüfung „:title“ (:count Prüfmittel). Über den folgenden Link geben Sie Ihr Angebot ab und melden nach der Beauftragung die Ergebnisse:\n:url\n\nDer Link gilt bis :until.",
    ],
];
