<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : intake_upload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Upload-Kanal der Kundeneingänge (MVP-1078): Einstellungen und Health.
 */

return [
    'settings' => [
        'server_url' => 'Canale di caricamento: URL del server',
        'server_url_help' => 'Indirizzo Nextcloud (https) per i link di caricamento delle richieste dei clienti, separato da acquisizione documenti e backup.',
        'username' => 'Canale di caricamento: utente',
        'app_password' => 'Canale di caricamento: password per app',
        'app_password_help' => 'Password per app revocabile da Nextcloud (Impostazioni → Sicurezza), mai la password dell\'account.',
        'base_folder' => 'Canale di caricamento: cartella di base',
        'link_days' => 'Canale di caricamento: validità dei link (giorni)',
        'link_days_help' => 'Da 1 a 90 giorni; poi il link viene revocato dopo l\'ultima acquisizione.',
    ],
    'health' => [
        'attention' => 'Non è stato possibile recuperare almeno un link di caricamento delle richieste dei clienti.',
    ],
];
