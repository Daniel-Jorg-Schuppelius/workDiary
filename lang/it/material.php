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
        'label' => 'Articolo',
        'none' => '— nessun articolo —',
        'hint' => 'Tramite l\'articolo, il report fornitori assegna il consumo a un fornitore.',
        'link' => 'Assegna articoli',
        'link_hint' => 'Assegnare i materiali senza articolo tramite lo stesso SKU o numero articolo; le corrispondenze ambigue restano aperte.',
        'linked' => ':count materiali assegnati a un articolo.',
    ],
    'stock' => [
        'insufficient' => 'Nel magazzino «:warehouse» sono disponibili solo :available :unit. Per un prelievo in giacenza negativa selezioni «Consenti giacenza negativa».',
        'negative_forbidden' => 'Solo chi ha il permesso «Autorizzare giacenze negative» può registrare un prelievo in giacenza negativa.',
        'serial_required' => 'L\'articolo richiede il numero di serie. Lo prelevi dal magazzino con il numero di serie oppure registri il consumo senza anagrafica materiale.',
        'booking_failed' => 'Non è stato possibile registrare il prelievo dal magazzino: :reason',
        'return_failed' => 'Non è stato possibile registrare il reso in magazzino: :reason',
        'usage_gone' => 'Il consumo di materiale di questa richiesta non esiste più.',
        'allow_negative_hint' => 'Necessario solo se un articolo gestito a magazzino non ha giacenza sufficiente nel magazzino predefinito.',
        'notice' => ':status · magazzino «:warehouse»',
        'current' => 'Giacenza: :notice',
    ],
    // Variante per consumo (MVP-1690).
    'variant' => [
        'default' => '— Variante predefinita —',
        'hint' => 'Solo per articoli gestiti a magazzino con più varianti. Vuoto registra la variante predefinita; senza prezzo unitario vale il prezzo proprio della variante, altrimenti quello del materiale.',
        'label' => 'Variante: :variant',
        'mismatch' => 'La variante non appartiene all\'articolo del materiale selezionato.',
        'inactive' => 'Questa variante non è attiva e non può essere scelta di nuovo.',
    ],
];
