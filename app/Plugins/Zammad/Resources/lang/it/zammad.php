<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : zammad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zammad',
    'intro' => 'I ticket di un gruppo Zammad associato arrivano come attività in WorkDiary — per il tracciamento del tempo, le prove e la fatturazione. Il sistema di ticket resta l\'autorità; una nuova importazione non crea mai duplicati.',

    'health' => [
        'ok' => 'Connesso',
        'failing' => 'Irraggiungibile',
        'inactive' => 'Inattivo',
    ],

    'action' => [
        'sync' => 'Importa ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'connection' => [
        'heading' => 'Connessione',
    ],

    'field' => [
        'name' => 'Etichetta',
        'base_url' => 'URL istanza',
        'api_token' => 'Token API',
        'token_keep' => '•••••••• (lascia invariato)',
        'token_help' => 'Zammad: Profilo → Accesso token. Memorizzato cifrato.',
        'webhook_secret' => 'Secret webhook (facoltativo)',
        'webhook_help' => 'Secret condiviso per la firma del webhook (X-Hub-Signature). Vuoto = webhook disattivato, solo polling.',
        'default_project' => 'Progetto predefinito',
        'no_project' => '— senza progetto (globale) —',
        'active' => 'Attivo',
        'resolved_state' => 'Ritorno di stato (stato di destinazione)',
        'resolved_state_help' => 'Facoltativo: stato di destinazione del ticket al completamento dell\'attività (es. «closed»). Vuoto = disattivato.',
    ],

    'queue' => [
        'heading' => 'Coda → progetto',
        'help' => 'Associa i gruppi Zammad (ID gruppo) a un progetto WorkDiary. Senza corrispondenza si applica il progetto predefinito, altrimenti l\'attività viene creata globalmente.',
        'group_id' => 'ID gruppo',
    ],

    'flash' => [
        'saved' => 'Connessione Zammad salvata.',
        'sync_done' => 'Importazione ticket avviata.',
        'disconnected' => 'Connessione Zammad disconnessa. Attività e collegamenti vengono conservati.',
        'no_connection' => 'Nessuna connessione Zammad attiva.',
        'invalid_url' => 'L\'URL dell\'istanza deve iniziare con http:// o https://.',
        'token_required' => 'Una nuova connessione richiede un token API.',
    ],
    'resolution' => [
        'note' => 'Risolto in WorkDiary.',
    ],
];
