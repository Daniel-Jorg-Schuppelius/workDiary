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
    'nextcloud' => [
        'connect_title' => 'Collega Nextcloud',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega',
        'field' => [
            'name' => 'Nome',
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
