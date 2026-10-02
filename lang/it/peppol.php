<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'field' => [
        'participant_id' => 'Identificativo partecipante Peppol',
        'participant_id_hint' => 'Forma <ICD>:<identificativo>, ad es. 9930:DE123456789 (partita IVA) o 0204:991-12345-67 (Leitweg-ID). Vuoto = nessun invio Peppol a questo cliente.',
    ],
    'action' => [
        'send' => 'Invia via Peppol',
        'send_title' => 'Consegnare la fattura tramite il fornitore di access point — la prova di consegna è la ricevuta di trasporto.',
        'check' => 'Verifica registrazione Peppol',
    ],
    'validator' => [
        'scope' => 'È stato verificato un sottoinsieme delle regole Peppol BIS Billing 3.0 (:scenario) — espressamente non una dichiarazione di piena conformità. La verifica Schematron completa spetta al validatore KoSIT e all’access point.',
    ],
    'error' => [
        'not_configured' => 'Per questa organizzazione non è configurato alcun access point Peppol (plugin «Peppol Access Point»).',
        'sender_invalid' => 'L’identificativo partecipante Peppol proprio manca o non è valido — si trova nelle impostazioni del plugin.',
        'no_participant' => 'Per :customer non è memorizzato alcun identificativo partecipante Peppol.',
        'invalid_participant' => 'L’identificativo partecipante Peppol di :customer non è valido: :value',
        'not_registered' => 'Il destinatario :participant non è registrato in Peppol.',
        'unsupported_document' => 'Il destinatario :participant non accetta il formato :document tramite Peppol.',
        'lookup_failed' => 'La risoluzione del partecipante Peppol non è riuscita: :message',
        'validation' => 'La fattura non soddisfa le regole Peppol verificate: :messages',
        'transport' => 'L’access point non ha accettato l’invio: :message',
        'not_issued' => 'Solo le fatture emesse possono essere consegnate tramite Peppol.',
        'external_billing' => 'La fatturazione è di un sistema esterno — WorkDiary non consegna fatture per questo cliente.',
        'proforma' => 'Le fatture pro forma non sono fatture elettroniche e non passano per Peppol.',
    ],
    'status' => [
        'registered' => 'Registrato in Peppol (SMP :smp, :count formati di documento).',
        'not_registered' => 'Non registrato in Peppol.',
        'checked_at' => 'Ultima verifica: :at',
        'never_checked' => 'Non ancora verificato.',
    ],
    'flash' => [
        'sent' => 'Fattura consegnata a :participant (messaggio :message, stato di trasporto :status).',
        'checked' => 'Verifica Peppol per :customer: :result',
    ],
    'inbound' => [
        'summary' => 'Ricezione Peppol: :fetched recuperati, :imported acquisiti, :duplicates duplicati, :unreadable illeggibili.',
        'document_name' => 'peppol-:id.xml',
    ],
];
