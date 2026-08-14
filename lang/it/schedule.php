<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : schedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'qualification' => [
        'missing' => 'Qualifica obbligatoria mancante',
    ],
    'availability' => [
        'title' => 'Disponibilità e preferenze turni',
        'subtitle' => 'Gestisci la tua disponibilità e le preferenze sui turni. La pianificazione ne tiene conto per i suggerimenti di copertura.',
        'windows_legend' => 'Finestre di disponibilità',
        'desired_legend' => 'Preferenze turni',
        'no_windows' => 'Nessuna finestra di disponibilità.',
        'no_desired' => 'Nessuna preferenza turno.',
        'window_saved' => 'Disponibilità salvata.',
        'window_deleted' => 'Disponibilità eliminata.',
        'desired_saved' => 'Preferenza salvata.',
        'desired_deleted' => 'Preferenza eliminata.',
    ],
    'exchange' => [
        'title' => 'Scambio turno',
        'subtitle' => 'Cedi o scambia turni — efficace solo dopo l\'approvazione del responsabile.',
        'mine_legend' => 'Le mie richieste',
        'pending_legend' => 'In attesa di approvazione',
        'no_mine' => 'Nessuna richiesta di scambio aperta o passata.',
        'no_pending' => 'Nessuna richiesta da approvare.',
        'swap' => 'Scambio',
        'open_target' => 'aperto',
        'requested' => 'Richiesta di scambio inviata.',
        'accepted' => 'Scambio accettato.',
        'cancelled' => 'Richiesta ritirata.',
        'approved' => 'Scambio approvato.',
        'rejected' => 'Scambio rifiutato.',
        'error_not_owner' => 'Solo la persona assegnata può cedere questo turno.',
        'error_cancelled_shift' => 'Il turno è annullato e non può essere scambiato.',
        'error_not_requestable' => 'Questa richiesta non può più essere accettata.',
        'error_not_target' => 'Non sei il partner di scambio richiesto.',
        'error_not_open' => 'La richiesta è già stata decisa.',
        'error_not_decidable' => 'Questa richiesta non può più essere decisa.',
        'error_no_target' => 'Nessun partner di scambio impostato.',
        'error_compliance' => 'L\'approvazione violerebbe le regole sull\'orario di lavoro (riposo/ore massime/sovrapposizione/assenza).',
        'notification_request_title' => 'Scambio turno richiesto',
        'notification_request_message' => 'È stato richiesto uno scambio per il turno del :date.',
        'notification_pending_message' => 'Lo scambio turno del :date attende la tua approvazione.',
        'notification_decided_title' => 'Scambio turno deciso',
        'notification_decided_message' => 'Il tuo scambio turno del :date è stato :status.',
    ],
    'suggest' => [
        'button' => 'Suggerimenti',
        'reason_qualified' => 'qualifica idonea',
        'reason_preferred_window' => 'disponibilità preferita',
        'reason_available' => 'disponibile',
        'reason_wished' => 'preferenza turno',
    ],
    'coverage' => [
        'under_title' => 'Sottorganico: turni richiesti scoperti',
    ],
    // MVP-515: priorità + indicatori dei desideri nella pianificazione.
    'wish' => [
        'priority_label' => 'Priorità',
        'priority_none' => 'Nessuna',
        'priority_1' => 'Alta (1)',
        'priority_2' => 'Media (2)',
        'priority_3' => 'Bassa (3)',
        'priority_short' => 'Prio',
        'fulfilled' => 'Desiderio esaudito',
        'conflict' => 'Conflitto di desiderio',
    ],
];
