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
        'connect_dropbox' => 'Collega Dropbox',
    ],
    'dropbox' => [
        'description' => 'Legge i documenti dalle cartelle Dropbox monitorate (ingresso documenti cloud) — con regole di cartella, prova di trasferimento e inbox per i casi dubbi.',
        'health' => [
            'not_configured' => 'Chiavi app Dropbox non configurate.',
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Dropbox richiede attenzione (riautenticazione/bloccata).',
            'backup_attention' => 'La destinazione di backup Dropbox richiede attenzione (riautenticazione/bloccata) — riguarda tutte le organizzazioni.',
            'ok' => 'Connessioni Dropbox in ordine.',
            'error' => 'Controllo stato non riuscito (:class).',
        ],
    ],
];
