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
    'plugin' => [
        'description' => 'Invia e riceve documenti tramite un access point Peppol certificato. WorkDiary non gestisce un access point proprio: qui si configurano endpoint e nomi di campo del fornitore.',
    ],
    'settings' => [
        'base_url' => 'URL di base del fornitore',
        'base_url_help' => 'Radice dell’API del fornitore, ad es. https://api.example-ap.eu/v1 — senza barra finale.',
        'api_key' => 'Chiave di accesso',
        'api_key_help' => 'Memorizzata cifrata e oscurata nei log.',
        'auth_header' => 'Intestazione di autenticazione',
        'auth_header_help' => 'Intestazione che trasporta la chiave (predefinito: Authorization).',
        'auth_scheme' => 'Prefisso di autenticazione',
        'auth_scheme_help' => 'Prefisso come Bearer. Lasciare vuoto se il fornitore attende la sola chiave.',
        'send_path' => 'Endpoint di invio (percorso)',
        'receive_path' => 'Endpoint di ricezione (percorso)',
        'ack_path' => 'Endpoint di conferma (percorso)',
        'ack_path_help' => 'Il segnaposto {messageId} viene sostituito con l’identificativo del messaggio; senza di esso l’identificativo viaggia nel corpo.',
        'health_path' => 'Endpoint di stato (percorso)',
        'payload_field' => 'Nome del campo della busta',
        'payload_field_help' => 'Campo JSON che contiene la busta SBDH. Lasciare vuoto se il fornitore attende XML grezzo.',
        'message_id_field' => 'Nome del campo dell’identificativo messaggio',
        'status_field' => 'Nome del campo dello stato di trasporto',
        'items_field' => 'Nome del campo dell’elenco in ingresso',
        'sender_participant_id' => 'Identificativo partecipante Peppol proprio',
        'sender_participant_id_help' => 'Forma <ICD>:<identificativo>, ad es. 9930:DE123456789. Deve essere registrato presso il fornitore per questa organizzazione.',
        'sender_country' => 'Paese del mittente',
        'sender_country_help' => 'Due lettere (ISO 3166-1), scritte nella busta come COUNTRY_C1.',
        'sml_zone' => 'Zona SML',
        'sml_zone_help' => 'Produzione o test. Le zone NAPTR sono la procedura attuale; le zone CNAME restano solo dalla migrazione.',
        'lookup_ttl_hours' => 'Validità della verifica del partecipante (ore)',
        'lookup_ttl_hours_help' => 'Per quanto tempo vale un risultato SMP prima di risolvere di nuovo. 0 = risolvere ogni volta.',
    ],
    'health' => [
        'not_configured' => 'Nessuna credenziale del fornitore di access point memorizzata.',
        'sender_invalid' => 'L’identificativo partecipante Peppol proprio manca o non ha la forma <ICD>:<identificativo>.',
        'unreachable' => 'Il fornitore di access point non risponde o rifiuta la chiave di accesso.',
        'ok' => 'Collegato a :url.',
    ],
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
