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
    'intro' => 'I ticket aperti visibili al token API arrivano come attività in WorkDiary — per il tracciamento del tempo, le prove e la fatturazione. Con «Solo gruppi associati» solo i gruppi associati a un progetto. Il sistema di ticket resta l\'autorità; una nuova importazione non crea mai duplicati.',

    'health' => [
        'ok' => 'Connesso',
        'failing' => 'Irraggiungibile',
        'inactive' => 'Inattivo',
    ],

    'action' => [
        'sync' => 'Importa ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
        'switch_target' => 'Cambia destinazione',
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
        'webhook_url' => 'Indirizzo del webhook (da inserire in Zammad sotto Webhook come endpoint, con il secret come token di firma HMAC SHA1)',
        'default_project' => 'Progetto predefinito',
        'no_project' => '— senza progetto (globale) —',
        'active' => 'Attivo',
        'resolved_state' => 'Ritorno di stato (stato di destinazione)',
        'resolved_state_help' => 'Facoltativo: stato di destinazione del ticket al completamento dell\'attività (es. «closed»). Vuoto = disattivato.',
        'time_unit' => 'Registrazione del tempo nel ticket',
        'time_unit_off' => 'Disattivato',
        'time_unit_minute' => 'Minuti',
        'time_unit_hour' => 'Ore',
        'time_unit_help' => 'Facoltativo: registra nel ticket, come rilevazione del tempo, i tempi registrati sulle attività collegate. L\'unità deve corrispondere all\'unità di rilevazione del tempo in Zammad. Disattivato = nessuna registrazione.',
        'allow_private_network' => 'Consenti indirizzi privati/interni',
        'allow_private_network_help' => 'Attivare solo se Zammad si trova nella propria rete (ad es. 192.168.x.x). L\'operazione è sottoposta ad audit e ha effetto solo se il gestore lo consente.',
    ],

    'queue' => [
        'heading' => 'Coda → progetto',
        'help' => 'Associa i gruppi Zammad (ID gruppo) a un progetto WorkDiary. Senza corrispondenza si applica il progetto predefinito, altrimenti l\'attività viene creata globalmente.',
        'group_id' => 'ID gruppo',
        'limited' => 'Solo gruppi associati',
        'limited_help' => 'Attivo: arrivano come attività solo i ticket dei gruppi associati qui a un progetto. Disattivo: tutti i ticket visibili al token API.',
    ],

    'flash' => [
        'saved' => 'Connessione Zammad salvata.',
        'sync_done' => 'Importazione ticket avviata.',
        'disconnected' => 'Connessione Zammad disconnessa. Attività e collegamenti vengono conservati.',
        'no_connection' => 'Nessuna connessione Zammad attiva.',
        'invalid_url' => 'L\'URL dell\'istanza deve iniziare con http:// o https://.',
        'token_required' => 'Una nuova connessione richiede un token API.',
        'private_url_blocked' => 'L\'URL dell\'istanza punta a un indirizzo privato/interno. Per uno Zammad nella propria rete, attivi l\'autorizzazione degli indirizzi privati.',
        'helpdesk_required' => 'I ticket di servizio richiedono il modulo Helpdesk.',
        'queue_required' => 'Scelga una coda.',
        'target_switched' => 'Destinazione dei ticket cambiata.',
    ],

    'target' => [
        'heading' => 'Destinazione dei ticket',
        'help' => 'Stabilisce se i nuovi ticket arrivano come attività o come ticket di servizio di una coda. I ticket già importati restano dove sono. Conflitti di assegnazione aperti nell\'Inbox di riconciliazione impediscono il cambio.',
        'current' => 'Attualmente',
        'task' => 'Attività',
        'service_ticket' => 'Ticket di servizio',
        'service_ticket_in' => 'Ticket di servizio in «:queue»',
        'field' => 'Nuovi ticket come',
        'queue' => 'Coda',
        'queue_help' => 'Solo per i ticket di servizio. Zammad gestisce poi i ticket di questa coda.',
        'no_queue' => '— scegliere la coda —',
        'helpdesk_hint' => 'I ticket di servizio richiedono il modulo Helpdesk.',
        'no_queues_hint' => 'Non esiste ancora alcuna coda. La crei in Service desk → Code.',
        'confirm_title' => 'Cambiare la destinazione dei ticket',
        'confirm' => 'I nuovi ticket arriveranno poi per la via scelta; quelli già importati restano dove sono. Il cambio viene registrato.',
    ],

    'guard' => [
        'subject' => 'L\'URL dell\'istanza',
        'private_hint' => 'Per uno Zammad nella propria rete, l\'autorizzazione degli indirizzi privati deve essere attiva sulla connessione.',
    ],

    'resolution' => [
        'note' => 'Risolto in WorkDiary.',
    ],
];
