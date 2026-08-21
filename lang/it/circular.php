<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Circolari',
    'subtitle' => 'Comunicazioni commerciali a un gruppo filtrato di clienti',
    'empty' => 'Nessuna circolare creata finora.',
    'empty_recipients' => 'Nessun destinatario registrato.',
    'created' => 'Circolare creata.',
    'sent' => 'Circolare inviata.',
    'already_sent' => 'Questa circolare è già stata inviata.',
    'no_recipients' => 'Il filtro selezionato non corrisponde ad alcun cliente.',
    'mandatory_short' => 'Comunicazione obbligatoria',
    'portal_short' => 'Visibile nel portale',
    'no_email' => 'nessun indirizzo e-mail',
    'confirm_send' => 'Inviare ora la circolare a :count destinatari?',
    'body_hint' => 'Segnaposto: :firma, :kunde, :ansprechpartner',
    'mandatory_hint' => 'Le comunicazioni obbligatorie raggiungono anche i clienti che hanno rifiutato gli invii collettivi — solo per informazioni previste dalla legge.',
    'portal_hint' => 'La comunicazione compare anche nel portale clienti.',

    'audience' => [
        'heading' => 'Destinatari (:count)',
    ],

    'approved' => 'Circolare approvata.',
    'approved_by' => 'Approvata da :name',
    'approval_pending' => 'Approvazione in sospeso',

    'error' => [
        'approval_missing' => 'L’invio richiede l’approvazione di una seconda persona.',
        'approval_self' => 'Chi ha creato la circolare non può approvarla da solo.',
    ],

    'action' => [
        'approve' => 'Approva',
        'create' => 'Crea circolare',
        'save_draft' => 'Salva come bozza',
        'send' => 'Invia',
        'show' => 'Visualizza',
    ],

    'column' => [
        'subject' => 'Oggetto',
        'status' => 'Stato',
        'recipients' => 'Destinatari',
        'skipped' => 'Non raggiunti',
        'sent_at' => 'Inviata il',
        'customer' => 'Cliente',
        'email' => 'E-mail',
    ],

    'field' => [
        'body' => 'Testo',
        'is_mandatory' => 'Comunicazione obbligatoria',
        'portal_notice' => 'Mostra nel portale clienti',
    ],

    'filter' => [
        'search' => 'Ricerca',
        'city' => 'Città',
        'zip_prefix' => 'Il CAP inizia con',
        'zip_hint' => 'ad es. 30 per la zona di Hannover',
        'with_active_projects' => 'solo clienti con un progetto attivo',
    ],

    'status' => [
        'draft' => 'Bozza',
        'sending' => 'invio in corso',
        'sent' => 'inviata',
    ],

    'recipient_status' => [
        'pending' => 'in sospeso',
        'sent' => 'consegnata',
        'skipped' => 'saltato',
        'failed' => 'non riuscito',
    ],

    'reason' => [
        'no_email' => 'nessun indirizzo e-mail registrato',
    ],
];
