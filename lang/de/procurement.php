<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Bestellungen',
    'subtitle' => 'Bestellungen, Wareneingang und Bestellvorschläge',
    'empty' => 'Keine Bestellungen vorhanden.',

    'action' => [
        'create' => 'Bestellung anlegen',
        'add_line' => 'Position hinzufügen',
        'submit' => 'Bestellen',
        'receive' => 'Wareneingang',
        'cancel' => 'Stornieren',
        'suggestions' => 'Bestellvorschläge',
        'apply' => 'Bestellungen erzeugen',
        'incoming' => 'Erwartete Eingänge',
    ],

    'field' => [
        'number' => 'Nr.',
        'supplier' => 'Lieferant',
        'warehouse' => 'Lager',
        'ordered_qty' => 'Bestellt',
        'received_qty' => 'Geliefert',
        'unit_price' => 'Einzelpreis',
        'article' => 'Artikel',
        'qty' => 'Menge',
        'expected_at' => 'Liefertermin',
        'note' => 'Notiz',
    ],

    'flash' => [
        'created' => 'Bestellung angelegt.',
        'line_added' => 'Position hinzugefügt.',
        'ordered' => 'Bestellung ausgelöst.',
        'received' => 'Wareneingang gebucht.',
        'cancelled' => 'Bestellung storniert.',
        'suggestions_applied' => ':count Bestellung(en) erzeugt.',
        'unknown_article' => 'Unbekannter Artikel.',
        'unknown_line' => 'Unbekannte Position.',
        'no_warehouse' => 'Kein Lager gewählt.',
    ],

    'ui' => [
        'suggestions_title' => 'Bestellvorschläge',
        'needed' => 'Bedarf',
        'suggested' => 'Vorschlag',
        'none' => 'Keine Vorschläge.',
        'select_warehouse' => 'Lager wählen',
        'incoming_title' => 'Erwartete Wareneingänge',
        'incoming_subtitle' => 'Offene Bestellzeilen bestellter Aufträge',
        'incoming_none' => 'Keine erwarteten Eingänge.',
        'open' => 'Offen',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'ordered' => 'Bestellt',
        'partially_received' => 'Teilweise geliefert',
        'received' => 'Geliefert',
        'cancelled' => 'Storniert',
    ],

    'advice_status' => [
        'announced' => 'Angekündigt',
        'received' => 'Vereinnahmt',
        'cancelled' => 'Storniert',
    ],

    'advice' => [
        'title' => 'Lieferavise',
        'announce' => 'Lieferavis erfassen',
        'reference' => 'Avis-/Lieferschein-Nr.',
        'announced_qty' => 'Angekündigt',
        'receive' => 'Wareneingang buchen',
        'flash' => [
            'announced' => 'Lieferavis erfasst.',
            'received' => 'Wareneingang aus Avis gebucht.',
            'cancelled' => 'Lieferavis storniert.',
        ],
    ],
];
