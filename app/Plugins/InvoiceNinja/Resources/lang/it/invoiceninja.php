<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoiceninja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Rispecchia in sola lettura fatture, note di credito e spese di Invoice Ninja nel flusso documenti, con collegamento all’originale. Clienti e fornitori vengono collegati solo tramite assegnazioni esistenti, il numero cliente o il nome del fornitore.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Lasci vuoto per Invoice Ninja in hosting; per la propria installazione il suo indirizzo, ad es. https://fatture.example.com.',
        'api_token' => 'Token API',
        'api_token_help' => 'In Invoice Ninja lo crei in Impostazioni → Gestione account → Token API. Il suo utente necessita dei diritti di amministratore o del diritto di vedere fatture, note di credito e spese, altrimenti vede solo i propri. L’Invoice Ninja ospitato offre l’API solo nei piani a pagamento.',
        'pull_vouchers' => 'Recuperare documenti',
        'pull_vouchers_help' => 'Solo con questa impostazione il recupero orario legge i documenti. Non scrive nulla in Invoice Ninja.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’installazione nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
    'health' => [
        'not_configured' => 'Invoice Ninja non è configurato (manca il token API).',
    ],
];
