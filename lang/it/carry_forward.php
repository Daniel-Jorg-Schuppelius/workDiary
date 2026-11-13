<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carry_forward.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Merker für die nächste Periode (Feature 201, MVP-1645): Komponente <x-carry-forward-notes>.
return [
    'title' => 'Promemoria per questa volta',
    'empty' => [
        'title' => 'Nessun promemoria aperto',
        'message' => 'Dai periodi precedenti non c’è nulla per :period.',
    ],
    'open_heading' => 'Promemoria aperti dai periodi precedenti',
    'left_by' => 'Lasciato da :name in :period, valido da :from.',
    'resolution_label' => 'Nota alla spunta',
    'resolution_placeholder' => 'Breve nota (facoltativa), ad es. «certificato richiesto»',
    'resolve' => 'Spunta',
    'resolved_heading' => 'Promemoria spuntati qui',
    'resolved_by' => 'Spuntato da :name il :date',
    'left_heading' => 'Lasciato per dopo',
    'from_period' => 'da :period',
    'withdraw' => 'Ritira promemoria',
    'withdraw_confirm' => 'Vuole davvero ritirare questo promemoria? La prossima volta non comparirà.',
    'new_label' => 'Promemoria per la prossima volta',
    'new_hint' => 'Compare in alto alla prossima occorrenza di questa serie finché qualcuno non lo spunta. Le altre note di questa pratica non vengono riportate.',
    'target_label' => 'Valido',
    'create' => 'Lascia promemoria',
    'help' => 'Come funzionano i promemoria?',
    // Cockpit von Kunde, Lieferant, Projekt und Asset (MVP-1646).
    'cockpit' => [
        'summary' => 'I promemoria aperti non sono stati spuntati; compaiono alla prossima occorrenza della serie a partire dal periodo di destinazione.',
        'contracts' => 'Promemoria sugli obblighi contrattuali',
        'inspections' => 'Promemoria sugli obblighi di verifica',
        'recurring_orders' => 'Promemoria sugli ordini ricorrenti',
        'period_orders' => 'Promemoria sugli ordini con periodo',
    ],
    'flash' => [
        'created' => 'Promemoria per la prossima volta salvato.',
        'resolved' => 'Promemoria spuntato.',
        'withdrawn' => 'Promemoria ritirato.',
    ],
    'error' => [
        'no_series' => 'Questa pratica non appartiene a nessuna serie con periodo; qui non è possibile un promemoria.',
        'target_not_after' => 'Un promemoria vale al più presto dal periodo successivo.',
        'empty' => 'Inserisca un testo per il promemoria.',
        'foreign_series' => 'Questo promemoria non appartiene a questa serie.',
        'already_resolved' => 'Questo promemoria è già spuntato.',
        'not_due' => 'Questo promemoria vale solo da un periodo successivo.',
    ],
];
