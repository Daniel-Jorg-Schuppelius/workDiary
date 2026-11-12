<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfwesen: Bearbeiten, Deaktivieren, Storno, Roh-Messwerte (MVP-1575).
return [
    'action' => [
        'deactivate' => 'Disattivare',
        'activate' => 'Attivare',
    ],
    'state' => [
        'inactive' => 'disattivato',
    ],
    'profile' => [
        'edit' => 'Modificare il profilo di ispezione',
        'updated' => 'Profilo di ispezione salvato.',
        'activated' => 'Profilo di ispezione attivato.',
        'deactivated' => 'Profilo di ispezione disattivato.',
        'deactivate_confirm' => 'Disattivare il profilo di ispezione? Non sarà più disponibile per nuove assegnazioni; obblighi e verbali esistenti restano.',
        'inactive_title' => 'Profili dell\'organizzazione disattivati',
        'interval_hint' => 'Vale per tutti gli obblighi senza intervallo proprio, dalla prossima ispezione.',
    ],
    'requirement' => [
        'edit' => 'Modificare il requisito',
        'edit_hint' => 'I limiti modificati valgono per le nuove ispezioni; i verbali precedenti mantengono i limiti di allora.',
        'updated' => 'Requisito salvato.',
        'activated' => 'Requisito attivato.',
        'deactivated' => 'Requisito disattivato.',
    ],
    'assignment' => [
        'edit' => 'Modificare l\'obbligo di ispezione',
        'updated' => 'Obbligo di ispezione salvato.',
        'activated' => 'Obbligo di ispezione ripreso.',
        'deactivated' => 'Obbligo di ispezione disattivato.',
        'deactivate_confirm' => 'Disattivare l\'obbligo di ispezione? Gli appuntamenti aperti vengono annullati e il blocco per scadenza di questo obbligo termina; i verbali restano.',
        'interval_override' => 'Intervallo per questo asset (mesi)',
        'interval_override_hint' => 'Vuoto = intervallo del profilo di ispezione (:months mesi).',
        'next_due_on' => 'Prossima scadenza',
        'next_due_hint' => 'Vuoto = ricalcolare dall\'ultima ispezione se cambia l\'intervallo.',
        'inactive_title' => 'Obblighi di ispezione disattivati',
        'show_active' => 'Mostrare gli attivi',
        'show_inactive' => 'Disattivati (:count)',
        'block_released' => 'Obbligo di ispezione modificato o disattivato.',
    ],
    'schedule' => [
        'cancel_title' => 'Annullare l\'appuntamento di ispezione',
        'cancel_action' => 'Annullare',
        'cancel_hint' => 'L\'appuntamento resta in elenco con il motivo (filtro «Annullato»). L\'obbligo di ispezione prosegue.',
        'cancelled' => 'Appuntamento di ispezione annullato.',
        'none_for_status' => 'Nessun appuntamento di ispezione con questo stato.',
        'cancelled_by_deactivation' => 'Obbligo di ispezione disattivato.',
        'cancelled_by_decommission' => 'Asset dismesso.',
        'error' => [
            'not_cancellable' => 'Questo appuntamento di ispezione è già stato eseguito o annullato.',
        ],
    ],
    'measurement' => [
        'title' => 'Misure grezze',
        'hint' => 'Serie di misure libera senza verifica dei limiti; le righe senza valore non vengono salvate.',
        'label' => 'Punto di misura',
        'value' => 'Valore misurato',
    ],
    'protocol' => [
        'title' => 'Verbali di ispezione (ultimi 20)',
        'performed_at' => 'Eseguita',
        'values' => 'Risultati e misure',
        'out_of_limit' => 'fuori limite',
        'empty' => 'Nessuna ispezione ancora documentata.',
    ],
];
