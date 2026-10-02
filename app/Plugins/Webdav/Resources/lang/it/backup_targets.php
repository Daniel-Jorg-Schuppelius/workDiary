<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webdav' => [
        'connect_title' => 'Collega destinazione WebDAV',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega e verifica',
        'selftest_hint' => 'Al collegamento viene creata una cartella di prova, scritto un file, riletto e poi eliminato.',
        'field' => [
            'name' => 'Nome',
            'server_url' => 'URL della collection',
            'server_url_help' => 'Solo HTTPS. La collection WebDAV completa, ad es. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Nome utente',
            'password' => 'Password',
            'password_help' => 'Preferibilmente un token di accesso dedicato e revocabile anziché la password dell’account.',
            'base_path' => 'Sottocartella (facoltativa)',
            'base_path_help' => 'Vuoto = direttamente nella collection. La cartella pseudonimo viene creata al di sotto.',
        ],
        'validation' => [
            'https_required' => 'L’URL della collection deve iniziare con https://.',
            'unsafe_url' => 'L’URL della collection deve essere raggiungibile pubblicamente (nessuna destinazione interna/privata).',
        ],
        'flash' => [
            'selftest_failed' => 'Il test di connessione non è riuscito (:class). La destinazione non è stata attivata.',
        ],
    ],
];
