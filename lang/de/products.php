<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : products.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370).
return [
    'title' => [
        'index' => 'Produkte',
        'subtitle' => 'Typ-Ebene Hersteller-Modell: bündelt Artikel und Assets desselben Produkts.',
        'create' => 'Produkt anlegen',
        'edit' => 'Produkt bearbeiten',
        'empty' => 'Noch keine Produkte angelegt.',
        'empty_search' => 'Keine Produkte für „:q" gefunden.',
    ],
    'field' => [
        'basics' => 'Stammdaten',
        'manufacturer' => 'Hersteller',
        'model' => 'Modell',
        'name' => 'Anzeigename',
        'name_placeholder' => 'Hersteller Modell',
        'name_help' => 'Leer lassen für „Hersteller Modell".',
        'product_group' => 'Produktgruppe',
        'no_group' => '— keine —',
        'articles' => 'Artikel',
        'assets' => 'Assets',
        'status' => 'Status',
        'notes' => 'Notizen',
        'product' => 'Produkt',
        'no_product' => '— kein Produkt —',
        'product_help' => 'Typ-Zuordnung (Hersteller-Modell); vererbt Hersteller/Modell als Vorbelegung.',
    ],
    'action' => [
        'create' => 'Produkt anlegen',
        'save' => 'Speichern',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',
        'delete_confirm' => 'Produkt wirklich löschen? Artikel und Assets bleiben erhalten und verlieren nur die Typ-Zuordnung.',
    ],
    'flash' => [
        'created' => 'Produkt angelegt.',
        'updated' => 'Produkt aktualisiert.',
        'deleted' => 'Produkt gelöscht.',
    ],
];
