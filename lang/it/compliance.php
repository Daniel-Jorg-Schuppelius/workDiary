<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'report' => [
        'title' => 'Conformità orario di lavoro',
        'nav' => 'Conformità orario di lavoro',
        'subtitle' => 'Violazioni della legge sull’orario di lavoro in base al tempo di lavoro effettivamente registrato.',
        'empty' => 'Nessuna violazione nel periodo.',
        'thresholds_note' => 'Soglie (ArbZG): max :daily netto/giorno · min :rest di riposo · max media :weekly/settimana · pause obbligatorie di 30 min oltre 6 h, 45 min oltre 9 h.',
        'corrected' => 'corretto',
        'corrected_hint' => 'Per questo giorno esiste una correzione orario approvata.',
        'drilldown' => 'Apri chiusura giornaliera',
        'filter' => [
            'kind' => 'Tipo di violazione',
            'all' => 'Tutti i tipi',
        ],
        'kpi' => [
            'total' => 'Violazioni totali',
            'employees' => 'Dipendenti interessati',
        ],
        'kind' => [
            'maxDailyHours' => 'Durata massima giornaliera',
            'restPeriod' => 'Tempo di riposo',
            'breakMissing' => 'Pausa obbligatoria',
            'maxWeeklyHours' => 'Durata massima settimanale',
            'frameTime' => 'Fascia oraria consentita',
            'coreTime' => 'Orario centrale',
            'entryBreakMissing' => 'Pausa obbligatoria (tempo di progetto)',
            'missingCheckout' => 'Timbratura di uscita mancante',
            'freeDayStamp' => 'Timbratura in giorno libero',
            'absenceStamp' => 'Timbratura durante assenza',
            'attendanceFrameTime' => 'Fascia oraria (timbrature)',
        ],
        'severity' => [
            'error' => 'Violazione',
            'warning' => 'Avviso',
        ],
        'col' => [
            'date' => 'Data',
            'kind' => 'Tipo',
            'value' => 'Valore',
            'threshold' => 'Soglia',
            'severity' => 'Gravità',
        ],
        'csv' => [
            'employee' => 'Dipendente',
            'date' => 'Data',
            'kind' => 'Tipo',
            'severity' => 'Gravità',
            'value' => 'Valore',
            'threshold' => 'Soglia',
            'corrected' => 'Corretto',
            'yes' => 'sì',
        ],
    ],
    'history' => [
        'title' => 'Violazioni di conformità',
        'nav' => 'Cronologia violazioni',
        'subtitle' => 'Violazioni ArbZG persistite con stato di elaborazione e presa in carico.',
        'to_report' => 'Report dettagliato',
        'to_dashboard' => 'Dashboard',
        'filter' => [
            'status' => 'Stato',
            'all' => 'Tutti gli stati',
            'category' => 'Categoria',
        ],
        'col' => [
            'employee' => 'Dipendente',
            'status' => 'Stato',
        ],
        'empty' => 'Nessuna violazione persistita.',
        'note_placeholder' => 'Motivazione (obbligatoria per «accettato»)',
        'btn' => [
            'acknowledge' => 'Conferma',
            'accept' => 'Accetta',
            'correction' => 'Richiesta di correzione',
        ],
        'category' => [
            'arbzg' => 'ArbZG',
            'plausibility' => 'Casi da chiarire',
        ],
        'acknowledged' => 'Violazione aggiornata.',
        'error' => [
            'invalid_status' => 'Stato di destinazione non valido.',
            'not_acknowledgeable' => 'Questa violazione non può più essere confermata.',
            'note_required' => 'Per «accettato» è richiesta una motivazione.',
        ],
    ],
];
