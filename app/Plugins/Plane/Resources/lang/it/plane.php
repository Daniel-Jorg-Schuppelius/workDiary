<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura gli elementi di lavoro di progetti Plane selezionati come attività nei progetti WorkDiary: titolo, descrizione, stato secondo il gruppo di stato e data obiettivo. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Workspace',
    'task_link' => 'Aprire in Plane',
    'health' => [
        'not_configured' => 'Plane non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione Plane non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’API: https://api.plane.so per Plane Cloud, altrimenti l’indirizzo della propria istanza (senza /api/v1).',
        'workspace_slug' => 'Identificativo del workspace',
        'workspace_slug_help' => 'La parte dell’indirizzo dopo l’host, ad es. «acme» in https://app.plane.so/acme/.',
        'api_key' => 'Chiave API',
        'api_key_help' => 'La crei in Plane in Impostazioni profilo → Token di accesso personali; vede i progetti di cui il profilo è membro. Richiede Plane 0.28 o successivo.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
