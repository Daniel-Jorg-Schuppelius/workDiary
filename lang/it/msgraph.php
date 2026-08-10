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

    // Presenza Teams nella pagina delle presenze (Feature 102, F).
    'presence' => [
        'heading' => 'Team (stato Teams)',
        'state' => [
            'Available' => 'Disponibile',
            'AvailableIdle' => 'Disponibile (inattivo)',
            'Busy' => 'Occupato',
            'BusyIdle' => 'Occupato (inattivo)',
            'DoNotDisturb' => 'Non disturbare',
            'Away' => 'Assente',
            'BeRightBack' => 'Torno subito',
            'Offline' => 'Offline',
            'PresenceUnknown' => 'Sconosciuto',
        ],
    ],
    // Free/busy nel dialogo evento (Feature 102, C2).
    'availability' => [
        'check' => 'Verifica disponibilità (Microsoft 365)',
        'hint' => 'Libero/occupato dei partecipanti selezionati nella finestra oraria — senza dettagli degli appuntamenti.',
        'missing_input' => 'Selezionare inizio, fine e almeno un partecipante.',
        'no_connection' => 'Nessuna connessione calendario Microsoft 365 attiva.',
        'failed' => 'Richiesta di disponibilità non riuscita.',
        'free' => 'libero',
        'busy' => 'occupato',
        'unknown' => 'sconosciuto',
    ],
    // Registrazione app per organizzazione (Feature 102 variante B).
    'settings' => [
        'client_id' => 'ID client (registrazione app propria)',
        'client_id_help' => 'Vuoto = l’app dell’installazione. Un’app Entra propria deve registrare le stesse URI di reindirizzamento.',
        'client_secret' => 'Segreto client',
        'client_secret_help' => 'Salvato cifrato; lasciare vuoto per mantenere il valore memorizzato.',
        'tenant' => 'Tenant (ID directory)',
        'tenant_help' => 'GUID del tenant Entra; vuoto = valore dell’app di istanza (predefinito «common»).',
        'tenant_invalid' => 'Il tenant deve essere un GUID di directory (oppure common/organizations/consumers).',
    ],
    'health' => [
        'badge_ok' => 'Connesso',
        'badge_failing' => 'Non raggiungibile',
        'badge_inactive' => 'Inattivo',
        'not_configured' => 'Microsoft 365 non è configurato (MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'no_org_context' => 'Configurato (nessuna organizzazione nel contesto).',
        'no_connection' => 'Nessuna connessione Microsoft 365 stabilita.',
        'inactive' => 'La connessione Microsoft 365 è disconnessa o disattivata.',
        'side_connections' => 'Le connessioni secondarie Microsoft 365 richiedono attenzione (:intake ricezione documenti, :backup backup, :mail mail — ripetere l’accesso o verificare gli scope).',
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
        'teams_meetings' => 'Creare i nuovi eventi come riunioni Teams (link di partecipazione)',
        'teams_meetings_hint' => 'Riguarda solo gli eventi pubblicati ex novo — Graph non può riportare «offline» un evento esistente.',
        'two_way' => 'Bidirezionale: importare le modifiche esterne come proposte',
        'two_way_hint' => 'Importazione delta del calendario di destinazione — nuovi eventi esterni, modifiche esterne e cancellazioni diventano casi della inbox delle integrazioni (mai creazione cieca).',
    ],

    // App Entra & autorizzazione a livello di tenant (admin consent v2).
    'entra' => [
        'heading' => 'App Entra & autorizzazione a livello di tenant',
        'intro' => 'Gli utenti collegano i propri servizi Microsoft 365 tramite l\'accesso Microsoft (OAuth2, solo permessi delegati). Se un criterio del tenant Microsoft impedisce agli utenti di dare il consenso, un amministratore Entra può concedere qui i permessi una sola volta per l\'intera organizzazione.',
        'consent' => 'Concedi per l\'organizzazione (admin consent)',
        'consent_hint' => 'Apre l\'accesso Microsoft; è richiesto un ruolo di amministratore Entra nel tenant di destinazione. Il consenso copre calendario, invio di e-mail, contatti, attività e ricezione documenti.',
        'redirects' => 'URI di reindirizzamento per una registrazione app propria',
        'redirects_hint' => 'Un\'app Entra del cliente (impostazioni del plugin) deve registrare esattamente questi URI come reindirizzamenti di tipo «Web»:',
        'redirect_calendar' => 'Calendario',
        'redirect_mail' => 'Invio e-mail',
        'redirect_contacts' => 'Contatti',
        'redirect_tasks' => 'Attività (To Do)',
        'redirect_intake' => 'Ricezione documenti',
        'redirect_adminconsent' => 'Admin consent',
        'redirect_backup' => 'Destinazione di backup (solo app dell\'istanza)',
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
        'admin_consent_granted' => 'Autorizzazione a livello di tenant concessa — gli utenti possono ora collegarsi senza richiesta di consenso individuale.',
        'admin_consent_failed' => 'Admin consent non concesso (:error).',
    ],
];
