<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal: Passwort vergessen, Zugang zurücksetzen, Rechnungsdokument (MVP-1096/1097).
return [
    'password' => [
        'mail_subject' => 'Reimpostare la password del portale clienti di :org',
        'mail_heading' => 'Reimpostare la password',
        'mail_intro' => 'È stata richiesta la reimpostazione della password per il Suo accesso al portale clienti di :org. Con il link seguente può impostare una nuova password.',
        'mail_validity' => 'Il link è utilizzabile una sola volta ed è valido per :minutes minuti.',
        'mail_ignore' => 'Se non ha richiesto Lei la reimpostazione, ignori questa e-mail — la Sua password attuale resta valida.',
        'sessions_hint' => 'Con la nuova password tutte le sessioni aperte di questo accesso vengono chiuse. Un secondo fattore già configurato resta attivo.',
    ],
    'reset' => [
        'action' => 'Reimpostare l’accesso',
        'confirm_title' => 'Reimpostare l’accesso al portale',
        'confirm_message' => 'La password attuale non è più valida da subito, tutte le sessioni vengono chiuse e :email riceve un nuovo invito. I metodi a due fattori configurati restano attivi.',
        'confirm_label' => 'Reimpostare',
        'flash' => 'Accesso reimpostato — nuovo invito inviato a :email.',
        'mail_heading' => 'Il Suo accesso al portale clienti è stato reimpostato',
        'mail_intro' => ':org ha reimpostato il Suo accesso al portale clienti. La Sua password precedente non è più valida. Con il link seguente può impostare una nuova password e quindi accedere.',
    ],
    'second_factor' => [
        'action' => 'Reimposta il secondo fattore',
        'confirm_title' => 'Reimposta il secondo fattore',
        'confirm_message' => 'Tutti i metodi a due fattori di :email (app, passkey, codici di recupero) vengono rimossi e tutte le sessioni terminate. Usi questa funzione solo se il cliente non dispone più di alcun fattore e Lei ne ha verificato l’identità.',
        'confirm_label' => 'Reimposta',
        'flash' => 'Secondo fattore di :email reimpostato — il cliente è stato informato.',
        'mail_subject' => 'Accesso a due fattori al portale clienti di :org reimpostato',
        'mail_heading' => 'Il Suo secondo fattore è stato reimpostato',
        'mail_intro' => ':org ha rimosso i metodi a due fattori del Suo accesso al portale clienti. Acceda con la Sua password; se :org richiede l’accesso a due fattori, lo configurerà nuovamente in quel momento.',
        'mail_unexpected' => 'Se non ha richiesto Lei questa operazione, si rivolga subito al Suo referente presso :org.',
    ],
    'invoices' => [
        'pdf_label' => 'Scaricare la fattura :number in PDF',
        'document_unavailable' => 'Il documento non è ancora disponibile nel programma contabile. Riprovi più tardi.',
    ],

    // Anteprima come cliente (fase 153, MVP-1229–1231).
    'preview' => [
        'action' => 'Visualizza portale',
        'action_as' => 'Visualizza come questa persona',
        'no_account' => 'Anteprima disponibile dopo la creazione di un accesso',
        'running' => 'Anteprima in corso come :name fino alle :until.',
        'stop' => 'Termina anteprima',
        'banner' => 'Anteprima: sta visualizzando il portale come :name (:customer). Le azioni sono bloccate.',
        'banner_until' => 'Termina alle :time.',
        'title_prefix' => 'Anteprima',
        'blocked' => 'Durante l’anteprima le azioni sono bloccate.',
        'blocked_page' => 'Questa pagina non è disponibile durante l’anteprima.',
        'nested_support' => 'Durante una sessione di supporto non è possibile un’anteprima.',
        'deactivated' => 'Per un accesso disattivato non esiste un’anteprima.',
        'ended_manual' => 'Anteprima terminata.',
        'ended_replaced' => 'L’anteprima precedente è stata terminata.',
        'ended_expired' => 'L’anteprima è scaduta dopo :minutes minuti.',
        'ended_actor_changed' => 'L’anteprima è stata terminata perché l’accesso è cambiato.',
        'ended_permission_lost' => 'L’anteprima è stata terminata perché manca il permesso.',
        'ended_organization_changed' => 'L’anteprima è stata terminata perché l’organizzazione è cambiata.',
        'ended_account_changed' => 'L’anteprima è stata terminata perché l’account del portale è cambiato.',
        'ended_account_deactivated' => 'L’anteprima è stata terminata perché l’account del portale non è più disponibile.',
    ],
    // Accesso con password iniziale (MVP-1232).
    'direct' => [
        'action' => 'Crea accesso',
        'title' => 'Crea accesso al portale con password iniziale',
        'intro' => 'Riceverà una password iniziale casuale che viene mostrata una sola volta. La consegni di persona o per telefono, mai per e-mail. Al primo accesso la persona imposta una propria password.',
        'submit' => 'Crea accesso',
        'flash' => 'Accesso per :email creato.',
        'password_heading' => 'Password iniziale per :email',
        'password_hint' => 'Mostrata una sola volta. Da consegnare di persona o per telefono, mai per e-mail. Al primo accesso deve essere impostata una password personale.',
        'copy' => 'Copia',
        'copied' => 'Copiato',
        'state_provisional' => 'Password iniziale in sospeso',
        'reissue_action' => 'Genera password iniziale',
        'reissue_confirm_title' => 'Genera nuova password iniziale',
        'reissue_confirm_message' => 'La password attuale di :email non è più valida da subito, un invito aperto decade e tutte le sessioni vengono terminate. La nuova password iniziale viene mostrata una sola volta; al prossimo accesso deve essere impostata una password personale. I metodi a due fattori restano.',
        'reissue_confirm_label' => 'Genera',
        'reissue_flash' => 'Nuova password iniziale per :email generata.',
        'reauth_hint' => 'La creazione di un accesso con password iniziale richiede un login recente. Confermi prima la Sua password e riapra poi la finestra di dialogo.',
        'reauth_action' => 'Conferma password',
    ],
    // Cambia password nel portale (MVP-1233).
    'password_change' => [
        'title' => 'Cambia password',
        'forced_hint' => 'Ha ricevuto una password iniziale. Imposti ora la Sua password personale prima di usare il portale.',
        'intro' => 'Inserisca la password attuale e una nuova password. Gli accessi di questo account su altri dispositivi verranno terminati.',
        'current' => 'Password attuale',
        'new' => 'Nuova password',
        'confirm' => 'Ripeti la nuova password',
        'submit' => 'Salva password',
        'flash' => 'La Sua password è stata cambiata.',
        'profile_heading' => 'Password',
        'profile_intro' => 'Cambi la Sua password se sospetta che qualcuno la conosca o se ha ricevuto una password iniziale.',
        'profile_link' => 'Cambia password',
    ],
];
