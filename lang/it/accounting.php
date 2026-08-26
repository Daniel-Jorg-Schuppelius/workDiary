<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Trasferisci alla contabilità',
    ],

    'flash' => [
        'pushed' => 'Cliente trasferito alla contabilità (ID :id).',
        'failed' => 'Trasferimento non riuscito: :msg',
        'no_plugin' => 'Nessun sistema contabile attivo.',
    ],

    'error' => [
        'accounting_leads' => 'La contabilità detiene i dati anagrafici — non viene trasferito nulla (impostazione «autorità dei dati anagrafici»).',
        'no_syncer' => 'Il plugin :plugin non trasferisce contatti.',
    ],

    'authority' => [
        'workdiary' => 'Guida workDiary',
        'accounting' => 'Guida la contabilità',
    ],

    // Lokale Buchhaltung (Feature 125, MVP-671): Einrichtung, Buchungshoheit,
    // Geschäftsjahre und Preflight.
    'ledger' => [
        'title' => 'Contabilità locale',
        'menu' => 'Contabilità',
        'setup_menu' => 'Configurazione',
        'subtitle' => 'Autorità contabile, esercizi e controllo preliminare della configurazione.',
        'open_ended' => 'in corso',
        'sovereignty_note' => [
            'preaccounting' => 'La contabilità locale non è attivata — WorkDiary non tiene un proprio libro mastro, per questo questi elenchi restano vuoti. Verifica dei documenti, pagamenti e trasferimento (DATEV/GoBD) proseguono indipendentemente.',
            'external' => 'Il libro mastro è attualmente tenuto da un sistema esterno — i dati locali sono una proiezione e una prova di trasferimento, non un libro mastro concorrente.',
            'external_named' => 'Il libro mastro è attualmente tenuto da :provider — i dati locali sono una proiezione e una prova di trasferimento, non un libro mastro concorrente.',
            'setup_link' => 'Apri la configurazione',
        ],
        'section' => [
            'profile' => 'Profilo contabile',
            'preflight' => 'Controllo preliminare',
            'fiscal_years' => 'Esercizi',
            'sovereignty' => 'Autorità contabile',
        ],
        'field' => [
            'profit_determination' => 'Determinazione del reddito',
            'base_currency' => 'Valuta di base',
            'fiscal_year_start_month' => 'L\'esercizio inizia in',
            'starts_on' => 'Inizio delle registrazioni (data di riferimento)',
            'note' => 'Nota',
            'fiscal_year_starts_on' => 'Inizio dell\'esercizio',
            'fiscal_year_label' => 'Denominazione',
            'sovereignty' => 'Nuova autorità contabile',
            'external_provider' => 'Sistema principale',
            'valid_from' => 'Valido dal',
            'reason' => 'Motivazione',
            'datev_account' => 'Conto DATEV',
            'euer_category' => 'Riga entrate-uscite',
            'euer_category_none' => '— senza assegnazione —',
            'bwa_group' => 'Riga BWA',
            'bwa_group_none' => '— derivare dall\'intervallo numerico —',
            'deductible_percent' => 'Quota deducibile (%)',
            'description' => 'Descrizione',
            'post_now' => 'Registrare subito',
            'reversal_reason' => 'Motivazione',
            'reversal_booked_on' => 'Data della contro-registrazione',
        ],
        'hint' => [
            'profit_determination' => 'Cambia le analisi (per cassa o partita doppia), non le regole di registrazione e di prova.',
            'base_currency' => 'La prima versione gestisce una sola valuta; i documenti divergenti vengono segnalati invece che convertiti.',
            'starts_on' => 'Da questo giorno nascono registrazioni locali. I documenti precedenti restano storico e non vengono registrati retroattivamente.',
            'fiscal_year_starts_on' => 'Vengono create dodici periodi mensili fino al giorno precedente l\'anno successivo.',
            'fiscal_year_label' => 'Lasciare vuoto per «2026» oppure «2026/2027» con esercizio non solare.',
            'sovereignty' => 'Chi teneva il libro mastro e per quale periodo resta tracciabile, anche dopo un cambio.',
            'sovereignty_switch' => 'Il trasferimento dei dati resta il cambio di contabilità; qui si riassegna solo la direzione.',
            'external_provider' => 'Solo con autorità esterna: nome del sistema principale (ad es. lexoffice).',
            'datev_account' => 'Solo per l\'esportazione; la registrazione locale non ne dipende.',
            'euer_category' => 'Determina in quale riga del modulo compare il conto. Senza assegnazione finisce tra i casi non chiariti.',
            'bwa_group' => 'Riga dell\'analisi gestionale (BWA). Senza assegnazione il report deriva la riga dall\'intervallo SKR03/SKR04; se resta aperto, il conto appare sotto «non assegnato».',
            'deductible_percent' => "Agisce solo sull'anteprima entrate-uscite — nel giornale resta sempre l'importo pieno (p. es. 70 % per le spese di rappresentanza).",
            'normal_balance' => 'Precompilata dal tipo di conto, modificabile caso per caso.',
            'post_now' => 'Dopo la registrazione la correzione avviene solo tramite contro-registrazione.',
            'reversal_booked_on' => 'Lasciare vuoto per il giorno originale, se il periodo è ancora aperto.',
        ],
        'action' => [
            'activate' => 'Attivare la contabilità locale',
            'add_fiscal_year' => 'Creare un esercizio',
            'switch' => 'Cambiare l\'autorità contabile',
            'switch_submit' => 'Riassegnare la direzione',
            'add_account' => 'Creare conto',
            'edit_account' => 'Modificare conto',
            'deactivate' => 'Disattivare',
            'add_entry' => 'Nuova registrazione',
            'post' => 'Registrare',
            'reverse' => 'Stornare',
            'reverse_submit' => 'Creare contro-registrazione',
        ],
        'column' => [
            'fiscal_year' => 'Esercizio',
            'range' => 'Periodo',
            'periods' => 'Periodi',
            'status' => 'Stato',
            'from' => 'Dal',
            'to' => 'Al',
            'holder' => 'Direzione',
            'reason' => 'Motivazione',
            'number' => 'Conto',
            'name' => 'Denominazione',
            'type' => 'Tipo di conto',
            'normal_balance' => 'Sezione del saldo',
            'flags' => 'Caratteristiche',
            'journal_no' => 'N.',
            'booked_on' => 'Data di registrazione',
            'document_on' => 'Data documento',
            'memo' => 'Descrizione',
            'accounts' => 'Conti',
            'amount' => 'Importo',
            'debit' => 'Dare',
            'credit' => 'Avere',
            'account' => 'Conto',
            'document_reference' => 'Documento',
            'posted_by' => 'Registrato da',
            'source' => 'Origine',
        ],
        'empty' => [
            'accounts' => 'Nessun conto ancora creato.',
            'entries' => 'Nessuna scrittura nel periodo.',
            'fiscal_years' => 'Nessun esercizio creato finora.',
            'sections' => 'Nessun cambio di direzione registrato.',
        ],
        'flash' => [
            'saved' => 'Profilo contabile salvato.',
            'activated' => 'Contabilità locale attivata.',
            'fiscal_year_created' => 'Esercizio :year creato con i periodi.',
            'sovereignty_switched' => 'Autorità contabile cambiata.',
            'account_saved' => 'Conto salvato.',
            'account_deactivated' => 'Conto disattivato.',
            'imported' => 'Importazione conti: :imported nuovi, :updated aggiornati, :errors errori.',
            'entry_saved' => 'Registrazione salvata.',
            'entry_posted' => 'Registrazione definitiva.',
            'entry_reversed' => 'Contro-registrazione creata.',
        ],
        'error' => [
            'sovereignty' => 'Il :date il libro mastro è tenuto da :holder — per quel giorno non sono ammesse registrazioni locali.',
            'fiscal_year_overlap' => 'Il periodo si sovrappone all\'esercizio :year.',
            'start_locked' => 'L\'inizio delle registrazioni non è più modificabile dopo l\'attivazione.',
            'provider_required' => 'Con autorità contabile esterna occorre indicare il sistema principale.',
            'sovereignty_unchanged' => 'Questa autorità contabile vale già per la data indicata.',
            'later_section_exists' => 'Esiste già un periodo di direzione successivo dal :date.',
            'period_closed' => 'Il periodo dal :period non accetta più registrazioni.',
            'no_period' => 'Nessun periodo contabile per il :date.',
            'entry_frozen' => 'La registrazione è definitiva — correzione solo tramite contro-registrazione.',
            'needs_two_lines' => 'Una registrazione richiede almeno due righe.',
            'unknown_account' => 'Una riga rimanda a un conto sconosciuto.',
            'unknown_cost_center' => 'Il centro di costo non appartiene a questa organizzazione.',
            'inactive_account' => 'Il conto :account è disattivato.',
            'foreign_currency_line' => 'Tutte le righe devono essere in :currency.',
            'negative_amount' => 'Gli importi sono positivi; la direzione deriva da Dare o Avere.',
            'both_sides' => 'Una riga porta o Dare o Avere, mai entrambi.',
            'unbalanced' => 'Dare (:debit) e Avere (:credit) non coincidono.',
            'reverse_not_posted' => 'Solo una registrazione definitiva può essere stornata.',
            'reversal_reason_required' => 'Lo storno richiede una motivazione.',
            'account_in_use' => 'Su questo conto è già stato registrato — può solo essere disattivato.',
            'entry_without_organization' => 'La registrazione non ha un\'organizzazione — informare l\'amministratore.',
            'account_number_taken' => 'Questo numero di conto esiste già.',
        ],
        'preflight' => [
            'not_configured' => 'Profilo non ancora salvato — il controllo parte dal primo salvataggio.',
            'blocked_hint' => 'L\'attivazione resta bloccata finché un punto è rosso.',
            'profile_missing' => 'Non è ancora stato salvato alcun profilo contabile.',
            'starts_on_missing' => 'Non è stato impostato alcun inizio delle registrazioni.',
            'starts_on_ok' => 'Inizio delle registrazioni: :date.',
            'fiscal_year_missing' => 'Non esiste un esercizio che copra l\'inizio delle registrazioni.',
            'periods_missing' => 'L\'esercizio :year non ha periodi.',
            'fiscal_year_ok' => 'Esercizio :year con :count periodi.',
            'migration_active' => 'È in corso un cambio di contabilità (:status) — nel frattempo la direzione non è univoca.',
            'migration_none' => 'Nessun cambio di contabilità in corso.',
            'handed_over' => 'Il lotto DATEV :batch copre già il periodo fino al :to.',
            'handed_over_none' => 'Nessun lotto esportato si sovrappone al periodo.',
            'sovereignty_conflict' => 'Dal :date dirige già :holder — il periodo sarebbe occupato due volte.',
            'sovereignty_ok' => 'Nessun periodo di direzione concorrente.',
            'foreign_currency' => ':count documenti dalla data di riferimento non sono in :currency; restano visibili nella posta contabile.',
            'base_currency_ok' => 'Tutti i documenti dalla data di riferimento sono in :currency.',
            'billing_external' => 'Le fatture le emette :program — i documenti arriveranno da lì.',
            'billing_local' => 'workDiary emette autonomamente le fatture di vendita.',
            'master_data_external' => 'I dati anagrafici sono diretti dalla contabilità; clienti e fornitori non vengono sovrascritti da qui.',
            'master_data_local' => 'workDiary dirige i dati anagrafici.',
            'key' => [
                'profile' => 'Profilo',
                'starts_on' => 'Data di riferimento',
                'fiscal_year' => 'Esercizio',
                'migration_run' => 'Cambio',
                'handed_over' => 'Trasferimenti',
                'sovereignty' => 'Direzione',
                'base_currency' => 'Valuta',
                'billing_mode' => 'Fatturazione',
                'master_data' => 'Anagrafiche',
            ],
        ],
        'reversal_memo' => 'Storno della registrazione n. :no',
        'opening_memo' => 'Registrazione di apertura',
        'reverse_hint' => 'Lo storno crea una vera contro-registrazione. La registrazione originale resta invariata.',
        'accounts' => [
            'title' => 'Piano dei conti',
            'menu' => 'Piano dei conti',
            'subtitle' => 'Conti, sezione del saldo e corrispondenza DATEV della contabilità locale.',
        ],
        'journal' => [
            'title' => 'Libro giornale',
            'menu' => 'Giornale',
            'subtitle' => 'Registrazioni definitive e preparate nel periodo scelto.',
        ],
        'entry' => [
            'title' => 'Registrazione',
            'head' => 'Testata',
            'lines' => 'Righe',
            'total' => 'Totale',
            'is_reversal_of' => 'Questa registrazione storna la registrazione n. :no.',
            'reversed_by' => 'Stornata dalla registrazione n. :no — :reason',
        ],
        'filter' => [
            'only_active' => 'solo attivi',
            'all_types' => 'Tutti i tipi di conto',
            'all_states' => 'Tutti gli stati',
        ],
        'flag' => [
            'open_item' => 'Partite aperte',
            'bank' => 'Banca',
            'cash' => 'Cassa',
            'clearing' => 'Transitorio',
            'inactive' => 'Disattivato',
        ],
        'confirm' => [
            'deactivate' => 'Disattivare davvero questo conto? Le registrazioni esistenti restano.',
        ],
        'import' => [
            'line_invalid' => 'Riga :line ignorata (numero, nome o tipo di conto mancante).',
        ],
    ],

    // Buchungs-Inbox und Mappingregeln (Feature 125, MVP-673).
    'inbox' => [
        'title' => 'Posta contabile',
        'menu' => 'Posta contabile',
        'subtitle' => 'Documenti, spese e movimenti di cassa del periodo con il loro stato contabile.',
        'empty' => 'Nessun elemento aperto nel periodo.',
        'four_eyes_active' => 'Principio dei quattro occhi attivo: chi prepara una proposta non la registra da solo.',
        'state' => [
            'blocked' => 'Bloccato',
            'open' => 'Non registrato',
            'ready' => 'Pronto',
            'posted' => 'Registrato',
        ],
        'column' => [
            'kind' => 'Origine',
            'document' => 'Documento',
            'booked_on' => 'Data',
            'proposal' => 'Proposta',
        ],
        'filter' => [
            'all_kinds' => 'Tutte le origini',
            'include_posted' => 'mostra registrati',
        ],
        'action' => [
            'prepare' => 'Accettare la proposta',
            'prepare_and_post' => 'Accettare e registrare',
            'batch_prepare' => 'Accettare tutto',
            'batch_post' => 'Accettare e registrare tutto',
        ],
        'confirm' => [
            'batch' => 'Accettare come bozze tutti gli elementi non bloccati del periodo?',
            'batch_post' => 'Accettare E registrare tutti gli elementi non bloccati? Le registrazioni definitive si correggono solo con contro-registrazioni.',
        ],
        'flash' => [
            'prepared' => 'Proposta accettata.',
            'batch' => 'Lotto: :prepared accettati, :posted registrati, :failed aperti.',
        ],
        'error' => [
            'four_eyes' => 'Principio dei quattro occhi: questa registrazione è stata preparata da lei — deve registrarla un altro.',
        ],
        'blocker' => [
            'missing_rule' => 'Nessuna regola contabile per :role:criteria.',
            'handed_over' => 'Il documento fa già parte di un lotto esportato.',
            'no_tax_breakdown' => 'Il documento non ha la ripartizione dell\'IVA.',
            'no_amount' => 'Il documento non ha importo.',
            'no_lines' => 'La proposta non ha righe di registrazione.',
            'sovereignty' => 'In questo periodo l\'organizzazione non tiene un libro mastro locale.',
            'foreign_currency' => 'Il documento è in :currency, la contabilità in :base — non esiste ancora una conversione documentabile.',
            'unsupported_target' => 'Per questa destinazione di pagamento non esiste ancora un percorso contabile.',
            'year_closed' => 'L\'esercizio :year è chiuso.',
            'period_closed' => 'Il periodo del :date è chiuso.',
        ],
        'memo' => [
            'sales_invoice' => 'Fattura :number · :customer',
            'incoming_invoice' => 'Fattura di acquisto :number · :seller',
            'expense' => 'Spesa :description · :user',
            'cash_entry' => 'Cassa :register · :purpose',
            'payment' => 'Pagamento (:kind) · :target',
            'depreciation' => 'Ammortamento :year · :no :name',
        ],
        'reversal_reason' => [
            'unmatched' => 'Assegnazione del pagamento annullata — contro-registrazione.',
        ],
    ],
    // Anlagenregister und Jahres-AfA (Feature 133, MVP-698).
    'fixed_assets' => [
        'title' => 'Registro cespiti',
        'menu' => 'Cespiti',
        'subtitle' => 'Beni con costo d\'acquisto, vita utile e piano di ammortamento — l\'ammortamento annuale viene registrato come proposta tramite la posta contabile.',
        'empty' => 'Nessun cespite nel registro.',
        'months' => ':count mesi',
        'account_from_rule' => 'da regola contabile',
        'kpi' => [
            'active' => 'Cespiti attivi',
            'total' => 'Cespiti in totale',
            'book_value_year' => 'Valore residuo fine :year',
        ],
        'filter' => [
            'all' => 'Tutti i cespiti',
        ],
        'column' => [
            'no' => 'N.',
            'name' => 'Denominazione',
            'acquired_on' => 'Acquisto',
            'cost' => 'Costo d\'acquisto',
            'useful_life' => 'Vita utile',
            'book_value' => 'Valore residuo :year',
        ],
        'field' => [
            'device' => 'Dispositivo (asset)',
            'residual_value' => 'Valore residuo finale',
            'method' => 'Metodo di ammortamento',
            'asset_account' => 'Conto cespite',
            'depreciation_account' => 'Conto ammortamento',
            'disposed_on' => 'Dismesso il',
            'created_by' => 'Creato da',
        ],
        'section' => [
            'master' => 'Dati anagrafici',
            'accounts' => 'Conti',
            'schedule' => 'Piano di ammortamento',
            'posting' => 'Registrazione',
        ],
        'schedule' => [
            'year' => 'Esercizio',
            'months' => 'Mesi',
            'amount' => 'Quota',
            'book_value_end' => 'Valore residuo',
            'empty' => 'Nessun piano — manca la base ammortizzabile o la vita utile.',
        ],
        'hint' => [
            'device' => 'Collegamento facoltativo al registro dispositivi; non ogni cespite è un dispositivo.',
            'residual_value' => 'Resta alla fine della vita utile; predefinito 0.',
            'useful_life' => 'Vita utile ordinaria secondo la tabella di ammortamento, in mesi.',
            'accounts' => 'Lasciare vuoto per applicare la regola contabile del ruolo (conto cespite / ammortamento).',
            'frozen' => 'Una quota è registrata — data d\'acquisto, costo, valore residuo e vita utile sono bloccati.',
            'schedule' => 'Lineare, pro rata mensile nell\'anno di acquisto e di dismissione; l\'ultimo anno prende il resto.',
            'posting' => 'L\'ammortamento annuale viene proposto per esercizio nella chiusura e registrato nella posta contabile — mai direttamente.',
            'dispose' => 'La dismissione termina il piano nel mese di dismissione. Il valore residuo non viene stornato automaticamente.',
        ],
        'action' => [
            'add' => 'Aggiungi cespite',
            'edit' => 'Modifica cespite',
            'dispose' => 'Registra dismissione',
            'dispose_submit' => 'Registra dismissione',
        ],
        'flash' => [
            'created' => 'Cespite :no creato.',
            'updated' => 'Cespite salvato.',
            'disposed' => 'Dismissione registrata.',
        ],
        'error' => [
            'disposed_frozen' => 'Un cespite dismesso non è più modificabile.',
            'values_frozen' => 'I campi che determinano il valore sono bloccati dopo la prima quota registrata.',
            'disposed_before_acquired' => 'La dismissione non può precedere l\'acquisto.',
            'residual_exceeds_cost' => 'Il valore residuo deve essere inferiore al costo d\'acquisto.',
            'useful_life_required' => 'La vita utile deve essere di almeno un mese.',
        ],
    ],
    'rules' => [
        'title' => 'Regole di registrazione',
        'menu' => 'Regole di registrazione',
        'subtitle' => 'Corrispondenza tra origine, ruolo e conto — versionata e con data di validità.',
        'empty' => 'Nessuna regola creata finora.',
        'fallback' => 'Regola generale (tutti i casi)',
        'no_tax_code' => '— senza codice IVA —',
        'column' => [
            'role' => 'Ruolo',
            'match' => 'Criteri',
            'validity' => 'Validità',
            'priority' => 'Priorità',
        ],
        'field' => [
            'tax_code' => 'Codice IVA',
            'match_key' => 'Criterio',
            'match_value' => 'Valore',
        ],
        'hint' => [
            'role' => 'Che cosa rappresenta il conto nella registrazione — ricavo, credito, IVA a credito …',
            'tax_code' => 'Facoltativo; collega il risultato fiscale congelato del documento a un conto.',
            'match' => 'Lasciare vuoto per la regola generale. Esempi: tax_rate = 19.00, expense_category_id = 5.',
            'priority' => 'Vince la più alta; a parità, la regola più specifica.',
        ],
        'action' => [
            'add' => 'Creare regola',
            'edit' => 'Modificare regola',
        ],
        'confirm' => [
            'deactivate' => 'Disattivare la regola? Le registrazioni esistenti mantengono la loro versione.',
        ],
        'flash' => [
            'saved' => 'Regola salvata.',
            'versioned' => 'Nuova versione della regola creata dalla data di validità.',
            'deactivated' => 'Regola disattivata.',
        ],
    ],

    // Offene Posten (Feature 125, MVP-674).
    'open_items' => [
        'title' => 'Partite aperte',
        'menu' => 'Partite aperte',
        'subtitle' => 'Crediti e debiti dalle registrazioni definitive, con analisi per scadenza.',
        'empty' => 'Nessuna partita aperta.',
        'overdue_days' => 'scaduto da :days giorni',
        'settle_hint' => 'Aperto: :open. I pagamenti arrivano dalla riconciliazione bancaria — qui solo sconto, ritenuta o stralcio.',
        'column' => [
            'counterparty' => 'Controparte',
            'due_date' => 'Scadenza',
            'original' => 'Originale',
            'open' => 'Aperto',
            'kind' => 'Tipo',
        ],
        'bucket' => [
            'not_due' => 'Non scaduto',
            'd30' => '1–30 giorni',
            'd60' => '31–60 giorni',
            'd90' => '61–90 giorni',
            'd90plus' => 'oltre 90 giorni',
        ],
        'action' => [
            'settle' => 'Compensare',
            'show_entry' => 'Mostra registrazione',
        ],
        'flash' => [
            'settled' => 'Compensazione registrata.',
        ],
    ],

    // Wiederkehrende Vorgänge (Feature 125, MVP-675).
    'recurring' => [
        'title' => 'Operazioni ricorrenti',
        'menu' => 'Ricorrenti',
        'subtitle' => 'Attese di documenti, modelli di registrazione e piani di fatturazione a colpo d’occhio.',
        'principle' => 'Un\'attesa di documento non crea né documento né registrazione — solo l\'originale la soddisfa. I modelli creano soltanto bozze.',
        'invoice_schedules_hint' => 'Le fatture ricorrenti restano al piano di fatturazione; qui solo per panoramica.',
        'preview' => 'Prossime scadenze: :dates',
        'no_account' => '— nessun conto —',
        'section' => [
            'open_runs' => 'Operazioni aperte',
            'templates' => 'Modelli',
            'invoice_schedules' => 'Piani di fatturazione',
        ],
        'column' => [
            'template' => 'Modello',
            'period' => 'Periodo',
            'expected' => 'Atteso',
            'name' => 'Denominazione',
            'kind' => 'Tipo',
            'interval' => 'Ritmo',
            'next_due' => 'Prossima scadenza',
            'responsible' => 'Responsabile',
        ],
        'field' => [
            'due_day' => 'Giorno di scadenza',
            'starts_on' => 'Inizio',
            'ends_on' => 'Fine',
        ],
        'hint' => [
            'kind' => 'L\'attesa aspetta un originale; il modello di registrazione crea una bozza.',
            'due_day' => '1–28, così ogni mese ha quel giorno.',
            'accounts' => 'Solo per i modelli di registrazione — insieme all\'importo atteso.',
        ],
        'action' => [
            'add' => 'Creare modello',
            'edit' => 'Modificare modello',
            'run' => 'Esegui ora',
            'pause' => 'Sospendere',
            'resume' => 'Riprendere',
            'end' => 'Terminare',
            'open_schedules' => 'Apri i piani',
        ],
        'confirm' => [
            'end' => 'Terminare il modello? Le operazioni già create restano.',
        ],
        'empty' => [
            'runs' => 'Nessuna operazione aperta.',
            'templates' => 'Nessun modello creato.',
            'schedules' => 'Nessun piano attivo.',
        ],
        'flash' => [
            'saved' => 'Modello salvato.',
            'versioned' => 'Modello salvato in nuova versione.',
            'paused' => 'Modello sospeso.',
            'resumed' => 'Modello ripreso.',
            'ended' => 'Modello terminato.',
            'ran' => 'Esecuzione effettuata.',
        ],
        'error' => [
            'already_closed' => 'L\'operazione è già chiusa.',
            'template_incomplete' => 'Un modello richiede conto Dare, conto Avere e importo.',
        ],
        'blocker' => [
            'no_lines' => 'Al modello mancano le righe di registrazione.',
        ],
        'notification' => [
            'title' => 'Operazione ricorrente scaduta: :name',
            'message' => 'Scadenza il :due — stato: :status.',
        ],
    ],

    // Finanzberichte (Feature 125, MVP-676).
    'reports' => [
        'title' => 'Report finanziari',
        'menu' => 'Report finanziari',
        'subtitle' => 'Analisi della contabilità locale nel periodo selezionato.',
        'period' => 'Periodo :from – :to',
        'as_of' => 'Al :date',
        'empty' => 'Nessun dato nel periodo.',
        'vat_preview_hint' => 'Anteprima verificabile — l\'MVP non trasmette alcuna dichiarazione IVA.',
        'euer_preview_hint' => 'Anteprima secondo incasso e pagamento (§ 11 EStG), suddivisa per le righe del modulo tedesco — non è il modulo.',
        'euer_manual_hint' => 'da registrare manualmente',
        'pnl_hint' => 'Risultato per gruppi di conti — non è un conto economico certificato.',
        'column' => [
            'euer_category' => 'Riga entrate-uscite',
            'gross' => 'Importo',
            'deductible' => 'Deducibile',
            'not_deductible' => 'Non deducibile',
            'opening' => 'Saldo iniziale',
            'closing' => 'Saldo finale',
            'balance' => 'Saldo',
            'direction' => 'Direzione',
            'payable' => 'IVA dovuta',
            'result' => 'Risultato',
            'section' => 'Sezione',
        ],
        'section' => [
            'income' => 'Ricavi',
            'expense' => 'Costi',
            'balances' => 'Conti bancari e di cassa',
        ],
        'kpi' => [
            'cash' => 'Banca e cassa',
            'receivable' => 'Crediti',
            'payable' => 'Debiti',
            'forecast' => 'Previsione',
            'findings' => 'Rilievi',
        ],
        'aging' => [
            'receivable' => 'Scadenzario crediti',
            'payable' => 'Scadenzario debiti',
        ],
        'unclear' => [
            'title' => 'Casi non chiariti',
            'none' => 'Nessun caso non chiarito.',
            'open_items' => ':count partite aperte non sono compensate nel periodo.',
            'settlement_without_item' => 'Compensazione :id senza partita aperta corrispondente.',
            'settlement_without_source' => 'Pareggio :id senza documento di origine utilizzabile.',
            'account_without_category' => 'Il conto :account non ha una riga entrate-uscite.',
        ],
        'quality' => [
            'headline' => 'Cosa ostacola le analisi',
            'none' => 'Nessun rilievo.',
            'drafts' => ':count registrazioni non sono definitive.',
            'unbalanced' => ':count bozze non sono in pareggio.',
            'blocked_runs' => ':count esecuzioni ricorrenti sono bloccate.',
            'open_expectations' => ':count attese di documenti sono ancora aperte.',
            'ten_day_rule' => ":count pagamenti cadono tra il 22.12 e il 10.01 e appartengono all'anno adiacente secondo il documento (§ 11 c. 1 per. 2 EStG).",
            'open_clearing' => ':count conti transitori non sono ancora pareggiati.',
            'overdue_filings' => ':count scadenze dichiarative sono superate e non risultano presentate.',
            'kpi' => [
                'drafts' => 'Bozze',
                'unbalanced' => 'Non in pareggio',
                'blocked_runs' => 'Esecuzioni bloccate',
                'open_expectations' => 'Attese aperte',
            ],
        ],
        // 13-Wochen-Liquiditätsvorschau (Feature 136, MVP-701).
        'forecast' => [
            'subtitle' => 'Saldo iniziale banca e cassa e pagamenti attesi per settimana di calendario dal :date — :weeks settimane.',
            'hint' => 'Un’aspettativa, non un saldo: partite aperte secondo il comportamento di pagamento e le scadenze di sconto, attese di documenti, piani di fatturazione, ordini di pagamento rilasciati, rate di finanziamento e scadenze fiscali quantificabili. Gli scaduti contano nella settimana corrente.',
            'horizon' => ':weeks settimane',
            'column' => [
                'week' => 'Settimana',
                'period' => 'Periodo',
                'inflow' => 'Entrate',
                'outflow' => 'Uscite',
                'net' => 'Netto',
                'closing' => 'Saldo',
            ],
            'kpi' => [
                'opening' => 'Saldo iniziale',
                'inflow' => 'Entrate',
                'outflow' => 'Uscite',
                'min_closing' => 'Saldo minimo',
                'min_week' => 'in :week',
            ],
            'chart' => [
                'closing' => 'Saldo cumulato per settimana',
                'flows' => 'Entrate e uscite per settimana',
            ],
            'source' => [
                'receivables' => 'Crediti',
                'payables' => 'Debiti',
                'recurring' => 'Attese di documenti',
                'invoice_schedules' => 'Piani di fatturazione',
                'payment_runs' => 'Ordini di pagamento',
                'finance_rates' => 'Rate',
                'filings' => 'Imposte',
            ],
            'note' => [
                'overdue' => 'scaduto — settimana corrente',
                'delay' => 'ritardo medio :days giorni',
                'discount' => 'sconto :percent % alla scadenza di sconto',
            ],
        ],
        'card' => [
            'trial_balance' => [
                'title' => 'Bilancio di verifica',
                'text' => 'Riporto, movimento e saldo per conto.',
            ],
            'account_ledger' => [
                'title' => 'Mastrino',
                'text' => 'Tutti i movimenti di un conto con accesso alla registrazione.',
            ],
            'vat' => [
                'title' => 'IVA',
                'text' => 'IVA a debito, a credito e dovuta in anteprima.',
            ],
            'euer' => [
                'title' => 'Anteprima per cassa',
                'text' => 'Entrate e uscite secondo incasso e pagamento.',
            ],
            'recapitulative' => [
                'title' => 'Elenco riepilogativo',
                'text' => 'Cessioni intracomunitarie per partita IVA',
            ],
            'pnl' => [
                'title' => 'Risultato',
                'text' => 'Ricavi e costi per gruppi di conti.',
            ],
            'liquidity' => [
                'title' => 'Liquidità',
                'text' => 'Saldi effettivi, partite aperte e previsione — separati.',
            ],
            'liquidity_forecast' => [
                'title' => 'Previsione di liquidità',
                'text' => '13 settimane di entrate e uscite con comportamento di pagamento e saldo cumulato.',
            ],
            'quality' => [
                'title' => 'Qualità contabile',
                'text' => 'Bozze, esecuzioni bloccate e attese aperte.',
            ],
            'bwa' => [
                'title' => 'Analisi gestionale (BWA)',
                'text' => 'Conto economico a breve termine con anno precedente, mese precedente, griglia mensile e budget.',
            ],
            'budget' => [
                'title' => 'Budget',
                'text' => 'Valori pianificati per conto ed esercizio — valore annuo o valori mensili.',
            ],
            'journal' => [
                'title' => 'Giornale',
                'text' => 'Tutte le registrazioni definitive in ordine di giornale.',
            ],
            'open_items' => [
                'title' => 'Partite aperte',
                'text' => 'Crediti e debiti con scadenzario.',
            ],
        ],
    ],

    // Periodenabschluss (Feature 125, MVP-677).
    'closing' => [
        'title' => 'Chiusura dei periodi',
        'menu' => 'Chiusura',
        'subtitle' => 'Chiudere i periodi in via provvisoria o definitiva — e riaprirli con motivazione.',
        'blocked_hint' => 'La chiusura resta bloccata finché un punto è rosso.',
        'reopen_hint' => 'La riapertura annulla una chiusura. Viene registrata con la motivazione nella catena di prova.',
        'column' => [
            'period' => 'Periodo',
            'closed_at' => 'Chiuso',
            'reopened' => 'Riaperto',
        ],
        'field' => [
            'reason' => 'Motivazione',
        ],
        'action' => [
            'soft_close' => 'Chiudere provvisoriamente',
            'close' => 'Chiudere definitivamente',
            'close_submit' => 'Chiudere il periodo',
            'reopen' => 'Riaprire',
            'reopen_submit' => 'Aprire il periodo',
            'close_year' => 'Chiudere l\'esercizio',
            'propose_depreciation' => 'Proporre gli ammortamenti',
        ],
        'confirm' => [
            'year' => 'Chiudere l\'esercizio? Tutti i periodi devono essere chiusi.',
            'depreciation' => 'Inserire l\'ammortamento :year di tutti i cespiti nella posta contabile come bozze? La registrazione avviene lì.',
        ],
        'check' => [
            'no_drafts' => 'Nessuna bozza aperta nel periodo.',
            'drafts' => ':count registrazioni non sono definitive.',
            'balanced' => 'Tutte le registrazioni sono in pareggio.',
            'unbalanced' => ':count registrazioni non sono in pareggio.',
            'sequence_ok' => 'Nessun periodo precedente aperto.',
            'earlier_open' => ':count periodi precedenti sono ancora aperti.',
            'depreciation_ok' => 'L\'ammortamento annuale di tutti i cespiti è registrato.',
            'depreciation_open' => 'L\'ammortamento annuale non è ancora registrato per :count cespiti.',
            'key' => [
                'drafts' => 'Bozze',
                'balanced' => 'Pareggio',
                'sequence' => 'Sequenza',
                'depreciation' => 'Ammortamento',
            ],
        ],
        'flash' => [
            'soft_closed' => 'Periodo chiuso provvisoriamente.',
            'closed' => 'Periodo chiuso.',
            'reopened' => 'Periodo riaperto.',
            'year_closed' => 'Esercizio chiuso.',
            'depreciation_proposed' => 'Ammortamento :year: :prepared bozze preparate, :skipped già presenti, :failed bloccate.',
        ],
        'error' => [
            'reason_required' => 'La riapertura richiede una motivazione.',
            'already_open' => 'Il periodo è già aperto.',
            'wrong_status' => 'In questo stato (:status) il passaggio non è possibile.',
            'periods_open' => ':count periodi non sono chiusi.',
        ],
    ],

    // Startsalden und DATEV-Übergabe (Feature 125, MVP-677).
    'opening' => [
        'title' => 'Importare i saldi iniziali',
        'subtitle' => 'CSV con conto, Dare e Avere — prima verificare, poi registrare.',
        'hint' => 'L\'MVP riprende saldo iniziale, partite aperte e data di riferimento; un intero giornale storico non viene importato.',
        'field' => [
            'file' => 'File CSV',
        ],
        'action' => [
            'dry_run' => 'Prova',
            'import' => 'Importare',
        ],
        'flash' => [
            'dry_run' => 'Prova: :lines righe, Dare :debit, Avere :credit, :errors errori.',
            'imported' => 'Registrazione di apertura :no creata.',
        ],
        'error' => [
            'missing_account' => 'Riga :line senza conto.',
            'unknown_account' => 'Il conto :account (riga :line) non esiste.',
            'both_sides' => 'La riga :line porta Dare e Avere.',
            'unbalanced' => 'Dare (:debit) e Avere (:credit) non coincidono.',
        ],
    ],
    'datev' => [
        'title' => 'Trasferimento DATEV',
        'subtitle' => 'Righe delle registrazioni del periodo in CSV.',
        'hint' => 'Generato dalle registrazioni definitive — non ricavato di nuovo dai documenti.',
        'action' => [
            'export' => 'Esportare',
        ],
    ],

    // Kontenplan-Vorlagen (Feature 125, MVP-678).
    'template' => [
        'title' => 'Piano dei conti da modello',
        'subtitle' => 'Creare conti, codici IVA e regole di registrazione in un solo passo.',
        'hint_first' => 'Il modello crea conti, codici IVA e regole corrispondenti — la posta contabile è subito utilizzabile.',
        'hint_additive' => 'Solo aggiunta: conti e regole esistenti restano invariati.',
        'disclaimer' => 'Estratto iniziale ispirato al rispettivo piano dei conti standard tedesco, valido per la Germania. La scelta dei conti e la corrispondenza fiscale vanno verificate prima della prima registrazione.',
        'field' => [
            'template' => 'Modello',
        ],
        'action' => [
            'apply' => 'Applicare il modello',
        ],
        'flash' => [
            'applied' => 'Modello applicato: :accounts conti, :tax_codes codici IVA, :rules regole create, :skipped saltati.',
        ],
        'error' => [
            'unknown' => 'Modello di piano dei conti sconosciuto: :code',
        ],
    ],

    // Versteuerungsart (Feature 125, MVP-679).
    'taxation' => [
        'title' => 'Regime IVA',
        'subtitle' => 'Per competenza o per cassa — incide solo sull’analisi IVA.',
        'current' => 'Attuale: :method',
        'default_hint' => 'Senza impostazione vale il regime per competenza (§ 16 c. 1 UStG).',
        'field' => [
            'method' => 'Regime',
            'valid_from' => 'Valido dal',
        ],
        'hint' => [
            'method' => 'Il regime per cassa (§ 20 UStG) richiede un\'autorizzazione; l\'IVA a credito non è interessata.',
            'valid_from' => 'Di norma al cambio d\'anno — viene proposto il 1° gennaio successivo.',
        ],
        'column' => [
            'changeover' => 'Partite aperte al cambio',
        ],
        'action' => [
            'switch' => 'Cambiare regime',
            'switch_submit' => 'Registrare il cambio',
        ],
        'changeover' => [
            'headline' => ':count partite aperte per :amount sono interessate alla data di riferimento.',
            'hint' => '§ 20 per. 3 UStG: le operazioni non devono essere rilevate due volte né restare non tassate. Il cambio non viene bloccato — la valutazione spetta al consulente fiscale.',
            'summary' => ':count partite / :amount',
        ],
        'flash' => [
            'switched' => 'Regime IVA cambiato.',
        ],
        'error' => [
            'unchanged' => 'Questo regime vale già alla data indicata.',
            'later_section' => 'Esiste già un periodo successivo dal :date.',
        ],
    ],
    // Klärungsbuchung und interne Umbuchung (Feature 125, MVP-681).
    'clearing' => [
        'title' => 'Scrittura in sospeso',
        'memo' => 'Caso da chiarire: :purpose',
        'no_account' => 'Nessun conto transitorio configurato. Contrassegna un conto del piano dei conti come conto transitorio.',
        'action' => [
            'post' => 'Registrare su conto transitorio',
            'post_submit' => 'Crea la scrittura in sospeso',
        ],
        'field' => [
            'account' => 'Conto transitorio',
            'note' => "Perché l'operazione non è chiara?",
            'follow_up_on' => 'Data di richiamo',
        ],
        'hint' => [
            'account' => 'Vengono proposti solo i conti contrassegnati espressamente come transitori.',
            'note' => 'Obbligatorio — è la sola traccia del motivo per cui qui non è stato attribuito nulla.',
            'follow_up_on' => 'Il caso va risolto entro questa data.',
        ],
        'error' => [
            'not_a_clearing_account' => 'Il conto scelto non è un conto transitorio.',
            'note_required' => 'La motivazione è obbligatoria.',
        ],
        'blocker' => [
            'unassigned' => 'Nessun documento attribuito — registrabile solo tramite attribuzione o conto transitorio.',
        ],
        'flash' => [
            'posted' => 'Scrittura in sospeso creata.',
        ],
    ],
    'transfer' => [
        'title' => 'Giroconto interno',
        'action' => [
            'record' => 'Giroconto interno',
            'record_submit' => 'Registra il giroconto',
        ],
        'field' => [
            'from_account' => 'Dal conto',
            'to_account' => 'Al conto',
        ],
        'hint' => [
            'note' => 'Per cosa è stato spostato il denaro (p. es. prelievo bancario per la cassa)?',
        ],
        'error' => [
            'same_account' => 'Conto di partenza e di destinazione devono essere diversi.',
            'not_a_money_account' => 'Il conto :account non è un conto bancario, di cassa o transitorio.',
            'amount_positive' => 'L’importo deve essere maggiore di zero.',
        ],
        'flash' => [
            'recorded' => 'Giroconto registrato.',
        ],
    ],

    // Meldepflichten der Umsatzsteuer (Feature 125, MVP-684).
    'filing' => [
        'fields' => [
            'title' => 'Codici della dichiarazione',
            'subtitle' => 'Abbinamento dei codici IVA ai campi della dichiarazione tedesca — ausilio di riconciliazione, non il modulo.',
            'tax_codes' => 'Codici IVA',
            'remaining' => 'Acconto residuo (83)',
            'unclear' => 'Codice IVA :code senza numero di campo.',
            'column' => [
                'field' => 'Codice',
                'base' => 'Base imponibile',
                'tax' => 'Imposta',
            ],
            'hint' => [
                'base' => 'Campo della base imponibile, p. es. 81 (19 %), 86 (7 %), 41 (cessioni intracom.).',
                'tax' => 'Campo dell’imposta, p. es. 66 (IVA a credito), 61 (acquisto intracom.).',
            ],
            'flash' => [
                'saved' => 'Codici salvati.',
            ],
        ],
        'calendar' => [
            'menu' => 'Scadenze fiscali',
            'title' => 'Scadenze fiscali',
            'subtitle' => 'Scadenze IVA e stato di evasione.',
            'hint' => 'Le scadenze sono calcolate (§ 108 c. 3 AO: fine settimana e festivi slittano al giorno lavorativo successivo). Non viene trasmesso nulla.',
            'tax_advised' => 'con consulente fiscale',
            'overdue' => 'In ritardo',
            'empty' => 'Nessuna scadenza nel periodo.',
            'column' => [
                'kind' => 'Obbligo',
                'due_on' => 'Scadenza',
            ],
            'action' => [
                'submitted' => 'Segna come presentato',
            ],
        ],
        'notification' => [
            'title' => ':kind in scadenza',
            'message' => 'Periodo :period — scadenza :due.',
        ],
        'no_period' => 'Per questa organizzazione non è impostato alcun periodo di liquidazione (piccolo imprenditore § 19 UStG).',
        'prepayment_memo' => 'Acconto speciale 1/11 per :year',
        'prepayment' => [
            'title' => "Registra l'acconto speciale",
            'submit' => "Registra l'acconto",
            'calculation' => 'Un undicesimo da :year: imposta :tax, annualizzata :annualised → :amount.',
            'annualised_hint' => "Attività per soli :months mesi l'anno scorso — riportata all'anno intero (§ 47 c. 3 UStDV).",
            'due_hint' => 'Dichiarazione e pagamento entro il :date.',
        ],
        'title' => 'Obblighi dichiarativi',
        'subtitle' => 'Periodo di liquidazione IVA, proroga permanente e scadenze.',
        'current' => 'Attualmente: :interval',
        'default_hint' => 'Senza impostazione vale il trimestre solare (§ 18 c. 2 per. 1 UStG).',
        'field' => [
            'period' => 'Periodo',
            'remaining' => 'Acconto residuo',
            'prepayment_account' => 'Conto acconto speciale',
            'money_account' => 'Conto di liquidità',
            'interval' => 'Periodo di liquidazione',
            'valid_from' => 'Valido dal',
            'year' => 'Anno solare',
            'granted_on' => 'Concessa il',
            'special_prepayment' => 'Acconto speciale (1/11)',
        ],
        'hint' => [
            'prepayment_account' => 'Di norma 1781 (SKR03) o 3830 (SKR04) — acconti IVA 1/11.',
            'interval' => "Il periodo lo decide l'ufficio delle imposte — il programma lo registra soltanto.",
            'valid_from' => "Di norma un cambio d'anno; è possibile anche un cambio in corso d'anno.",
            'granted_on' => 'Lasciare vuoto finché la proroga non è concessa.',
            'special_prepayment' => "Un undicesimo degli acconti dell'anno precedente; dichiarazione e pagamento entro il 10 febbraio (§ 47 UStDV).",
        ],
        'action' => [
            'switch' => 'Cambia periodo',
            'switch_submit' => 'Applica il periodo',
        ],
        'error' => [
            'note_required' => '«Non necessario» richiede una motivazione.',
            'amount_positive' => "L'importo deve essere maggiore di zero.",
            'not_a_money_account' => 'Il conto scelto non è un conto bancario o di cassa.',
            'no_extension' => 'Per :year non è registrata alcuna proroga.',
            'unchanged' => 'Questo periodo di liquidazione è già valido a quella data.',
            'later_section' => 'Esiste già una sezione dal :date. Modificare prima quella.',
        ],
        'flash' => [
            'marked' => 'Stato registrato.',
            'prepayment_posted' => 'Acconto speciale registrato.',
            'switched' => 'Periodo di liquidazione modificato.',
            'extension_saved' => 'Proroga salvata.',
        ],
        'suggestion' => [
            'headline' => 'Proposta da :year (imposta :amount): :interval.',
            'monthly' => "Oltre 9.000 € di imposta dell'anno precedente — mensile (§ 18 c. 2 per. 2 UStG).",
            'quarterly' => 'Tra 2.000 € e 9.000 € — trimestre solare (§ 18 c. 2 per. 1 UStG).',
            'annual' => 'Fino a 2.000 € — possibile esonero dalla dichiarazione periodica (§ 18 c. 2 per. 3 UStG).',
            'none' => 'Nessuna dichiarazione IVA periodica (piccolo imprenditore § 19 UStG).',
            'founder_rule' => "Dal periodo d'imposta 2027 le nuove imprese tornano all'obbligo mensile.",
        ],
        'extension' => [
            'short' => 'con proroga',
            'title' => 'Proroga permanente',
            'active' => 'Proroga dal :year',
            'no_prepayment' => 'Chi liquida trimestralmente ottiene la proroga senza acconto speciale (§ 46 UStDV).',
            'prepayment_note' => 'Acconto speciale :amount registrato per :year.',
        ],
    ],

    // Zusammenfassende Meldung (Feature 125, MVP-687).
    'recapitulative' => [
        'title' => 'Elenco riepilogativo',
        'hint' => 'Comunicazione ai sensi del § 18a UStG. La proroga permanente NON si applica — il termine resta il 25° giorno dopo il periodo.',
        'due' => 'Scadenza: :date',
        'interval' => 'Periodo: :interval',
        'total' => 'Cessioni intracomunitarie',
        'column' => [
            'vat_id' => 'Partita IVA',
        ],
        'unclear' => [
            'missing_vat_id' => 'Registrazione :entry (:customer) senza partita IVA del destinatario.',
            'unknown_customer' => 'senza cliente',
        ],
    ],

    // Betriebswirtschaftliche Auswertung und Budget (Feature 142, MVP-709).
    'bwa' => [
        'title' => 'Analisi gestionale (BWA)',
        'menu' => 'BWA & budget',
        'hint' => 'Conto economico a breve termine per gruppi di conti — assegnazione tramite la riga BWA del conto o l\'intervallo SKR, non un report certificato.',
        'compare_range' => 'Periodo di confronto :from – :to',
        'scheme' => [
            'skr03' => 'Piano dei conti SKR03 rilevato.',
            'skr04' => 'Piano dei conti SKR04 rilevato.',
            'none' => 'Nessun piano dei conti standard rilevato — valgono solo le righe BWA esplicite dei conti.',
        ],
        'column' => [
            'row' => 'Riga',
            'actual' => 'Effettivo',
            'budget' => 'Piano',
            'total' => 'Totale',
            'delta' => 'Scostamento',
            'delta_pct' => 'Scost. %',
        ],
        'compare' => [
            'none' => 'Nessun confronto',
            'previous_year' => 'Anno precedente',
            'previous_month' => 'Mese precedente',
            'months' => 'Griglia mensile',
            'budget' => 'Budget',
        ],
        'filter' => [
            'compare' => 'Confronto',
            'cost_center' => 'Centro di costo',
            'all_cost_centers' => 'Tutti i centri di costo',
        ],
        'subtotal' => [
            'total_output' => 'Produzione complessiva',
            'gross_profit' => 'Margine lordo',
            'operating_gross_profit' => 'Margine lordo operativo',
            'total_costs' => 'Costi totali',
            'operating_result' => 'Risultato operativo',
            'result_before_tax' => 'Risultato ante imposte',
            'result' => 'Risultato provvisorio',
            'result_total' => 'Risultato incl. conti non assegnati',
        ],
        'unmapped' => [
            'title' => 'Non assegnato',
            'hint' => ':count conti con movimenti non hanno una riga BWA — assegnarla sul conto; non alimentano alcun gruppo ma la riga finale.',
        ],
        'chart' => [
            'groups' => 'Effettivo per riga BWA',
            'months' => 'Ricavi e costi totali per mese',
        ],
    ],

    'budget' => [
        'title' => 'Budget',
        'subtitle' => 'Valori pianificati per conto per l\'esercizio :year',
        'empty' => 'Nessun conto economico nel piano dei conti.',
        'total' => 'Risultato pianificato',
        'column' => [
            'year_value' => 'Valore annuo',
            'mode' => 'Tipo',
            'note' => 'Nota',
        ],
        'filter' => [
            'year' => 'Esercizio',
        ],
        'action' => [
            'edit' => 'Modifica budget',
            'copy_previous' => 'Effettivo anno precedente come budget',
            'save' => 'Salva',
        ],
        'confirm' => [
            'copy_previous' => 'Riprendere l\'effettivo dell\'esercizio :year come budget? I budget esistenti dell\'anno scelto verranno sostituiti.',
        ],
        'mode' => [
            'year' => 'Valore annuo',
            'months' => 'Valori mensili',
        ],
        'hint' => [
            'mode' => 'Un valore annuo viene ripartito uniformemente su dodici mesi per i confronti mensili; i valori mensili valgono per mese.',
            'sign' => 'Valori positivi: ricavo atteso o costo atteso.',
        ],
        'flash' => [
            'saved' => 'Budget per :account salvato.',
            'copied' => ':count conti con effettivo di :year ripresi come budget.',
        ],
        'note' => [
            'copied_from' => 'Ripreso dall\'effettivo :year',
        ],
    ],

];
