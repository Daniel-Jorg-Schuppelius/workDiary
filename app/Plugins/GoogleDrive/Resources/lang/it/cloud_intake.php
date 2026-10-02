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
        'connect_google' => 'Collega Google Drive',
    ],
    'google' => [
        'description' => 'Legge i documenti dalle cartelle Google Drive monitorate (ingresso documenti cloud) — Il mio Drive e Drive condivisi; rollout bloccato fino alla verifica OAuth di Google.',
        'health' => [
            'not_configured' => 'Chiavi client Google Drive non configurate.',
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Google Drive richiede attenzione (riautenticazione/bloccata).',
            'backup_attention' => 'La destinazione di backup Google Drive richiede attenzione (riautenticazione/bloccata) — riguarda tutte le organizzazioni.',
            'ok' => 'Connessioni Google Drive in ordine.',
            'error' => 'Controllo stato non riuscito (:class).',
        ],
    ],
];
