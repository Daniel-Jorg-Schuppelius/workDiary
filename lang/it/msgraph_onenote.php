<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_onenote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// OneNote-Übernahme (Feature 155, MVP-815): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'Importa da OneNote',
    'intro' => 'Importa un blocco appunti OneNote una volta o su richiesta come note o articoli della knowledge base, in sola lettura (Notes.Read), senza riscrittura né sincronizzazione continua. Il blocco appunti diventa una raccolta, le sezioni sottoraccolte.',
    'badge_connected' => 'Connesso',
    'badge_disabled' => 'Disattivato',
    'account' => 'Account connesso',
    'connect' => 'Connetti OneNote',
    'disconnect' => 'Disconnetti OneNote',
    'open_hub' => 'Vai a «Conoscenza»',
    'enable_hint' => 'Attiva prima «Consenti importazione OneNote» nelle impostazioni del plugin: solo allora la connessione richiede l’autorizzazione aggiuntiva Notes.Read.',
    'flash' => [
        'not_configured' => 'Microsoft 365 non è configurato (mancano MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'L’accesso è scaduto o non valido: ricomincia.',
        'oauth_denied' => 'Il consenso è stato annullato.',
        'oauth_failed' => 'La connessione non è riuscita (:class).',
        'connected' => 'OneNote connesso.',
        'disconnected' => 'OneNote disconnesso: token di accesso rimosso.',
        'disabled' => 'L’importazione OneNote è disattivata nelle impostazioni del plugin.',
    ],
];
