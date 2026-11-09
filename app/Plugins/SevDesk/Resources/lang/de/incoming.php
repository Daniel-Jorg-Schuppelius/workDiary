<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → sevDesk (Feature 163, MVP-1507).

return [
    'label' => 'sevDesk',
    'amount_differs' => 'In sevDesk gibt es den Beleg :number schon mit einem anderen Betrag. Bitte dort prüfen und danach die Übergabe erneut anstoßen.',
    'candidate' => 'In sevDesk liegt vermutlich schon derselbe Beleg (:voucher), aber ohne Rechnungsnummer ist das nicht sicher. Bitte verknüpfen oder einen neuen Beleg anlegen lassen.',
    'no_party' => 'Der Eingang ist noch keiner Partei zugeordnet.',
    'contact_missing' => 'Der Kontakt :party ließ sich in sevDesk weder eindeutig finden noch anlegen. Bitte den Kontakt in sevDesk verknüpfen und danach die Übergabe erneut anstoßen.',
    'no_account' => 'Für diese Richtung ist in den sevDesk-Einstellungen kein Buchungskonto hinterlegt.',
    'currency' => 'sevDesk erhält aus dem Rechnungseingang nur Belege in Euro. Bitte den Beleg in sevDesk erfassen; die nächste Übergabe verknüpft ihn über die Rechnungsnummer.',
    'rates' => 'Die Steuersätze passen nicht zu sevDesk (0, 7 oder 19 %) oder die Summen sind unvollständig. Bitte den Beleg in sevDesk erfassen; die nächste Übergabe verknüpft ihn über die Rechnungsnummer.',
    'tax_case' => 'Reverse Charge, innergemeinschaftliche Fälle und Ausfuhr bucht die Buchhaltung in sevDesk selbst. Bitte den Beleg dort erfassen; die nächste Übergabe verknüpft ihn über die Rechnungsnummer.',
    'xml_requires_update' => 'sevDesk nimmt XRechnungen erst ab der Buchhaltungsversion 2.0 an, und dem Eingang liegt kein PDF bei.',
    'settings' => [
        'transfer' => 'Rechnungseingang an sevDesk übergeben',
        'transfer_help' => 'Zugeordnete Eingänge gehen als Entwurf mit dem Original an sevDesk. Liegt ein Beleg mit derselben Nummer schon dort, wird er nur verknüpft.',
        'incoming_account' => 'Buchungskonto für Eingangsbelege (ID)',
        'outgoing_account' => 'Buchungskonto für Ausgangsbelege (ID)',
        'account_help' => 'ID des Kontos im sevDesk-Kontenkatalog: AccountDatev bei Buchhaltung 2.0, AccountingType bei 1.0. Ohne Konto wartet die Übergabe.',
    ],
];
