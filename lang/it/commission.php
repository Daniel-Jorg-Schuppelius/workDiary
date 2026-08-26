<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : commission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Provvigioni',

    'page' => [
        'rules' => 'Regole di provvigione',
        'runs' => 'Liquidazioni provvigioni',
    ],

    'subtitle' => [
        'index' => 'Righe di provvigione per documento. La base è la fattura pagata — mai quella emessa.',
        'rules' => 'Aliquota per fonte del lead, gruppo di prodotti o venditore. Per documento vale una sola regola.',
        'runs' => 'Liquidare un periodo: la bozza è un’anteprima, la chiusura la congela. Poi solo storni.',
    ],

    'section' => [
        'unassigned' => 'Fatture pagate senza provvigione',
        'per_user' => 'Totali per venditore',
        'run_rows' => 'Righe di provvigione della liquidazione',
    ],

    'group' => [
        'rule' => 'Regola',
        'validity' => 'Validità',
        'period' => 'Periodo',
    ],

    'action' => [
        'create_rule' => 'Crea regola',
        'edit_rule' => 'Modifica regola',
        'edit' => 'Modifica',
        'delete' => 'Elimina',
        'save' => 'Salva',
        'show' => 'Visualizza',
        'export' => 'Esportazione CSV',
        'close' => 'Chiudi liquidazione',
        'back' => 'Indietro',
        'assign' => 'Assegna venditore',
        'create_run' => 'Crea liquidazione',
        'to_rules' => 'Regole',
        'to_runs' => 'Liquidazioni',
        'to_commissions' => 'Righe di provvigione',
    ],

    'field' => [
        'name' => 'Denominazione',
        'scope' => 'Ambito',
        'scope_value' => 'Valore dell’ambito',
        'user' => 'Venditore',
        'rate_percent' => 'Aliquota',
        'priority' => 'Priorità',
        'valid_from' => 'Valida dal',
        'valid_to' => 'Valida fino al',
        'validity' => 'Validità',
        'is_active' => 'Attiva',
        'note' => 'Nota',
        'status' => 'Stato',
        'invoice' => 'Documento',
        'customer' => 'Cliente',
        'earned_on' => 'Data di riferimento',
        'base_amount' => 'Base di calcolo',
        'commission_amount' => 'Provvigione',
        'run' => 'Liquidazione',
        'period' => 'Periodo',
        'period_start' => 'Periodo dal',
        'period_end' => 'Periodo al',
        'currency' => 'Valuta',
        'entry_count' => 'Righe',
        'total_base' => 'Totale base',
        'total_commission' => 'Totale provvigione',
        'closed_by' => 'Chiusa da',
        'paid_on' => 'Pagata il',
    ],

    'scope' => [
        'all' => 'Tutti i documenti',
        'lead_source' => 'Fonte del lead',
        'product_group' => 'Gruppo di prodotti',
        'user' => 'Venditore',
    ],

    'status' => [
        'pending' => 'Aperta',
        'settled' => 'Liquidata',
        'reversed' => 'Stornata',
    ],

    'run_status' => [
        'draft' => 'Bozza',
        'closed' => 'Chiusa',
    ],

    'assignment' => [
        'lead' => 'Dalla pipeline dei lead',
        'manual' => 'Assegnata manualmente',
    ],

    'badge' => [
        'reversal' => 'Storno',
    ],

    'empty' => [
        'rules' => 'Nessuna regola di provvigione definita.',
        'commissions' => 'Nessuna riga di provvigione presente.',
        'runs' => 'Nessuna liquidazione creata.',
        'run_rows' => 'Nessuna riga di provvigione in questo periodo.',
    ],

    'hint' => [
        'scope_value' => 'Solo per l’ambito fonte del lead o gruppo di prodotti; deve corrispondere all’ambito scelto.',
        'user' => 'Solo per l’ambito venditore.',
        'priority' => 'Vince il numero più alto; a parità decide l’ambito più ristretto.',
        'period' => 'Etichetta leggibile, ad es. 2026-08. Vuoto = derivata dalla data di inizio.',
        'currency' => 'Una liquidazione tratta esattamente una valuta — le provvigioni non vengono mai convertite.',
        'assign' => 'Lasciare vuoto per tornare all’origine dalla pipeline dei lead.',
        'current_assignment' => 'Attualmente responsabile: :user (:source).',
        'no_assignment' => 'Al momento non è responsabile nessuno — senza assegnazione non nasce alcuna provvigione.',
        'unassigned' => 'Queste fatture sono pagate ma non assegnate a nessuno: né manualmente né tramite un lead convertito.',
        'draft_preview' => 'Bozza: le righe vengono ricalcolate a ogni apertura. Solo la chiusura le congela.',
        'no_payout' => 'WorkDiary calcola ed esporta la provvigione — il pagamento avviene nel cedolino.',
    ],

    'confirm' => [
        'delete_rule' => 'Eliminare la regola di provvigione? Le provvigioni già calcolate restano invariate.',
        'delete_run' => 'Eliminare la bozza della liquidazione?',
        'close_run' => 'Chiudere la liquidazione? Dopo è congelata; le correzioni passano solo da uno storno.',
    ],

    'flash' => [
        'rule_created' => 'Regola di provvigione creata.',
        'rule_updated' => 'Regola di provvigione salvata.',
        'rule_deleted' => 'Regola di provvigione eliminata.',
        'assigned' => 'Assegnazione salvata.',
        'run_created' => 'Liquidazione creata.',
        'run_closed' => 'Liquidazione chiusa e congelata.',
        'run_deleted' => 'Liquidazione eliminata.',
    ],

    'error' => [
        'period_reversed' => 'La fine del periodo precede il suo inizio.',
        'period_overlap' => 'Per questo periodo esiste già una liquidazione.',
        'already_closed' => 'Questa liquidazione è già chiusa.',
    ],

    'note' => [
        'credit_note' => 'Storno per nota di credito :number',
        'cancelled' => 'Storno per annullamento',
        'reassigned' => 'Storno per riassegnazione del venditore',
    ],

    'export' => [
        'period' => 'Periodo',
        'user' => 'Venditore',
        'invoice' => 'Documento',
        'customer' => 'Cliente',
        'earned_on' => 'Data di riferimento',
        'currency' => 'Valuta',
        'base' => 'Base di calcolo',
        'rate' => 'Aliquota in percentuale',
        'commission' => 'Provvigione',
        'kind' => 'Tipo',
        'note' => 'Nota',
        'reversal' => 'Storno',
        'regular' => 'Provvigione',
    ],
];
