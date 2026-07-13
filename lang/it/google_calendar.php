<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : google_calendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Google Calendar',
    'intro' => 'Gli appuntamenti WorkDiary vengono pubblicati tramite l\'API Google Calendar in un calendario dell\'account Google collegato. WorkDiary resta la fonte autorevole; gli appuntamenti annullati vi scompaiono e le esecuzioni ripetute non creano mai duplicati. Gli appuntamenti esterni non vengono mai letti.',
    'plugin_description' => 'Pubblica gli appuntamenti in modo idempotente in un calendario Google (Calendar API v3, OAuth2) — solo pubblicazione, calendario di destinazione selezionabile.',
    'not_configured_hint' => 'GOOGLE_CALENDAR_CLIENT_ID/SECRET non sono impostati — la connessione richiede prima un client OAuth nella Google Cloud Console (gli scope calendario sono “sensitive”: verifica del brand o tipo di consenso “Internal” per Workspace).',

    'health' => [
        'badge_ok' => 'Connesso',
        'badge_failing' => 'Non raggiungibile',
        'badge_inactive' => 'Inattivo',
        'not_configured' => 'Google Calendar non è configurato (GOOGLE_CALENDAR_CLIENT_ID/SECRET mancanti).',
        'no_org_context' => 'Configurato (nessuna organizzazione nel contesto).',
        'no_connection' => 'Nessuna connessione Google Calendar stabilita.',
        'inactive' => 'La connessione Google Calendar è disconnessa o disattivata.',
        'ok' => 'Connesso — elenco calendari disponibile.',
        'failing' => 'API Google Calendar non raggiungibile o accesso negato.',
        'error' => 'Errore Google Calendar (:class).',
    ],

    'action' => [
        'connect' => 'Collega a Google',
        'publish' => 'Pubblica ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'calendar' => [
        'heading' => 'Calendario di destinazione',
        'help' => 'In quale calendario dell\'account collegato viene pubblicato. Senza selezione viene usato il calendario principale.',
        'target' => 'Calendario',
        'default' => 'Calendario principale',
    ],

    'flash' => [
        'not_configured' => 'Google Calendar non è configurato (GOOGLE_CALENDAR_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'Il flusso OAuth è scaduto o non è valido. Riprovare.',
        'oauth_denied' => 'La connessione è stata rifiutata o annullata.',
        'oauth_failed' => 'Lo scambio dei token non è riuscito (:class).',
        'connected' => 'Account Google collegato.',
        'disconnected' => 'Connessione Google Calendar disconnessa. Gli appuntamenti già pubblicati restano nel sistema esterno.',
        'no_connection' => 'Nessuna connessione Google Calendar attiva.',
        'calendar_saved' => 'Calendario di destinazione salvato.',
        'calendar_invalid' => 'Il calendario selezionato non è stato trovato.',
        'publish_done' => 'Pubblicazione avviata.',
    ],
];
