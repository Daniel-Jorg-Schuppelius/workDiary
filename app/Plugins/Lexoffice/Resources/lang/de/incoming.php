<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → Lexware Office (Feature 163, MVP-1111).

return [
    'label' => 'Lexware Office',
    'amount_differs' => 'In Lexware gibt es den Beleg :number schon mit einem anderen Betrag. Bitte dort prüfen und danach die Übergabe erneut anstoßen.',
    'remark' => 'Aus dem Rechnungseingang von WorkDiary (:sender).',
    'no_party' => 'Der Eingang ist noch keiner Partei zugeordnet.',
    'contact_missing' => 'Der Kontakt :party ließ sich in Lexware weder finden noch anlegen.',
    'contact_role' => 'Lexware lehnt den Kontakt :party wegen seiner Rolle ab. Bitte in Lexware die Rolle Lieferant bzw. Kunde ergänzen und danach die Übergabe erneut anstoßen.',
    'header_only' => [
        'no_category' => 'Ohne Beträge übergeben: Für die Partei und in den Einstellungen ist keine Buchungskategorie hinterlegt.',
        'currency' => 'Ohne Beträge übergeben: Lexware nimmt Belege nur in Euro an.',
        'rates' => 'Ohne Beträge übergeben: Die Steuersätze passen nicht zu Lexware oder die Summen sind unvollständig.',
    ],
    'settings' => [
        'transfer' => 'Rechnungseingang an Lexware Office übergeben',
        'transfer_help' => 'Zugeordnete Eingänge gehen als „zu prüfen“ an Lexware. Liegt ein Beleg mit derselben Nummer schon dort, wird er nur verknüpft.',
        'incoming_category' => 'Vorgabe-Kategorie für Eingangsbelege',
        'outgoing_category' => 'Vorgabe-Kategorie für Ausgangsbelege',
        'category_help' => 'Ohne Kategorie gehen Belege ohne Beträge an Lexware. Eine Kategorie am Lieferanten oder Kunden hat Vorrang. Die Liste stammt aus dem Kategorie-Abgleich.',
        'no_category' => '— keine —',
    ],
    'category' => [
        'title' => 'Buchungskategorie in Lexware',
        'help' => 'Belege aus dem Rechnungseingang gehen mit dieser Kategorie an Lexware, statt mit der Vorgabe aus den Einstellungen.',
        'save' => 'Kategorie speichern',
        'saved' => 'Buchungskategorie gespeichert.',
        'cleared' => 'Buchungskategorie entfernt; es gilt die Vorgabe aus den Einstellungen.',
    ],
];
