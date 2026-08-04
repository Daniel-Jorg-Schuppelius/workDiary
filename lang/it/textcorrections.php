<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : textcorrections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Dizionario',
        'subtitle' => 'Correzioni ortografiche (errato → corretto) applicate automaticamente ai testi di posizione generati — le registrazioni dei tempi restano invariate.',
    ],

    'notice' => 'Le voci si applicano automaticamente alla costruzione dei testi di posizione di trasferimenti e bozze di fattura (parola intera, le maiuscole/minuscole vengono mantenute). I testi originali delle registrazioni non vengono mai modificati.',
    'search_placeholder' => 'Cerca (errato/corretto) …',
    'legend' => 'Voce del dizionario',
    'empty' => 'Nessuna voce nel dizionario',
    'delete_confirm' => 'Eliminare questa voce del dizionario? La correzione non verrà più applicata.',
    'wrong_placeholder' => 'es. manutenzzione',
    'wrong_help' => 'Parola o frase errata — corrispondenza solo come parola intera, senza distinzione tra maiuscole e minuscole.',
    'correct_placeholder' => 'es. manutenzione',
    'correct_help' => 'Grafia corretta — sostituisce l\'errore in tutti i testi di posizione generati.',

    'field' => [
        'wrong' => 'Errato',
        'correct' => 'Corretto',
        'origin' => 'Origine',
        'origin_manual' => 'Manuale',
        'origin_learned' => 'Appreso',
        'usage' => 'Utilizzato',
        'active' => 'Attivo',
        'enabled_yes' => 'Sì',
        'enabled_no' => 'No',
    ],

    'action' => [
        'new' => 'Crea voce',
        'edit' => 'Modifica voce',
        'submit' => 'Salva',
        'activate' => 'Attiva',
        'deactivate' => 'Disattiva',
        'delete' => 'Elimina',
    ],

    'flash' => [
        'saved' => 'Voce del dizionario creata.',
        'updated' => 'Voce del dizionario aggiornata.',
        'deleted' => 'Voce del dizionario eliminata.',
        'activated' => 'Voce del dizionario attivata.',
        'deactivated' => 'Voce del dizionario disattivata.',
        'learned' => 'Correzione aggiunta al dizionario.',
        'duplicate_updated' => 'La voce esisteva già ed è stata aggiornata.',
        'invalid' => 'Errato e corretto non possono essere identici.',
    ],

    'validation' => [
        'duplicate' => 'Esiste già una voce per questo errore.',
    ],

    'learn' => [
        'title' => 'Memorizzare la correzione?',
        'question' => 'Nella modifica sono state rilevate correzioni di parole. Aggiungerle al dizionario perché vengano applicate automaticamente in futuro?',
        'confirm' => 'Memorizza',
        'dismiss' => 'Non memorizzare',
    ],
];
