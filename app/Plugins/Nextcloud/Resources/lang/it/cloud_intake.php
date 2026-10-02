<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_nextcloud' => 'Collega Nextcloud',
    ],
    'field' => [
        'name' => 'Nome',
    ],
    'nextcloud' => [
        'description' => 'Acquisisce documenti dalle cartelle Nextcloud monitorate (WebDAV) — con regole di cartella, prova di consegna e posta in arrivo per i casi ambigui.',
        'health' => [
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Nextcloud richiede attenzione (ri-autenticazione/bloccata).',
            'backup_attention' => 'La destinazione di backup Nextcloud richiede attenzione (riautenticazione/bloccata) — riguarda tutte le organizzazioni.',
            'ok' => 'Connessioni Nextcloud in ordine.',
            'error' => 'Controllo di integrità non riuscito (:class).',
        ],
        'connect_title' => 'Collega Nextcloud',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega',
        'field' => [
            'server_url' => 'URL del server',
            'server_url_help' => 'Solo HTTPS. Esempio: https://cloud.example.com',
            'username' => 'Nome utente',
            'app_password' => 'Password app',
            'app_password_help' => 'Una password app revocabile (Impostazioni › Sicurezza), mai la password normale dell’account.',
        ],
        'validation' => [
            'https_required' => 'L’URL del server deve iniziare con https://.',
            'unsafe_url' => 'L’URL del server deve essere raggiungibile pubblicamente (nessuna destinazione interna/privata).',
        ],
    ],
];
