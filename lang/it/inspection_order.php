<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_order.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfaufträge an Dienstleister (MVP-938).
return [
    'title' => 'Ordini di verifica',
    'subtitle' => 'Affidare le verifiche in scadenza a un fornitore: offerta, accettazione, risultati per strumento e acquisizione come registrazione.',
    'create' => 'Crea ordine di verifica',
    'send' => 'Invia ordine',
    'open' => 'Apri',
    'empty' => 'Nessun ordine di verifica finora.',
    'no_schedules' => 'Nessuna scadenza di verifica aperta.',
    'due' => 'scadenza :date',
    'accept' => 'Accetta offerta',
    'reject' => 'Rifiuta offerta',
    'take_over' => 'Acquisisci risultati',
    'taken_over' => 'acquisito',
    'cancel' => 'Annulla ordine',
    'confirm_cancel' => 'Annullare l\'ordine? Le scadenze tornano libere.',
    'public_title' => 'Ordine di verifica da :org',
    'public_offer' => 'Presentare un\'offerta',
    'public_submit_offer' => 'Invia offerta',
    'public_report' => 'Comunicare i risultati',
    'public_submit_report' => 'Invia risultati',
    'public_reported' => 'I risultati sono stati inviati. Grazie.',
    'field' => [
        'title' => 'Denominazione',
        'supplier' => 'Fornitore di verifica',
        'recipient_email' => 'E-mail del fornitore',
        'items' => 'Strumenti',
        'status' => 'Stato',
        'offer_amount' => 'Prezzo offerto',
        'offer_planned_on' => 'Data prevista',
        'offer_note' => 'Nota sull\'offerta',
        'asset' => 'Strumento',
        'result' => 'Risultato',
        'performed_on' => 'Verificato il',
        'valid_until' => 'Valido fino al',
        'certificate_no' => 'Numero di certificato',
        'certificate_file' => 'Certificato (PDF)',
        'event' => 'Registrazione di verifica',
    ],
    'status' => [
        'requested' => 'Richiesto',
        'offered' => 'Offerta ricevuta',
        'accepted' => 'Incaricato',
        'reported' => 'Risultati comunicati',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
    ],
    'flash' => [
        'sent' => 'Ordine di verifica inviato a :email.',
        'decided' => 'Decisione salvata.',
        'taken_over' => ':count registrazioni acquisite.',
        'cancelled' => 'Ordine annullato.',
        'offered' => 'Grazie, la sua offerta è stata ricevuta.',
        'reported' => 'Grazie, i risultati sono stati ricevuti.',
    ],
    'error' => [
        'no_schedules' => 'Scelga almeno una scadenza aperta.',
        'nothing_reported' => 'Indichi almeno un risultato.',
        'transition' => 'L\'ordine non può passare da «:from» a «:to».',
    ],
    'mail' => [
        'subject' => 'Ordine di verifica da :org: :title',
        'body' => "Buongiorno,\n\n:org le chiede un'offerta per la verifica «:title» (:count strumenti). Con il link seguente presenta l'offerta e, dopo l'incarico, comunica i risultati:\n:url\n\nIl link è valido fino al :until.",
    ],
];
