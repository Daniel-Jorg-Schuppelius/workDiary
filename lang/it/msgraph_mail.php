<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Invio e-mail via Graph (Feature 102): sezione mail del pannello di amministrazione Msgraph + messaggi del flusso.
return [
    'heading' => 'Invio e-mail tramite Microsoft 365',
    'intro' => 'Invia le e-mail di WorkDiary (fatture, solleciti, notifiche) tramite Microsoft Graph invece di SMTP — autenticazione moderna, senza SMTP con autenticazione di base.',
    'badge_connected' => 'Connesso',
    'badge_inactive' => 'Disconnesso',
    'mailer_hint' => 'Il mailer msgraph non è attualmente attivo. Attivarlo tramite MAIL_MAILER=msgraph (o una catena failover che includa msgraph) nell’installazione.',
    'account' => 'Account connesso',
    'from_address' => 'Indirizzo mittente (opzionale)',
    'from_placeholder' => 'es. fatturazione@azienda.it (casella condivisa)',
    'from_hint' => 'Vuoto = l’account connesso invia a proprio nome. Un indirizzo diverso richiede il permesso Exchange «Invia come» e lo scope Mail.Send.Shared.',
    'save_to_sent' => 'Salvare una copia nella cartella Posta inviata',
    'connect' => 'Connetti l’invio e-mail',
    'disconnect' => 'Disconnetti l’invio e-mail',
    'flash' => [
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'La procedura di accesso è scaduta o non è valida — riprovare.',
        'oauth_denied' => 'L’autorizzazione è stata annullata.',
        'oauth_failed' => 'La connessione non è riuscita (:class).',
        'connected' => 'Invio e-mail tramite Microsoft 365 connesso.',
        'disconnected' => 'Invio e-mail disconnesso — token di accesso rimossi.',
        'no_connection' => 'Nessuna connessione mail Microsoft 365 stabilita.',
        'settings_saved' => 'Impostazioni e-mail salvate.',
    ],
];
