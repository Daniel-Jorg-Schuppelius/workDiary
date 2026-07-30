<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recipes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Ricette (MVP-455): gestione ricette neutra + estensione catering.
return [
    'title' => [
        'materials' => 'Ricetta / fabbisogno materiali',
        'party' => 'Catering: resa base & porzioni',
        'allergen_overrides' => 'Deviazioni allergeni (con motivazione)',
        'allergens' => 'Allergeni',
        'plan' => 'Scalatura & costi pianificati',
    ],
    'hint' => [
        'version' => 'Versione :version',
        'readonly' => 'pubblicato — stato immutabile',
        'materials' => 'Quantità fisse, quantità per unità/porzione o rapporti di miscela; gli utensili restano separati dal consumo. Le posizioni sono modificabili solo nelle bozze.',
        'ratio_input' => 'Per «quota proporzionale» il valore indica la quota della quantità target (somma delle quote = quantità totale).',
        'party' => 'La resa base documenta quante porzioni produce la preparazione standard; le quantità per unità si registrano per porzione.',
    ],
    'empty' => [
        'no_version' => 'Nessuna versione — creare prima una bozza.',
        'no_materials' => 'Nessuna posizione registrata.',
    ],
    'field' => [
        'position' => 'Pos.',
        'article' => 'Articolo/ingrediente',
        'article_placeholder' => '… seleziona articolo',
        'kind' => 'Tipo di quantità',
        'quantity' => 'Quantità',
        'quantity_or_ratio' => 'Quantità / quota',
        'unit' => 'Unità',
        'waste' => 'Sfrido %',
        'tool' => 'Utensile',
        'tool_yes' => 'Utensile',
        'actions' => 'Azioni',
        'base_portions' => 'Resa base (porzioni)',
        'base_yield' => 'Quantità prodotta',
        'yield_unit' => 'Unità prodotta',
        'allergen_added' => 'Dichiarare in aggiunta',
        'allergen_removed' => 'Non dichiarare',
        'override_reason' => 'Motivazione della deviazione',
        'portions' => 'Porzioni',
        'demand' => 'Fabbisogno',
        'cost' => 'Costi pianificati',
    ],
    'kind' => [
        'fixed' => 'fisso per preparazione',
        'per_unit' => 'per porzione/unità',
        'ratio' => 'quota proporzionale',
    ],
    'action' => [
        'add' => 'Aggiungi posizione',
        'remove' => 'Rimuovi',
        'save_profile' => 'Salva profilo',
        'save_allergens' => 'Salva allergeni',
        'scale' => 'Scala',
        'back' => 'Torna alla panoramica',
    ],
    'allergens' => [
        'none' => 'Nessun allergene dichiarato.',
        'unresolved_heading' => 'Ingredienti senza assegnazione allergeni',
    ],
    'plan' => [
        'total' => 'Totale',
        'per_portion' => 'per porzione',
    ],
    'flash' => [
        'material_saved' => 'Posizione salvata.',
        'material_removed' => 'Posizione rimossa.',
        'profile_saved' => 'Profilo ricetta salvato.',
        'allergens_saved' => 'Allergeni dell\'ingrediente salvati.',
        'menu_saved' => 'Menù salvato.',
    ],
    'error' => [
        'published_immutable' => 'Gli stati pubblicati sono immutabili — creare una nuova versione.',
        'override_reason_required' => 'Le deviazioni allergeni richiedono una motivazione.',
        'ratio_required' => 'Per una quota proporzionale indicare un valore maggiore di 0.',
        'allergens_unresolved' => 'Pubblicazione bloccata: ingredienti senza assegnazione allergeni (:articles). Assegnare gli allergeni o registrare una deviazione motivata.',
    ],
    'costs' => [
        'unit_unmapped' => ':article: unità «:unit» non convertibile nell\'unità base — costi incompleti.',
        'price_missing' => ':article: nessun prezzo d\'acquisto registrato — costi incompleti.',
    ],
    'menu' => [
        'title' => 'Pianificazione menù',
        'intro' => 'Menù e buffet da ricette pubblicate — il numero di ospiti scala il fabbisogno aggregato.',
        'empty' => 'Nessun menù creato.',
        'no_date' => 'senza data',
        'no_dishes' => 'Nessun piatto nel menù.',
        'not_published' => 'nessuno stato pubblicato',
        'dishes_heading' => 'Piatti',
        'aggregate_heading' => 'Fabbisogno materiali aggregato',
        'missing_published' => 'Non considerato (nessuno stato pubblicato): :dishes',
        'no_materials' => 'Nessun fabbisogno — aggiungere piatti con ricette pubblicate.',
        'field' => [
            'name' => 'Nome',
            'event_date' => 'Data',
            'guest_count' => 'Numero ospiti',
            'dishes' => 'Piatti',
            'dish' => 'Piatto',
            'dish_placeholder' => '… seleziona piatto',
            'portions_per_guest' => 'Porzioni per ospite',
            'portions_total' => 'Porzioni totali',
            'version' => 'Stato ricetta',
        ],
        'action' => [
            'create' => 'Crea menù',
            'open' => 'Apri',
            'add_dish' => 'Aggiungi piatto',
        ],
    ],
];
