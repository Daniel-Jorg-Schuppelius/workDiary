<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_tasks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sincronizzazione To Do (Feature 102, taglio E): sezione nel pannello Msgraph + messaggi del flusso.
return [
    'heading' => 'Sincronizzare Microsoft To Do',
    'intro' => 'Sincronizza le liste To Do collegate con i progetti WorkDiary (modello Todoist): fusione a tre vie, i conflitti vanno nella inbox delle integrazioni — mai last-write-wins; le cancellazioni remote vengono solo segnalate.',
    'badge_connected' => 'Connesso',
    'badge_inactive' => 'Disconnesso',
    'account' => 'Account connesso',
    'connect' => 'Connetti la sincronizzazione To Do',
    'disconnect' => 'Disconnetti la sincronizzazione To Do',
    'link' => [
        'list' => 'Lista To Do',
        'target' => 'Destinazione',
        'project' => 'Progetto',
        'global' => 'Kanban globale',
        'mode' => 'Direzione',
        'add' => 'Collega',
        'remove' => 'Rimuovi',
        'remove_confirm' => 'Rimuovere davvero questo collegamento? Le attività e i riferimenti già sincronizzati vengono mantenuti.',
    ],
    'mode' => [
        'bidirectional' => 'Entrambe le direzioni',
        'todo_to_workdiary' => 'Solo To Do → WorkDiary',
        'workdiary_to_todo' => 'Solo WorkDiary → To Do',
    ],
    'flash' => [
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'La procedura di accesso è scaduta o non è valida — riprovare.',
        'oauth_denied' => 'L’autorizzazione è stata annullata.',
        'oauth_failed' => 'La connessione non è riuscita (:class).',
        'connected' => 'Microsoft To Do connesso.',
        'disconnected' => 'Sincronizzazione To Do disconnessa — token di accesso rimossi.',
        'no_connection' => 'Nessuna connessione Microsoft To Do stabilita.',
        'list_invalid' => 'La lista To Do selezionata non è più disponibile.',
        'project_invalid' => 'Il progetto selezionato non appartiene a questa organizzazione.',
        'link_saved' => 'Collegamento lista salvato.',
        'link_removed' => 'Collegamento lista rimosso.',
    ],
];
