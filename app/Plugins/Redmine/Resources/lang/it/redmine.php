<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : redmine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa i ticket di progetti Redmine selezionati come attività in progetti WorkDiary e, se desiderato, le registrazioni di tempo come tempi registrati, in sola lettura: titolo, descrizione, stato tramite «chiuso» e scadenza. Conflitti e casi da verificare finiscono nella posta in arrivo delle integrazioni.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Apri in Redmine',
    'health' => [
        'not_configured' => 'Redmine non è configurato (manca l’URL di base o la chiave API).',
        'not_connected' => 'Connessione a Redmine non ancora verificata.',
    ],
    'settings' => [
        'base_url' => 'URL di base',
        'base_url_help' => 'Indirizzo dell’istanza Redmine, ad es. https://redmine.example.com.',
        'api_key' => 'Chiave API',
        'api_key_help' => 'In Redmine in Il mio account → Chiave di accesso API; l’API REST deve essere attivata nell’amministrazione. L’account necessita nei progetti di un ruolo che possa vedere ticket e tempo impiegato, con visibilità «tutti».',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un URL di base con indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
        'default_billable' => 'Importare i tempi come fatturabili',
        'default_billable_help' => 'Se disattivato, i tempi importati non sono mai fatturabili; se attivato, vale l’impostazione del progetto di destinazione.',
        'time_window_days' => 'Finestra temporale (giorni)',
        'time_window_days_help' => 'Quanti giorni indietro ogni esecuzione legge le registrazioni di tempo. Quelle più vecchie restano invariate.',
    ],
    'time' => [
        'fallback' => 'Registrazione di tempo Redmine',
    ],
    'flash' => [
        'times_enabled' => 'Importazione dei tempi attivata per questa associazione.',
        'times_disabled' => 'Importazione dei tempi disattivata per questa associazione. I tempi già importati vengono conservati.',
        'user_invalid' => 'Scelga un utente della Sua organizzazione.',
        'user_mapped' => 'Utente associato. La prossima sincronizzazione registra i tempi in attesa.',
        'user_unmapped' => 'Associazione rimossa. I tempi già registrati vengono conservati.',
    ],
    'times' => [
        'title' => 'Registrazioni di tempo',
        'hint' => 'Per ogni associazione si possono importare le registrazioni di tempo Redmine degli ultimi :days giorni. Redmine conosce solo giorno e ore, quindi vengono registrate come durata senza orario nel progetto di destinazione; l’attività è quella del ticket collegato.',
        'col' => [
            'project' => 'Progetto Redmine',
            'last_run' => 'Ultima importazione dei tempi',
            'import' => 'Importare i tempi',
        ],
        'counters' => ':created nuove · :updated modificate · :unresolved_users in attesa di utente · :removed rimosse',
        'enabled' => 'Attivo',
        'disabled' => 'Disattivo',
        'empty' => 'Nessuna associazione di progetto',
    ],
    'users' => [
        'title' => 'Associazione utenti',
        'hint' => 'Le registrazioni di tempo vengono registrate solo per utenti Redmine associati, mai in silenzio per qualcun altro. La prossima sincronizzazione registra quelle in attesa dopo l’associazione.',
        'waiting' => ':name (:count registrazioni in attesa)',
        'map' => 'Associare',
        'none_pending' => 'Nessuna registrazione di tempo attende un’associazione utente',
        'mapped' => 'Associati',
        'mapping' => 'Utente Redmine #:redmine → :user',
        'unmap' => 'Rimuovere l’associazione',
        'confirm_unmap' => 'Rimuovere l’associazione? I tempi già registrati vengono conservati, le nuove registrazioni tornano in attesa.',
    ],
];
