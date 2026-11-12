<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : appointment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Eigene Gruppe: die losen Schlüssel „bestätigt“/„storniert“ gehören der Buchhaltung (acknowledged/reversed).
return [
    'status' => [
        'requested' => 'richiesta',
        'confirmed' => 'confermata',
        'declined' => 'rifiutata',
        'canceled' => 'annullata',
        'proposed' => 'alternativa proposta',
        'superseded' => 'sostituita',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'proposal' => 'Proposta: :date',
        'proposal_accept' => 'Accettare',
        'proposal_decline' => 'Rifiutare',
        'cancel_expired' => 'Termine di annullamento scaduto',
        'cancel_policy' => 'È possibile annullare fino a :hours ore prima dell’inizio dell’appuntamento.',
        'cancel_until' => 'Annullabile fino al :date',
        'order_cancel_reason' => 'Appuntamento annullato dal cliente nel portale clienti.',
        'order_in_progress' => 'Questo appuntamento è già in lavorazione — ci chiami.',
    ],
    'notification' => [
        'canceled_title' => 'Appuntamento annullato da :customer',
        'message' => ':service il :date',
        'requested_title' => 'Richiesta di appuntamento da :customer',
    ],
    'proposal' => [
        'action' => 'Altro orario',
        'title' => 'Proporre un altro appuntamento',
        'start' => 'Appuntamento proposto',
        'note' => 'Nota per il cliente (facoltativa)',
        'submit' => 'Invia proposta',
        'sent' => 'Alternativa proposta: il cliente decide nel portale.',
        'badge' => 'Proposta: :date',
        'in_past' => 'L’appuntamento è nel passato.',
        'unavailable' => 'Non c’è alcuna proposta aperta per questa richiesta.',
        'accepted' => 'Appuntamento accettato: riceverà una conferma.',
        'declined' => 'Proposta rifiutata: la richiesta è annullata.',
    ],
    'reschedule' => [
        'action' => 'Spostare',
        'title' => 'Spostare l’appuntamento',
        'start' => 'Nuovo appuntamento',
        'submit' => 'Spostare',
        'done' => 'Appuntamento spostato: il cliente riceve il nuovo orario.',
        'not_confirmed' => 'Si possono spostare solo appuntamenti confermati.',
    ],
    'mail' => [
        'proposed_subject' => 'Proposta di appuntamento: :service',
        'proposed_title' => 'Le proponiamo un altro appuntamento',
        'proposed_body' => 'Purtroppo l’orario richiesto per :service il :wish non è disponibile. Le proponiamo il :date.',
        'proposed_action' => 'Accettare o rifiutare nel portale clienti',
        'rescheduled_title' => 'Il Suo appuntamento è stato spostato',
    ],
    // Assegnazione e gestione (MVP-1610, MVP-1612).
    'assign' => [
        'action' => 'Assegna',
        'title' => 'Assegna richiesta',
        'submit' => 'Assegna',
        'invitee' => 'Prenotato da :name (:email).',
        'customer_hint' => 'Alla conferma, l\'ordine viene creato per questo cliente.',
        'lead' => 'Lead',
        'lead_hint' => 'Se il lead è già cliente, viene assegnato anche il cliente.',
        'required' => 'Scegliere un cliente o un lead.',
        'done' => 'Richiesta assegnata.',
        'decided' => 'Le richieste già decise non possono più essere assegnate.',
    ],
    'service' => [
        'edit_title' => 'Modifica tipo di servizio',
        'updated' => 'Tipo di servizio «:title» salvato.',
        'site_foreign' => 'Questo tipo di servizio è legato a un oggetto di un altro cliente.',
        'site_hint' => 'Con un oggetto prenota solo il cliente che ne è proprietario; diventa il luogo d\'intervento dell\'ordine.',
    ],
];
