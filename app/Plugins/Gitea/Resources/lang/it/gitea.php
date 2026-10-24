<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gitea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa i ticket di repository selezionati di un’istanza Gitea o Forgejo self-hosted come attività in progetti WorkDiary, in sola lettura: titolo, descrizione, aperto/chiuso e scadenza. Conflitti e casi da verificare finiscono nella posta in arrivo delle integrazioni.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Apri in Gitea/Forgejo',
    'health' => [
        'not_configured' => 'Gitea/Forgejo non è configurato (manca l’URL di base o il token).',
        'not_connected' => 'Connessione a Gitea/Forgejo non ancora verificata.',
    ],
    'settings' => [
        'base_url' => 'URL di base',
        'base_url_help' => 'Indirizzo dell’istanza Gitea o Forgejo, ad es. https://git.example.com (senza /api/v1).',
        'api_token' => 'Token di accesso',
        'api_token_help' => 'Lo crei in Gitea/Forgejo in Impostazioni → Applicazioni, con accesso in lettura a repository e ticket. Servono gli ambiti read:user, read:repository e read:issue.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un URL di base con indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
