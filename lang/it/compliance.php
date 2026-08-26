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
            'category' => 'Ambito',
            'all_categories' => 'Tutti gli ambiti',
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
            'lateRecording' => 'Registrazione tardiva (MiLoG)',
            'sixMonthAverage' => 'Media semestrale (§ 3 ArbZG)',
            'nightWork' => 'Lavoro notturno oltre 8 h (§ 6 ArbZG)',
            'substituteRestDay' => 'Giorno di riposo sostitutivo mancante (§ 11 ArbZG)',
            'freeSundays' => 'Domeniche libere insufficienti (§ 11 ArbZG)',
            // Feature 144 (MVP-719): Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV).
            'dailyDriving' => 'Tempo di guida giornaliero (art. 6 reg. 561/2006)',
            'weeklyDriving' => 'Tempo di guida settimanale (art. 6 reg. 561/2006)',
            'fortnightDriving' => 'Tempo di guida bisettimanale (art. 6 reg. 561/2006)',
            'drivingBreakMissing' => 'Interruzione di guida mancante (art. 7 reg. 561/2006)',
            'dailyRest' => 'Riposo giornaliero (art. 8 reg. 561/2006)',
            'weeklyRest' => 'Riposo settimanale (art. 8 reg. 561/2006)',
        ],
        'unit' => [
            'days' => '{1} :count giorno|[2,*] :count giorni',
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
            'drivingTime' => 'Tempi di guida',
        ],
        'acknowledged' => 'Violazione aggiornata.',
        'error' => [
            'invalid_status' => 'Stato di destinazione non valido.',
            'not_acknowledgeable' => 'Questa violazione non può più essere confermata.',
            'note_required' => 'Per «accettato» è richiesta una motivazione.',
        ],
    ],
    'milog' => [
        'button' => 'Prova MiLoG (dogana)',
        'csv' => [
            'employee' => 'Dipendente',
            'personnel_number' => 'Numero personale',
            'date' => 'Data',
            'start' => 'Inizio',
            'end' => 'Fine',
            'breaks' => 'Pause (min)',
            'duration' => 'Durata',
        ],
    ],
    'driving' => [
        'button' => 'Prova tempi di guida',
        'title' => 'Prova dei tempi di guida e di riposo',
        'thresholds_note' => 'Tempi di guida/riposo (reg. (CE) 561/2006 / FPersV): max. 9 h di guida/giorno (10 h due volte a settimana) · 56 h/settimana · 90 h/due settimane · interruzione di 45 min dopo 4,5 h (frazionabile 15 + 30) · riposo 11 h/giorno (max. 3×/settimana 9 h) · 45 h/settimana (24 h con compensazione).',
        'disclaimer' => 'La base dati sono i viaggi registrati (libro di bordo) con veicoli contrassegnati; i dati del tachigrafo/DTCO non vengono letti. Nessuna consulenza legale.',
        'csv' => [
            'driver' => 'Conducente',
            'personnel_number' => 'Matricola',
            'date' => 'Data',
            'vehicles' => 'Veicoli',
            'start' => 'Prima partenza',
            'end' => 'Ultimo arrivo',
            'driving' => 'Tempo di guida',
            'longest_stint' => 'Periodo di guida più lungo senza interruzione',
            'breaks' => 'Interruzioni (min)',
            'rest_before' => 'Riposo precedente',
            'findings' => 'Rilievi',
        ],
        'badge' => [
            'label' => 'Tempo di guida',
            'remaining' => ':remaining rimanenti',
            'until_break' => 'Pausa tra :until',
            'break_due' => 'Pausa dovuta',
            'exhausted' => 'Tempo di guida giornaliero esaurito',
            'title' => 'Tempo di guida giornaliero residuo :daily (limite :limit) · prossima interruzione tra :until · residuo settimanale :weekly · due settimane :fortnight',
        ],
    ],
];
