<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : caldav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CalDAV',
    'intro' => 'Gli appuntamenti di WorkDiary vengono pubblicati in un calendario CalDAV esterno (Nextcloud/ownCloud) — on-premise, senza account Microsoft o Google. WorkDiary resta l\'autorità; gli appuntamenti annullati vi scompaiono e le esecuzioni ripetute non creano mai duplicati.',

    'health' => [
        'ok' => 'Connesso',
        'failing' => 'Irraggiungibile',
        'inactive' => 'Inattivo',
    ],

    'action' => [
        'publish' => 'Pubblica ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'connection' => [
        'heading' => 'Connessione',
    ],

    'field' => [
        'name' => 'Etichetta',
        'base_url' => 'URL base DAV',
        'base_url_help' => 'Nextcloud: .../remote.php/dav (senza il percorso del calendario).',
        'username' => 'Nome utente',
        'app_password' => 'Password app',
        'password_keep' => '•••••••• (lascia invariato)',
        'password_help' => 'Nextcloud: Impostazioni → Sicurezza → Password app. Memorizzata cifrata.',
        'calendar_path' => 'Percorso calendario (collection)',
        'calendar_path_help' => 'Relativo all\'URL base, ad es. calendars/team/turni.',
        'active' => 'Attivo',
        'scopes' => 'Contenuti pubblicati',
        'scope_events' => 'Eventi',
        'scope_schedule' => 'Turni e ferie',
        'scopes_help' => 'Quali contenuti vengono pubblicati in questa collezione. Senza selezione: solo eventi.',
    ],

    'flash' => [
        'saved' => 'Connessione CalDAV salvata.',
        'publish_done' => 'Pubblicazione avviata.',
        'disconnected' => 'Connessione CalDAV disconnessa. Gli appuntamenti già pubblicati restano all\'esterno.',
        'no_connection' => 'Nessuna connessione CalDAV attiva.',
        'invalid_url' => 'L\'URL base deve iniziare con http:// o https://.',
        'password_required' => 'Una nuova connessione richiede una password app.',
    ],
];
