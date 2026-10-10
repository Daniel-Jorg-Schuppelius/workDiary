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
    ],
];
