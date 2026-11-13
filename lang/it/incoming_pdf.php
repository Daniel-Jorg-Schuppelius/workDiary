<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming_pdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// PDF cumulativo e GiroCode nella ricezione fatture (Feature 208, MVP-1662/1663).
return [
    'split' => [
        'title' => 'Dividere il PDF cumulativo',
        'card_title' => 'PDF cumulativo',
        'gate' => 'PDF cumulativo: prima dividerlo o confermarlo come un’unica fattura.',
        'part_description' => 'Parte di «:title», pagine :from–:to',
        'document_title' => 'PDF cumulativo :file (:count fatture)',
        'document_description' => 'Originale invariato, diviso in :count fatture ricevute.',
        'state' => [
            'pending' => 'Le pagine vengono verificate per più fatture. Fino ad allora trasferimento e pagamento restano in attesa.',
            'proposed' => 'Il PDF contiene probabilmente :count fatture. Trasferimento e pagamento attendono la sua decisione.',
            'single' => 'Le :pages pagine appartengono probabilmente a un’unica fattura. Se invece ce ne sono più di una, le divida a mano.',
            'dismissed' => 'Confermato come un’unica fattura.',
            'splitting' => 'La divisione è in corso in background.',
            'too_large' => 'Più di :max pagine — nessuna verifica di più fatture.',
            'failed' => 'La verifica di più fatture non è riuscita; la ricezione vale come un’unica fattura.',
        ],
        'action' => [
            'reset' => 'Reimpostare la divisione',
            'reset_confirm' => 'La divisione dura più del solito. Reimpostarla e riprovare?',
            'open' => 'Verificare i confini',
            'split' => 'Dividere',
            'dismiss' => 'È un’unica fattura',
            'dismiss_confirm' => 'Mantenere la ricezione come un’unica fattura? Trasferimento e pagamento proseguono in seguito.',
        ],
        'dialog' => [
            'intro' => 'Segni le pagine in cui inizia una nuova fattura. Ogni fattura diventa una ricezione a sé con il proprio PDF parziale; l’originale resta invariato.',
            'page' => 'Pagina :page',
            'starts_here' => 'Qui inizia una nuova fattura',
            'first_page' => 'Prima fattura',
            'no_image' => 'Nessuna anteprima',
            'images_missing' => 'Mancano le immagini di anteprima — le pagine si possono comunque segnare.',
            'signals' => 'Indizi',
        ],
        'signal' => [
            'page_one' => '«Pagina 1 di …»',
            'continued' => 'pagina successiva',
            'new_number' => 'nuovo numero di fattura',
            'same_number' => 'stesso numero di fattura',
            'new_sender' => 'nuovo mittente',
            'after_total' => 'dopo un blocco dei totali',
            'new_letterhead' => 'altra intestazione',
        ],
        'blocked' => [
            'split' => 'La ricezione è stata divisa come PDF cumulativo — si elaborano le sue parti.',
            'part' => 'Questa ricezione è già parte di un PDF cumulativo diviso.',
            'no_bundle' => 'Per questa ricezione non c’è una verifica del PDF cumulativo aperta.',
            'pending' => 'Le pagine sono ancora in verifica.',
            'running' => 'La divisione è già in corso.',
            'too_large' => 'Il PDF è troppo grande per essere diviso.',
            'status' => 'Si possono dividere solo ricezioni aperte (ricevute o con richiesta di chiarimento).',
            'transferred' => 'La ricezione è già stata trasferita alla contabilità — dividerla lì.',
            'payment' => 'La ricezione è in una distinta di pagamento.',
            'linked' => 'La ricezione ha trattenute o collegamenti (cespite, partita aperta, regresso, nota di credito).',
            'hold' => 'Per la ricezione vale un vincolo di conservazione.',
        ],
        'error' => [
            'too_many_parts' => 'Al massimo :max fatture per PDF cumulativo.',
            'parts_too_large' => 'I PDF parziali sarebbero troppo grandi nel complesso — divida il file prima del caricamento.',
            'stalled' => 'La divisione non è terminata ed è stata reimpostata.',
            'invalid_page' => 'I numeri di pagina non corrispondono al documento.',
            'single' => 'Segni almeno un’altra pagina iniziale — altrimenti resta un’unica fattura.',
            'original_missing' => 'L’originale manca nella gestione documentale.',
            'original_changed' => 'L’originale archiviato non corrisponde più all’hash della ricezione — nessuna divisione.',
            'tool_missing' => 'Manca uno strumento PDF per estrarre le pagine (mutool, pdftk o poppler).',
            'extract_failed' => 'Non è stato possibile estrarre le pagine :from–:to.',
            'nothing_created' => 'Tutte le parti esistono già come ricezioni — nulla è stato diviso.',
            'failed' => 'La divisione non è riuscita.',
            'original_protected' => 'L’originale di un PDF cumulativo resta finché esiste una delle sue parti.',
        ],
        'flash' => [
            'reset' => 'Divisione reimpostata.',
            'done' => 'PDF cumulativo diviso in :count fatture.',
            'queued' => 'Divisione in :count fatture avviata — le ricezioni compariranno a breve nell’elenco.',
            'dismissed' => 'Mantenuto come un’unica fattura.',
        ],
        'parts' => [
            'title' => 'Parti di questo PDF cumulativo',
            'intro' => 'L’originale resta invariato; ogni parte è una ricezione a sé con il proprio PDF parziale.',
            'pages' => 'Pagine :from–:to',
            'protected' => 'L’originale non si può eliminare finché esistono parti.',
        ],
        'part_of' => 'Parte di un PDF cumulativo, pagine :from–:to.',
        'open_original' => 'Aprire l’originale',
    ],
    'girocode' => [
        'title' => 'GiroCode',
        'pending' => 'Il GiroCode viene letto in background.',
        'status' => [
            'found' => 'GiroCode letto (pagina :page).',
            'none' => 'Nessun GiroCode trovato.',
            'unreadable' => 'GiroCode non leggibile.',
            'invalid' => 'Codice QR con identificativo GiroCode, ma contenuto non valido.',
        ],
        'reason' => [
            'page_size' => 'Le dimensioni della pagina non sono leggibili o sono troppo grandi per la rasterizzazione.',
            'changed' => 'L’originale archiviato non corrisponde più alla ricezione.',
            'tool_missing' => 'Sul server manca lo strumento di lettura (zbarimg).',
            'too_large' => 'Il file è troppo grande per essere letto.',
            'scan_failed' => 'Si è verificato un errore durante la lettura.',
        ],
        'field' => [
            'name' => 'Beneficiario',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'amount' => 'Importo',
            'reference' => 'Causale',
        ],
        'iban_applied' => 'L’IBAN del GiroCode è stato ripreso perché il documento non ne indicava nessuno.',
        'finding' => [
            'new_bank_details' => 'L’IBAN proviene solo dal GiroCode; per il fornitore non è registrata alcuna coordinata bancaria con cui confrontarlo.',
            'iban_differs_invoice' => 'L’IBAN del GiroCode differisce dall’IBAN della fattura.',
            'iban_differs_supplier' => 'L’IBAN del GiroCode non appartiene ad alcuna coordinata bancaria registrata del fornitore.',
            'multiple_codes' => 'Il documento riporta più GiroCode con IBAN diversi.',
            'iban_invalid' => 'Le cifre di controllo dell’IBAN del GiroCode non sono corrette — l’IBAN non è stato ripreso.',
            'amount_differs' => 'L’importo del GiroCode differisce dall’importo lordo.',
            'name_differs' => 'Il nome del beneficiario nel GiroCode non corrisponde al fornitore.',
            'unreadable' => 'GiroCode non leggibile.',
            'invalid' => 'GiroCode non valido.',
        ],
        'blocking' => 'Coordinate bancarie diverse — un noto schema di frode. Bloccato nella distinta di pagamento finché una persona con permesso di autorizzazione non conferma la differenza.',
        'confirmed' => 'Differenza confermata il :date.',
    ],
];
