<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => 'Regole di maggiorazione',
        'rules_subtitle' => 'Maggiorazioni notturne, del fine settimana e festive per organizzazione: fascia oraria, percentuale e voce retributiva per il trasferimento paghe.',
        'rules_help' => 'Come funzionano le regole di maggiorazione?',
        'rules_help_text' => 'Ogni regola descrive i periodi soggetti a maggiorazione (fascia notturna, sabato, domenica, giorno festivo o fascia personalizzata) con percentuale e voce retributiva. Durante l\'export dei tempi le presenze vengono suddivise di conseguenza e riportate come righe di export aggiuntive per giorno. In caso di sovrapposizione vince la percentuale più alta — le maggiorazioni non si sommano.',
        'create_rule' => 'Crea regola di maggiorazione',
        'edit_rule' => 'Modifica regola di maggiorazione',
        'empty' => 'Nessuna regola di maggiorazione',
        'export_summary' => 'Maggiorazioni per collaboratore e voce retributiva',
    ],

    'field' => [
        'basics' => 'Dati di base',
        'code' => 'Codice',
        'code_help' => 'Chiave breve e univoca (minuscole, cifre, ._-), ad es. «night».',
        'label' => 'Denominazione',
        'label_placeholder' => 'ad es. Maggiorazione notturna',
        'kind' => 'Tipo',
        'kind_help' => 'Notte/Personalizzato usano la fascia oraria; sabato, domenica e festivo valgono per l\'intera giornata.',
        'window' => 'Fascia oraria',
        'window_help' => 'Solo per Notte/Personalizzato. Le fasce oltre la mezzanotte (ad es. 23:00–06:00) sono ammesse e vengono suddivise correttamente.',
        'window_start' => 'Fascia da',
        'window_end' => 'Fascia a',
        'whole_day' => 'intera giornata',
        'percentage' => 'Maggiorazione (%)',
        'payroll' => 'Trasferimento paghe',
        'wage_type_code' => 'Voce retributiva',
        'wage_type_code_help' => 'Numero della voce per DATEV/Lexware (ad es. 2010). Vuoto = esportare senza voce.',
        'tax_free_limit_pct' => 'Esente fino a (%)',
        'tax_free_limit_pct_help' => "Limiti § 3b EStG configurabili (es. notte 25/40, domenica 50, festivo 125/150). Vuoto = nessuna divisione. Oltre il limite il resto viene esportato come quota imponibile con una propria voce salariale.",
        'taxable_wage_type_code' => 'Voce salariale quota imponibile',
        'taxable_wage_type_code_help' => "Obbligatoria quando il limite esente è inferiore alla maggiorazione. Il tetto in € del salario base resta alla paghe esterna.",
        'priority' => 'Priorità',
        'priority_help' => 'Spareggio a parità di percentuale: vince la priorità più alta.',
        'validity' => 'Validità',
        'valid_from' => 'Valida dal',
        'valid_until' => 'Valida fino al',
        'unlimited' => 'illimitata',
        'active' => 'Attiva',
        'rule_active' => 'La regola è attiva',
        'hours' => 'Ore',
        'yes' => 'Sì',
        'no' => 'No',
    ],

    'action' => [
        'create' => 'Crea',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'delete_confirm' => 'Eliminare davvero questa regola di maggiorazione? Gli export esistenti restano invariati.',
    ],

    'flash' => [
        'created' => 'Regola di maggiorazione creata.',
        'updated' => 'Regola di maggiorazione aggiornata.',
        'deleted' => 'Regola di maggiorazione eliminata.',
    ],

    'validation' => [
        'taxable_wage_type_required' => "La quota imponibile richiede una propria voce salariale.",
    ],
];
