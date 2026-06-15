<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reporting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'target' => [
        'nav' => 'Valori obiettivo',
        'title' => 'Valori obiettivo & benchmark',
        'subtitle' => 'Definisci valori obiettivo per indicatore – i report mostrano obiettivo, valore effettivo e scostamento.',
        'create' => 'Aggiungi valore obiettivo',
        'edit' => 'Modifica valore obiettivo',
        'empty' => 'Nessun valore obiettivo ancora definito.',
        'metric_label' => 'Indicatore',
        'scope_label' => 'Ambito',
        'scope_ref' => 'Oggetto di riferimento',
        'scope_ref_hint' => 'Selezionare solo per cliente/progetto/dipendente.',
        'value_label' => 'Valore obiettivo',
        'period_label' => 'Periodo di riferimento',
        'valid_from' => 'Valido dal',
        'valid_until' => 'Valido fino al',
        'note_label' => 'Nota',
        'created' => 'Il valore obiettivo è stato creato.',
        'updated' => 'Il valore obiettivo è stato aggiornato.',
        'deleted' => 'Il valore obiettivo è stato eliminato.',
        'delete_confirm' => 'Eliminare davvero questo valore obiettivo?',
        'none' => '–',
        'soll' => 'Obiettivo',
        'ist' => 'Effettivo',
        'deviation' => 'Scostamento',
        'met' => 'raggiunto',
        'missed' => 'mancato',
        'no_target' => 'Nessun obiettivo',
        'metric' => [
            'contributionMargin' => 'Margine di contribuzione (%)',
            'billableRate' => 'Quota fatturabile (%)',
            'reworkShare' => 'Quota di rilavorazione (%)',
            'slaComplianceRate' => 'Tasso di rispetto SLA (%)',
            'utilization' => 'Utilizzo (%)',
        ],
        'scope' => [
            'org' => 'Organizzazione (globale)',
            'customer' => 'Cliente',
            'project' => 'Progetto',
            'user' => 'Dipendente',
        ],
        'period' => [
            'month' => 'Mese',
            'quarter' => 'Trimestre',
            'year' => 'Anno',
        ],
    ],

    'cohort' => [
        'nav' => 'Confronto coorti',
        'title' => 'Confronto coorti (prima/dopo la formazione)',
        'subtitle' => 'Confronta un indicatore per dipendente nel periodo prima e dopo l\'acquisizione di una formazione.',
        'qualification' => 'Formazione / qualifica',
        'metric' => [
            'billableRate' => 'Quota fatturabile (%)',
            'reworkShare' => 'Quota di rilavorazione (%)',
        ],
        'metric_label' => 'Indicatore',
        'window' => 'Finestra di confronto (giorni)',
        'choose' => 'Seleziona una formazione.',
        'member' => 'Dipendente',
        'acquired_on' => 'Acquisita il',
        'before' => 'Prima',
        'after' => 'Dopo',
        'delta' => 'Δ',
        'improved' => 'Migliorato',
        'no_date' => 'nessuna data di acquisizione',
        'no_date_hint' => 'Senza una data di acquisizione registrata (qualifica "valida dal") non è possibile formare una suddivisione prima/dopo.',
        'no_data_window' => 'Registrazioni di tempo insufficienti in una delle finestre.',
        'aggregate' => 'Coorte totale (media)',
        'members_with_date' => 'con data di acquisizione',
        'members_without_date' => 'senza data di acquisizione',
        'improved_count' => 'migliorati',
        'data_note' => 'Fonte della data di acquisizione: il "valido dal" dell\'assegnazione della qualifica. Gli indicatori derivano dagli stessi campi di registrazione del tempo (fatturabile/non fatturabile) della vista redditività.',
    ],
];
