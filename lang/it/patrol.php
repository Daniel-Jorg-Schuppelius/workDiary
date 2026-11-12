<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Rapporto ronde',
        'subtitle' => 'Ronde per percorso e sito nel periodo: previste, puntuali, in ritardo e mancate.',
        'expected' => 'Punti previsti',
        'onTime' => 'puntuale',
        'late' => 'in ritardo',
        'missed' => 'mancato',
        'note' => 'Motivazione',
        'runs' => 'Ronde',
        'punctuality' => 'Puntualità',
        'aborted' => 'interrotte',
        'allRoutes' => 'Tutti i percorsi',
        'allSites' => 'Tutti i siti',
        'empty' => 'Nessuna ronda nel periodo.',
        'summary' => ':runs ronde, di cui :completed completate e :aborted interrotte',
    ],
    'scan' => [
        'implausibleTime' => 'L’orario di scansione del dispositivo è fuori dalla ronda: controllare l’orologio del dispositivo.',
    ],
    // Gestione e scostamenti (MVP-1611).
    'route' => [
        'edit_title' => 'Modifica percorso',
        'responsible' => 'Responsabile degli scostamenti',
        'responsible_hint' => 'Riceve il punto aperto in caso di scostamenti. Vuoto: il responsabile del team della persona che svolge la ronda.',
        'updated' => 'Percorso salvato.',
        'deactivate_confirm' => 'Disattivare il percorso? Una ronda in corso può terminare, non se ne possono avviare di nuove.',
        'deactivated' => 'Percorso disattivato: non può più essere avviato.',
        'activated' => 'Il percorso è di nuovo attivo.',
    ],
    'checkpoint' => [
        'edit_title' => 'Modifica punto di controllo',
        'edit_hint' => 'Le modifiche valgono per le scansioni future; le scansioni registrate mantengono la loro valutazione.',
        'updated' => 'Punto di controllo «:label» salvato.',
        'deactivate_confirm' => 'Disattivare il punto di controllo? Da ora non è più previsto e non può più essere scansionato; le ronde precedenti restano invariate.',
        'deactivated_done' => 'Punto di controllo «:label» disattivato.',
        'deactivated' => 'Questo punto di controllo è disattivato.',
        'inactive_badge' => 'disattivato',
    ],
    'deviation' => [
        'running_title' => 'La ronda «:route» presenta scostamenti',
        'late_line' => '«:label» fuori dalla finestra (:delta min)',
        'missed_line' => ':count punti di controllo scaduti senza scansione',
        'overdue_title' => 'Ronda «:route» non terminata',
        'overdue_line' => 'Durata prevista di :minutes minuti superata (inizio :start).',
    ],
];
