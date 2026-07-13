<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carddav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CardDAV',
    'intro' => 'I contatti vengono letti da una rubrica CardDAV self-hosted (Nextcloud/Radicale/Baïkal) e inseriti nella inbox di integrazione come proposte di abbinamento — nessuna unione automatica, nessuna scrittura sui dati dei clienti. Le schede invariate vengono saltate (UID+ETag).',
    'description' => 'Legge i contatti da una rubrica CardDAV (RFC 6352) e li inserisce nella inbox di integrazione come proposte di abbinamento — sola lettura, on-premise, senza account Microsoft/Google.',

    'health' => [
        'ok' => 'Connesso',
        'failing' => 'Non raggiungibile',
        'inactive' => 'Inattivo',
        'no_connection' => 'Nessuna connessione CardDAV configurata.',
        'inactive_or_incomplete' => 'La connessione CardDAV è disattivata o incompleta.',
        'unreachable' => 'Server CardDAV non raggiungibile o credenziali non valide.',
        'error' => 'Errore CardDAV (:class).',
        'last_error' => 'Ultimo errore: :error',
    ],

    'action' => [
        'discover' => 'Cerca rubriche',
        'choose_addressbook' => 'Usa questa rubrica',
        'sync' => 'Sincronizza ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'connection' => [
        'heading' => 'Connessione',
    ],

    'addressbook' => [
        'heading' => 'Rubrica',
        'current' => 'Sorgente di sincronizzazione attuale: :name',
        'hint' => 'Usare «Cerca rubriche» per interrogare il server e poi scegliere una rubrica come sorgente di sincronizzazione.',
    ],

    'status' => [
        'last_synced' => 'Ultima sincronizzazione :at.',
    ],

    'field' => [
        'name' => 'Denominazione',
        'base_url' => 'URL di base DAV',
        'base_url_help' => 'Nextcloud: .../remote.php/dav — Radicale/Baïkal: radice del server. La ricerca segue la RFC 6764 (.well-known/carddav).',
        'username' => 'Nome utente',
        'app_password' => 'Password per app',
        'password_keep' => '•••••••• (lasciare invariata)',
        'password_help' => 'Con la 2FA attiva (ad es. Nextcloud) è obbligatoria una password per app. Salvata cifrata.',
        'allow_private_network' => 'Consenti indirizzi privati/interni',
        'allow_private_network_help' => 'Attivare solo se il server CardDAV si trova nella propria rete (ad es. 192.168.x.x). L\'operazione è sottoposta ad audit.',
        'active' => 'Attivo',
    ],

    'flash' => [
        'saved' => 'Connessione CardDAV salvata.',
        'invalid_url' => 'L\'URL di base deve iniziare con http:// o https://.',
        'private_url_blocked' => 'L\'URL di base punta a un indirizzo privato/interno. Attivare il consenso agli indirizzi privati per un server nella propria rete.',
        'password_required' => 'Per una nuova connessione è necessaria una password per app.',
        'no_connection' => 'Nessuna connessione CardDAV attiva disponibile.',
        'discovery_failed' => 'Ricerca delle rubriche non riuscita — server non raggiungibile o credenziali non valide.',
        'no_addressbooks' => 'Nessuna rubrica trovata sul server.',
        'discovered' => ':count rubriche trovate — scegliere una sorgente di sincronizzazione.',
        'addressbook_not_discovered' => 'Eseguire prima «Cerca rubriche» e scegliere una rubrica trovata.',
        'addressbook_saved' => 'Rubrica impostata come sorgente di sincronizzazione.',
        'not_syncable' => 'Sincronizzazione non possibile — connessione inattiva, in errore o nessuna rubrica selezionata.',
        'sync_done' => 'Sincronizzazione avviata. I nuovi contatti appariranno come proposte nella inbox di abbinamento.',
        'disconnected' => 'Connessione CardDAV disconnessa. Le proposte già inserite vengono conservate.',
    ],
];
