<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : seafile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura file da una libreria di un’istanza Seafile self-hosted nella ricezione documenti cloud e funge da destinazione di backup cifrata. Le regole di ricezione indirizzano i file verso le fatture in entrata o l’archivio documenti.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza Seafile per la ricezione documenti, ad es. https://seafile.example.com (senza /api2).',
        'api_token' => 'Token API',
        'api_token_help' => 'Token di account di un utente Seafile con accesso in lettura alla libreria. Per la ricezione WorkDiary non modifica nulla in Seafile. Il token viene restituito da POST /api2/auth-token/ con nome utente e password (con accesso a due fattori anche l’intestazione X-Seafile-OTP con il codice attuale).',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
    'action' => [
        'connect_intake' => 'Collegare Seafile',
    ],
    'connect' => [
        'title' => 'Collegare Seafile',
        'legend' => 'Connessione',
        'instance' => 'Istanza dalle impostazioni del plugin: :instance',
        'name' => 'Nome della connessione',
        'hint' => 'Poi scelga la libreria, la cartella radice al suo interno e crei le regole. Le librerie cifrate non vengono proposte.',
        'submit' => 'Verificare la connessione',
        'not_configured' => 'Inserisca prima indirizzo e token API nelle impostazioni del plugin.',
        'to_settings' => 'Alle impostazioni del plugin',
    ],
    'container' => [
        'library' => 'Libreria',
    ],
    'flash' => [
        'not_configured' => 'Seafile non è configurato: mancano l’indirizzo o il token API nelle impostazioni del plugin.',
        'instance_changed' => 'Le impostazioni del plugin puntano a un’istanza Seafile diversa da questa connessione. Prima la scolleghi; i giustificativi già acquisiti restano.',
    ],
    'health' => [
        'not_configured' => 'Seafile non è configurato (mancano l’indirizzo o il token).',
        'not_connected' => 'Ancora nessuna connessione Seafile nella ricezione documenti cloud.',
        'attention' => 'La connessione Seafile segnala un errore — vedi ricezione documenti cloud.',
        'backup_attention' => 'Una destinazione di backup Seafile richiede attenzione — vedi destinazioni di backup.',
    ],
    'backup' => [
        'connect_title' => 'Collegare Seafile come destinazione di backup',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collegare',
        'name' => 'Nome',
        'server_url' => 'URL del server',
        'server_url_help' => 'Solo HTTPS. Esempio: https://seafile.example.com',
        'api_token' => 'Token API',
        'api_token_help' => 'Token di account di un utente Seafile dedicato ai backup, con accesso in scrittura alla libreria. Il token viene restituito da POST /api2/auth-token/ con nome utente e password (con accesso a due fattori anche l’intestazione X-Seafile-OTP con il codice attuale).',
        'library' => 'Libreria',
        'library_help' => 'Nome o ID di una libreria esistente non cifrata. WorkDiary scrive solo in una propria cartella al suo interno.',
        'https_required' => 'L’URL del server deve iniziare con https://.',
        'unsafe_url' => 'L’URL del server deve essere raggiungibile pubblicamente (nessuna destinazione interna/privata).',
        'library_not_found' => 'Questa libreria non esiste per l’account. La crei in Seafile o verifichi nome e condivisione.',
        'library_encrypted' => 'Questa libreria è cifrata. Scelga una libreria non cifrata — WorkDiary cifra il backup da sé.',
        'library_ambiguous' => 'Più librerie hanno questo nome. Indichi invece l’ID della libreria.',
        'library_read_only' => 'L’account non può scrivere in questa libreria. Scelga una libreria con permesso di lettura e scrittura.',
    ],
];
