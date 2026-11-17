<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming_pdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammel-PDF und GiroCode im Rechnungseingang (Feature 208, MVP-1662/1663).
return [
    'split' => [
        'title' => 'Sammel-PDF aufteilen',
        'card_title' => 'Sammel-PDF',
        'gate' => 'Sammel-PDF: erst aufteilen oder als eine Rechnung bestätigen.',
        'part_description' => 'Teil von „:title“, Seiten :from–:to',
        'document_title' => 'Sammel-PDF :file (:count Rechnungen)',
        'document_description' => 'Unverändertes Original, aufgeteilt in :count Rechnungseingänge.',
        'state' => [
            'pending' => 'Die Seiten werden auf mehrere Rechnungen geprüft. Bis dahin warten Übergabe und Zahlung.',
            'proposed' => 'Die PDF enthält vermutlich :count Rechnungen. Bis zur Entscheidung warten Übergabe und Zahlung.',
            'single' => 'Die :pages Seiten gehören vermutlich zu einer Rechnung. Liegen doch mehrere darin, teilen Sie sie von Hand.',
            'dismissed' => 'Als eine Rechnung bestätigt.',
            'splitting' => 'Die Aufteilung läuft im Hintergrund.',
            'too_large' => 'Mehr als :max Seiten — keine Prüfung auf mehrere Rechnungen.',
            'failed' => 'Die Prüfung auf mehrere Rechnungen ist gescheitert; der Eingang gilt als eine Rechnung.',
        ],
        'action' => [
            'reset' => 'Aufteilung zurücksetzen',
            'reset_confirm' => 'Die Aufteilung läuft ungewöhnlich lange. Zurücksetzen und erneut versuchen?',
            'open' => 'Grenzen prüfen',
            'split' => 'Aufteilen',
            'dismiss' => 'Ist eine Rechnung',
            'dismiss_confirm' => 'Den Eingang als eine Rechnung behalten? Übergabe und Zahlung laufen danach weiter.',
        ],
        'dialog' => [
            'intro' => 'Markieren Sie die Seiten, auf denen eine neue Rechnung beginnt. Jede Rechnung wird ein eigener Eingang mit eigener Teil-PDF; das Original bleibt unverändert.',
            'page' => 'Seite :page',
            'starts_here' => 'Neue Rechnung beginnt hier',
            'first_page' => 'Erste Rechnung',
            'no_image' => 'Keine Vorschau',
            'images_missing' => 'Vorschaubilder fehlen — die Seiten lassen sich trotzdem markieren.',
            'signals' => 'Hinweise',
        ],
        'signal' => [
            'page_one' => '„Seite 1 von …“',
            'continued' => 'Folgeseite',
            'new_number' => 'neue Rechnungsnummer',
            'same_number' => 'gleiche Rechnungsnummer',
            'new_sender' => 'neuer Absender',
            'after_total' => 'nach Summenblock',
            'new_letterhead' => 'anderer Briefkopf',
        ],
        'blocked' => [
            'split' => 'Der Eingang ist als Sammel-PDF aufgeteilt — bearbeitet werden seine Teile.',
            'part' => 'Dieser Eingang ist schon ein Teil einer aufgeteilten Sammel-PDF.',
            'no_bundle' => 'Für diesen Eingang gibt es keine offene Sammel-PDF-Prüfung.',
            'pending' => 'Die Seiten werden noch geprüft.',
            'running' => 'Die Aufteilung läuft bereits.',
            'too_large' => 'Die PDF ist für die Aufteilung zu groß.',
            'status' => 'Nur offene Eingänge (empfangen oder mit Rückfrage) lassen sich aufteilen.',
            'transferred' => 'Der Eingang ist schon an die Buchhaltung übergeben — dort aufteilen.',
            'payment' => 'Der Eingang steht in einem Zahllauf.',
            'linked' => 'Der Eingang hat Einbehalte oder Verknüpfungen (Anlage, offener Posten, Regress, Gutschrift).',
            'hold' => 'Für den Eingang gilt ein Aufbewahrungsvermerk.',
        ],
        'error' => [
            'too_many_parts' => 'Höchstens :max Rechnungen je Sammel-PDF.',
            'parts_too_large' => 'Die Teil-PDFs wären zusammen zu groß — die Datei bitte vor dem Hochladen aufteilen.',
            'stalled' => 'Die Aufteilung ist nicht fertig geworden und wurde zurückgesetzt.',
            'invalid_page' => 'Die Seitenangaben passen nicht zum Dokument.',
            'single' => 'Markieren Sie mindestens eine weitere Startseite — sonst bleibt es eine Rechnung.',
            'original_missing' => 'Das Original fehlt im DMS.',
            'original_changed' => 'Das abgelegte Original stimmt nicht mehr mit dem Hash des Eingangs überein — keine Aufteilung.',
            'tool_missing' => 'Zum Herauslösen der Seiten fehlt ein PDF-Werkzeug (mutool, pdftk oder poppler).',
            'extract_failed' => 'Die Seiten :from–:to ließen sich nicht herauslösen.',
            'nothing_created' => 'Alle Teile liegen schon als Eingang vor — nichts aufgeteilt.',
            'failed' => 'Die Aufteilung ist gescheitert.',
            'original_protected' => 'Das Original einer Sammel-PDF bleibt, solange einer ihrer Teile besteht.',
        ],
        'flash' => [
            'reset' => 'Aufteilung zurückgesetzt.',
            'done' => 'Sammel-PDF in :count Rechnungen aufgeteilt.',
            'queued' => 'Aufteilung in :count Rechnungen gestartet — die Eingänge erscheinen gleich in der Liste.',
            'dismissed' => 'Als eine Rechnung behalten.',
        ],
        'parts' => [
            'title' => 'Teile dieser Sammel-PDF',
            'intro' => 'Das Original bleibt unverändert; jeder Teil ist ein eigener Eingang mit eigener Teil-PDF.',
            'pages' => 'Seiten :from–:to',
            'protected' => 'Das Original lässt sich nicht löschen, solange Teile bestehen.',
        ],
        'part_of' => 'Teil einer Sammel-PDF, Seiten :from–:to.',
        'open_original' => 'Original öffnen',
    ],
    'girocode' => [
        'title' => 'GiroCode',
        'pending' => 'Der GiroCode wird im Hintergrund gelesen.',
        'status' => [
            'found' => 'GiroCode gelesen (Seite :page).',
            'none' => 'Kein GiroCode gefunden.',
            'unreadable' => 'GiroCode nicht lesbar.',
            'invalid' => 'QR-Code mit GiroCode-Kennung, aber ungültigem Inhalt.',
        ],
        'reason' => [
            'page_size' => 'Die Seitenmaße sind nicht lesbar oder zu groß zum Rastern.',
            'changed' => 'Das abgelegte Original stimmt nicht mehr mit dem Eingang überein.',
            'tool_missing' => 'Das Lesewerkzeug (zbarimg) fehlt auf dem Server.',
            'too_large' => 'Die Datei ist zum Lesen zu groß.',
            'scan_failed' => 'Beim Lesen trat ein Fehler auf.',
        ],
        'field' => [
            'name' => 'Empfänger',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'amount' => 'Betrag',
            'reference' => 'Verwendungszweck',
        ],
        'iban_applied' => 'Die IBAN aus dem GiroCode wurde übernommen, weil der Beleg keine nannte.',
        'finding' => [
            'new_bank_details' => 'Die IBAN stammt nur aus dem GiroCode; für den Lieferanten ist keine Bankverbindung hinterlegt, mit der sie sich vergleichen ließe.',
            'iban_differs_invoice' => 'Die IBAN im GiroCode weicht von der IBAN der Rechnung ab.',
            'iban_differs_supplier' => 'Die IBAN im GiroCode gehört zu keiner hinterlegten Bankverbindung des Lieferanten.',
            'multiple_codes' => 'Der Beleg trägt mehrere GiroCodes mit verschiedenen IBANs.',
            'iban_invalid' => 'Die Prüfziffer der IBAN im GiroCode stimmt nicht — die IBAN wurde nicht übernommen.',
            'amount_differs' => 'Der Betrag im GiroCode weicht vom Bruttobetrag ab.',
            'name_differs' => 'Der Empfängername im GiroCode passt nicht zum Lieferanten.',
            'unreadable' => 'GiroCode nicht lesbar.',
            'invalid' => 'GiroCode ungültig.',
        ],
        'blocking' => 'Abweichende Bankverbindung — ein bekanntes Betrugsmuster. Im Zahllauf gesperrt, bis jemand mit Freigaberecht die Abweichung bestätigt.',
        'confirmed' => 'Abweichung bestätigt am :date.',
    ],
];
