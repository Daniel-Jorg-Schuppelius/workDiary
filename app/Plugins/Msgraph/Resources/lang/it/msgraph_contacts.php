<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Invio contatti (Feature 102, taglio D): sezione nel pannello Msgraph + pulsante nella scheda cliente.
return [
    'heading' => 'Inviare i contatti a Outlook',
    'intro' => 'Invia i clienti WorkDiary come contatti Outlook dell’account collegato su richiesta (idempotente — nessun duplicato in caso di reinvio).',
    'badge_connected' => 'Connesso',
    'badge_inactive' => 'Disconnesso',
    'account' => 'Account connesso',
    'connect' => 'Connetti l’invio dei contatti',
    'disconnect' => 'Disconnetti l’invio dei contatti',
    'push_button' => 'Verso Outlook',
    'flash' => [
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'La procedura di accesso è scaduta o non è valida — riprovare.',
        'oauth_denied' => 'L’autorizzazione è stata annullata.',
        'oauth_failed' => 'La connessione non è riuscita (:class).',
        'connected' => 'Invio dei contatti a Outlook connesso.',
        'disconnected' => 'Invio dei contatti disconnesso — token di accesso rimossi.',
        'no_connection' => 'Nessuna connessione contatti Microsoft 365 stabilita.',
        'plugin_disabled' => 'Il plugin Microsoft 365 non è attivato.',
        'pushed' => 'Cliente inviato come contatto Outlook (ID :id).',
        'push_failed' => 'Invio non riuscito (:class).',
    ],
];
