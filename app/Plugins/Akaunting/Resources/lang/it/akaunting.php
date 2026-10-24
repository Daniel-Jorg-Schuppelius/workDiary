<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : akaunting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Rispecchia in sola lettura fatture e fatture fornitori di Akaunting nel flusso documenti, con collegamento all’originale. I fornitori vengono collegati tramite assegnazioni esistenti o il nome.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’installazione Akaunting, ad es. https://contabilita.example.com (senza /api).',
        'email' => 'E-mail',
        'email_help' => 'Accesso di un utente Akaunting con il permesso «leggere API» e il permesso di lettura su fatture e fatture fornitori.',
        'password' => 'Password',
        'company_id' => 'ID azienda',
        'company_id_help' => 'Numero dell’azienda in Akaunting; compare nell’indirizzo dell’interfaccia (ad es. /1/…).',
        'pull_vouchers' => 'Recuperare documenti',
        'pull_vouchers_help' => 'Solo con questa impostazione il recupero orario legge i documenti. Non scrive nulla in Akaunting.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’installazione nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
    'health' => [
        'not_configured' => 'Akaunting non è configurato (mancano l’indirizzo o l’accesso).',
    ],
];
