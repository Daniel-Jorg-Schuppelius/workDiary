<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Calendario Microsoft 365',
    'intro' => 'Gli appuntamenti WorkDiary vengono pubblicati tramite Microsoft Graph in un calendario dell\'account Microsoft 365 collegato. WorkDiary resta la fonte autorevole; gli appuntamenti annullati vi scompaiono e le esecuzioni ripetute non creano mai duplicati. Gli appuntamenti esterni non vengono mai letti.',
    'plugin_description' => 'Pubblica gli appuntamenti in modo idempotente in un calendario Microsoft 365 (Microsoft Graph, OAuth2) — solo pubblicazione, calendario di destinazione selezionabile.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (ed eventualmente MSGRAPH_TENANT) non sono impostati — la connessione richiede prima una registrazione dell\'app nel tenant Microsoft.',

    'health' => [
        'badge_ok' => 'Connesso',
        'badge_failing' => 'Non raggiungibile',
        'badge_inactive' => 'Inattivo',
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'no_org_context' => 'Configurato (nessuna organizzazione nel contesto).',
        'no_connection' => 'Nessuna connessione Microsoft 365 stabilita.',
        'inactive' => 'La connessione Microsoft 365 è disconnessa o disattivata.',
        'ok' => 'Connesso — elenco calendari disponibile.',
        'failing' => 'Microsoft Graph non raggiungibile o accesso negato.',
        'error' => 'Errore Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Collega a Microsoft 365',
        'publish' => 'Pubblica ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'calendar' => [
        'heading' => 'Calendario di destinazione',
        'help' => 'In quale calendario dell\'account collegato viene pubblicato. Senza selezione viene usato il calendario predefinito.',
        'target' => 'Calendario',
        'default' => 'Calendario predefinito',
    ],

    'flash' => [
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'Il flusso OAuth è scaduto o non è valido. Riprovare.',
        'oauth_denied' => 'La connessione è stata rifiutata o annullata.',
        'oauth_failed' => 'Lo scambio dei token non è riuscito (:class).',
        'connected' => 'Account Microsoft 365 collegato.',
        'disconnected' => 'Connessione Microsoft 365 disconnessa. Gli appuntamenti già pubblicati restano nel sistema esterno.',
        'no_connection' => 'Nessuna connessione Microsoft 365 attiva.',
        'calendar_saved' => 'Calendario di destinazione salvato.',
        'calendar_invalid' => 'Il calendario selezionato non è stato trovato.',
        'publish_done' => 'Pubblicazione avviata.',
    ],
];
