<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mcp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// MCP-Server für KI-Assistenten (MVP-1063/1064).
return [
    'error' => [
        'forbidden' => 'Nessun accesso a questo strumento.',
        'scope' => 'Questo token non è abilitato per gli assistenti IA (MCP). Crei un token con il permesso «Assistente IA (MCP)».',
        'not_found' => 'Non trovato.',
        'invalid_number' => 'Riga :position: quantità, prezzo o aliquota non è un numero.',
        'conflicts' => 'Non spostato — conflitti: :list',
    ],
    'oauth' => [
        'title' => 'Collegare l’assistente IA',
        'intro' => ':client desidera accedere ai dati di :organization in workDiary per conto di :user.',
        'scope' => [
            'read' => 'Lettura: clienti, progetti, ordini, preventivi, fatture, partite aperte, tempi, appuntamenti, indicatori e ricerca — con i Suoi permessi.',
            'write' => 'Creare bozze: bozze di preventivi e fatture, creare clienti, spostare interventi. Emissione e invio restano a Lei.',
        ],
        'target' => 'Dopo il consenso, l’accesso viene concesso a',
        'untrusted_warning' => 'Questa destinazione non appartiene ad alcun assistente IA noto. Acconsenta solo se ha appena configurato Lei stesso il collegamento, altrimenti un estraneo ottiene accesso ai Suoi dati.',
        'untrusted_confirm' => 'Ho configurato io stesso il collegamento a :target.',
        'untrusted_required' => 'Confermi di aver configurato Lei stesso il collegamento a questa destinazione.',
        'revoke_hint' => 'Può revocare l’accesso in qualsiasi momento in Profilo → Token API.',
        'approve' => 'Consenti accesso',
        'deny' => 'Rifiuta',
        'error' => [
            'title' => 'Connessione non possibile',
            'client' => 'Client sconosciuto: configurare di nuovo il connettore.',
            'redirect_uri' => 'L’indirizzo di ritorno non è registrato per questo client o non è consentito (solo https o indirizzi locali).',
            'response_type' => 'È supportato solo il tipo di risposta «code».',
            'pkce' => 'È richiesto PKCE con S256.',
            'resource' => 'La risorsa richiesta non è questo server MCP.',
            'scope' => 'Nessuna autorizzazione valida richiesta (mcp:read, mcp:write).',
            'grant' => 'Il codice o il token di aggiornamento non è valido, è scaduto o è già stato usato.',
            'grant_type' => 'Questo tipo di concessione non è supportato.',
            'disabled' => 'La Sua organizzazione non ha abilitato gli assistenti IA (MCP). L’amministrazione lo abilita nelle impostazioni dell’organizzazione.',
            'no_organization' => 'Il Suo account non appartiene ad alcuna organizzazione.',
        ],
    ],
];
