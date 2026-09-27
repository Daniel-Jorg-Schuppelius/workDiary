<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : supplier_questionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lieferanten-Selbstauskunft (MVP-937).
return [
    'title' => 'Autovalutazione dei fornitori',
    'subtitle' => 'Questionari per i fornitori (ad es. sostenibilità, filiera, qualità) con link monouso, verifica e validità.',
    'card' => 'Autovalutazione',
    'questionnaires' => 'Questionari',
    'recent' => 'Richieste recenti',
    'create' => 'Crea questionario',
    'edit' => 'Modifica questionario',
    'save' => 'Salva',
    'send' => 'Richiedi autovalutazione',
    'send_hint' => 'Il fornitore riceve via e-mail un link valido :days giorni. Una richiesta aperta dello stesso questionario viene ritirata.',
    'open' => 'Apri',
    'none' => 'Nessuna autovalutazione richiesta finora.',
    'empty' => 'Nessun questionario finora.',
    'no_requests' => 'Nessuna richiesta finora.',
    'inactive' => 'inattivo',
    'valid_until' => 'valido fino al :date',
    'submitted_at' => 'inviato il :date',
    'waiting' => 'In attesa di risposta da :email (link valido fino al :date).',
    'accept' => 'Accetta',
    'reject' => 'Restituisci per correzione',
    'public_title' => 'Autovalutazione per :org',
    'public_submit' => 'Invia risposte',
    'public_thanks' => 'Grazie, le sue risposte sono state ricevute.',
    'public_rework' => 'Completi le sue risposte: :note',
    'field' => [
        'name' => 'Questionario',
        'description' => 'Nota per il fornitore',
        'questions' => 'Domande',
        'validity_months' => 'Validità (mesi)',
        'is_active' => 'Attivo',
        'requests' => 'Richieste',
        'recipient_email' => 'E-mail del fornitore',
        'sent_at' => 'Richiesto',
        'status' => 'Stato',
        'valid_until' => 'Valido fino al',
        'note' => 'Nota',
    ],
    'status' => [
        'sent' => 'Richiesto',
        'submitted' => 'Inviato',
        'accepted' => 'Accettato',
        'rejected' => 'Restituito per correzione',
        'withdrawn' => 'Ritirato',
    ],
    'flash' => [
        'saved' => 'Questionario salvato.',
        'sent' => 'Richiesta inviata a :email.',
        'reviewed' => 'Verifica salvata.',
    ],
    'error' => [
        'transition' => 'L\'autovalutazione non può passare da «:from» a «:to».',
    ],
    'mail' => [
        'subject' => 'Autovalutazione per :org',
        'body' => "Buongiorno,\n\n:org le chiede di compilare l'autovalutazione «:name». Compili il questionario entro il :until:\n:url\n\nGrazie.",
    ],
];
