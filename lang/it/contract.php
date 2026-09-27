<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'template' => [
        'title' => 'Modelli di contratto',
        'subtitle' => 'I modelli nascono da un contratto con «Salva come modello» o da un profilo di settore.',
        'name' => 'Nome',
        'obligations' => 'Obblighi',
        'active' => 'Attivo',
        'edit' => 'Modifica modello',
        'delete' => 'Elimina modello',
        'confirm_delete' => 'Eliminare il modello «:name»? I contratti esistenti restano invariati.',
        'empty_title' => 'Nessun modello di contratto',
        'empty' => 'Apra un contratto e scelga «Salva come modello».',
        'use' => 'Modello:',
        'save_title' => 'Salva come modello',
        'save' => 'Salva modello',
        'save_hint' => 'Vengono ripresi tipo di contratto, titolo, durata, disdetta, rinnovo, base di valore, regola di adeguamento e obblighi (scadenza relativa all’inizio del contratto). Partner, importi e date no.',
        'flash' => [
            'created' => 'Modello «:name» salvato.',
            'updated' => 'Modello salvato.',
            'deleted' => 'Modello eliminato.',
        ],
    ],
    'cost_center' => [
        'title' => 'Valori dei contratti per centro di costo',
        'subtitle' => 'Contratti in corso (attivi o disdetti ma non ancora terminati); valori ricorrenti riportati ad anno e mese, valori una tantum a parte. Vista di pianificazione, nessuna registrazione.',
        'field' => 'Centro di costo',
        'count' => 'Contratti',
        'yearly' => 'Annuale',
        'monthly' => 'Mensile',
        'once' => 'Una tantum',
        'none' => 'Senza centro di costo',
        'empty' => 'Nessun contratto in corso.',
    ],
    'extraction' => [
        'title' => 'Suggerimenti da «:document»',
        'check' => 'I dati rilevati sono precompilati. Li confronti con il documento; vengono adottati solo al salvataggio.',
        'none' => 'Nel documento non sono stati rilevati dati contrattuali (oppure il testo non era leggibile).',
        'create' => 'Contratto dal documento',
        'leasing_create' => 'Pratica di leasing dal documento',
        'field' => [
            'rate_amount' => 'Rata',
            'payment_rhythm' => 'Periodicità',
            'special_payment' => 'Maxicanone',
            'residual_value' => 'Valore residuo',
            'purchase_option_amount' => 'Opzione di acquisto',
            'starts_on' => 'Inizio',
            'ends_on' => 'Fine',
            'min_term_months' => 'Durata minima',
            'notice_period_days' => 'Termine di disdetta (convertito in giorni)',
            'renew_period_months' => 'Rinnovo',
            'auto_renew' => 'Rinnovo automatico',
            'value_amount' => 'Valore del contratto',
            'value_period' => 'Base del valore',
        ],
    ],
];
