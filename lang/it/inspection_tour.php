<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfertouren (MVP-918).
return [
    'title' => 'Giro di ispezione',
    'subtitle' => 'Pianifichi come giro le ispezioni in scadenza di un ispettore: ogni appuntamento diventa un ordine presso l\'ubicazione del dispositivo e la pianificazione ottimizza l\'ordine.',
    'inspector' => 'Ispettore',
    'due_until' => 'In scadenza entro',
    'date' => 'Data del giro',
    'select' => 'Seleziona',
    'location' => 'Ubicazione',
    'no_coordinates' => 'senza coordinate',
    'no_coordinates_hint' => 'Senza coordinate sul dispositivo la tappa resta nel giro ma non rientra nel calcolo del percorso.',
    'none' => 'Nessuna ispezione aperta per questo ispettore nel periodo.',
    'none_selected' => 'Selezioni almeno un\'ispezione aperta.',
    'unavailable' => 'La pianificazione dei giri non è attiva per la sua organizzazione; i giri di ispezione richiedono il modulo Pianificazione.',
    'plan' => 'Pianifica giro',
    'planned' => ':count ispezioni pianificate come giro.',
    'entry_title' => 'Ispezione :profile – :asset',
    'entry_content' => 'Ispezione dal giro di ispezione, in scadenza il :due.',
];
