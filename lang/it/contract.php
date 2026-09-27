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
    // Verbraucherpreisindex und Indexanpassung (MVP-952).
    'price_index' => [
        'title' => 'Indice dei prezzi al consumo',
        'subtitle' => 'IPC Germania (base 2020 = 100) dalla serie della Bundesbank. I valori nuovi o rivisti valgono solo dopo l’approvazione.',
        'pending' => 'In attesa di approvazione: :count valori',
        'add' => 'Aggiungi valore',
        'approve' => 'Approva',
        'reject' => 'Rifiuta',
        'empty' => 'Ancora nessun valore dell’indice. L’importazione viene eseguita ogni mese (contracts:price-index-sync).',
        'field' => [
            'period' => 'Mese',
            'value' => 'Valore dell’indice',
            'source' => 'Fonte',
        ],
        'source' => [
            'bundesbank' => 'Bundesbank',
            'manual' => 'Manuale',
        ],
        'status' => [
            'pending' => 'In attesa di approvazione',
            'approved' => 'Approvato',
            'rejected' => 'Rifiutato',
        ],
        'flash' => [
            'approved' => 'Valore dell’indice approvato.',
            'rejected' => 'Valore dell’indice rifiutato.',
            'saved' => 'Valore dell’indice salvato e approvato.',
        ],
    ],
    'indexation' => [
        'title' => 'Adeguamento all’indice (IPC)',
        'configure' => 'Clausola di indicizzazione',
        'check' => 'Verifica ora',
        'save' => 'Salva',
        'apply' => 'Applica',
        'dismiss' => 'Scarta',
        'not_configured' => 'Nessun indice di base. Inserisca indice e mese di base della clausola di indicizzazione.',
        'base_line' => 'Indice di base :value (:period)',
        'preview_line' => 'attualmente :value (:period), :change % → :amount :currency',
        'effective' => 'efficace dal :date',
        'confirm_apply' => 'Impostare il valore del contratto a :amount e aggiornare l’indice di base?',
        'superseded' => 'Sostituito da un valore dell’indice più recente.',
        'notification' => 'Adeguamento all’indice proposto: :number',
        'disclaimer' => 'Il calcolo segue i dati salvati. Non sostituisce la verifica della clausola di indicizzazione.',
        'section' => [
            'base' => 'Base e regola',
        ],
        'field' => [
            'base_value' => 'Indice di base',
            'base_period' => 'Mese di base',
            'threshold' => 'Soglia (%)',
            'pass_through' => 'Trasferimento (%)',
            'index' => 'Indice',
            'change' => 'Variazione',
            'old_amount' => 'Precedente',
            'new_amount' => 'Nuovo',
        ],
        'hint' => [
            'base_value' => 'Livello dell’indice alla firma o all’ultimo adeguamento, base 2020 = 100.',
            'threshold' => 'Adeguamento solo da questa variazione, vuoto = qualsiasi variazione.',
            'pass_through' => 'Quota della variazione trasferita, vuoto = 100 %.',
        ],
        'status' => [
            'proposed' => 'Proposto',
            'applied' => 'Applicato',
            'dismissed' => 'Scartato',
        ],
        'flash' => [
            'saved' => 'Clausola di indicizzazione salvata.',
            'proposed' => 'Proposta di adeguamento creata.',
            'none' => 'Nessuna proposta: nessun nuovo valore approvato o soglia non raggiunta.',
            'applied' => 'Adeguamento all’indice applicato.',
            'dismissed' => 'Adeguamento all’indice scartato.',
        ],
    ],
];
