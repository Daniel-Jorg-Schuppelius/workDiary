<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'error' => [
        'lexoffice_contact_missing' => 'Kein Lexoffice-Kontakt für den Kunden — bitte zuerst den Kontakt synchronisieren.',
        'lexoffice_delivery_no_customer' => 'Auslieferung ohne Kunde kann nicht als Lieferschein übergeben werden.',
        'lexoffice_delivery_not_linked' => 'Mit dieser Auslieferung ist kein Lexoffice-Lieferschein verknüpft.',
        'lexoffice_dunning_not_invoice' => 'Eine Mahnung kann nur zu einer Rechnung erstellt werden.',
        'lexoffice_not_configured' => 'Lexoffice ist für diese Organisation nicht konfiguriert (API-Key fehlt).',
        'lexoffice_oc_no_customer' => 'Fertigungsauftrag ohne Kunde kann nicht als Auftragsbestätigung übergeben werden.',
        'lexoffice_oc_not_linked' => 'Mit diesem Fertigungsauftrag ist keine Lexoffice-Auftragsbestätigung verknüpft.',
        'lexoffice_quote_no_customer' => 'Fertigungsauftrag ohne Kunde kann nicht als Angebot übergeben werden.',
        'lexoffice_quote_not_linked' => 'Mit diesem Fertigungsauftrag ist kein Lexoffice-Angebot verknüpft.',
    ],
    'lexoffice' => [
        'introduction' => 'Unsere Lieferungen/Leistungen stellen wir Ihnen wie folgt in Rechnung.',
        'delivery_title' => 'Lieferschein',
    ],
];
