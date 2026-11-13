<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Material ↔ Artikel (MVP-904).
    'article' => [
        'label' => 'Artikel',
        'none' => '— kein Artikel —',
        'hint' => 'Über den Artikel ordnet die Lieferantenauswertung den Verbrauch einem Lieferanten zu.',
        'link' => 'Artikel zuordnen',
        'link_hint' => 'Materialien ohne Artikel über gleiche SKU bzw. Artikelnummer zuordnen; mehrdeutige Treffer bleiben offen.',
        'linked' => ':count Materialien einem Artikel zugeordnet.',
    ],
    'stock' => [
        'insufficient' => 'Im Lager „:warehouse“ sind nur :available :unit verfügbar. Für einen Abgang in den Negativbestand „Negativen Bestand zulassen“ wählen.',
        'negative_forbidden' => 'Einen Abgang in den Negativbestand darf nur buchen, wer das Recht „Negative Bestände freigeben“ hat.',
        'serial_required' => 'Der Artikel ist seriennummernpflichtig. Entnehmen Sie ihn über das Lager mit Seriennummer, oder erfassen Sie den Verbrauch ohne Materialstamm.',
        'booking_failed' => 'Der Lagerabgang ließ sich nicht buchen: :reason',
        'return_failed' => 'Die Rückbuchung ins Lager ließ sich nicht buchen: :reason',
        'usage_gone' => 'Den Materialverbrauch zu diesem Antrag gibt es nicht mehr.',
        'allow_negative_hint' => 'Nur nötig, wenn ein lagergeführter Artikel im Standardlager nicht genug Bestand hat.',
        'notice' => ':status · Lager „:warehouse“',
        'current' => 'Bestand: :notice',
    ],
    // Variante je Verbrauch (MVP-1690).
    'variant' => [
        'default' => '— Standardvariante —',
        'hint' => 'Nur bei lagergeführten Artikeln mit mehreren Varianten. Leer bucht die Standardvariante; ohne Einzelpreis gilt der eigene Preis der Variante, sonst der des Materials.',
        'label' => 'Variante: :variant',
        'mismatch' => 'Die Variante gehört nicht zum Artikel des gewählten Materials.',
        'inactive' => 'Diese Variante ist nicht aktiv und kann nicht neu gewählt werden.',
    ],
];
