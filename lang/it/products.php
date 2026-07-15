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
        'index' => 'Prodotti',
        'subtitle' => 'Livello tipo produttore + modello: raggruppa articoli e asset dello stesso prodotto.',
        'create' => 'Crea prodotto',
        'edit' => 'Modifica prodotto',
        'empty' => 'Nessun prodotto presente.',
        'empty_search' => 'Nessun prodotto trovato per «:q».',
    ],
    'field' => [
        'basics' => 'Dati anagrafici',
        'manufacturer' => 'Produttore',
        'model' => 'Modello',
        'name' => 'Nome visualizzato',
        'name_placeholder' => 'Produttore modello',
        'name_help' => 'Lasciare vuoto per «produttore modello».',
        'product_group' => 'Gruppo di prodotti',
        'no_group' => '— nessuno —',
        'articles' => 'Articoli',
        'assets' => 'Asset',
        'status' => 'Stato',
        'notes' => 'Note',
        'product' => 'Prodotto',
        'no_product' => '— nessun prodotto —',
        'product_help' => 'Assegnazione del tipo (produttore modello); precompila produttore/modello.',
    ],
    'action' => [
        'create' => 'Crea prodotto',
        'save' => 'Salva',
        'edit' => 'Modifica',
        'delete' => 'Elimina',
        'delete_confirm' => 'Eliminare davvero il prodotto? Articoli e asset restano e perdono solo l\'assegnazione del tipo.',
    ],
    'flash' => [
        'created' => 'Prodotto creato.',
        'updated' => 'Prodotto aggiornato.',
        'deleted' => 'Prodotto eliminato.',
    ],
];
