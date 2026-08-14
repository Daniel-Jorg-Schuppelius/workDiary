<?php

return [
    'action' => 'Converti file fattura in fattura elettronica', 'title' => 'Importa file fattura', 'eyebrow' => 'Assistente fattura elettronica', 'submit' => 'Leggi fattura',
    'intro' => 'Le fatture PDF, Word ed Excel vengono lette senza eseguire macro. I valori riconosciuti devono essere verificati prima dell’emissione.',
    'group_source' => 'Documento sorgente', 'group_target' => 'Destinazione e output', 'group_invoice' => 'Dati fattura', 'group_einvoice' => 'Fattura elettronica',
    'file' => 'File fattura', 'file_hint' => 'PDF, DOCX, DOC, XLSX o XLS fino a 20 MB; per le scansioni PDF viene usato l’OCR, se disponibile.', 'delivery_format' => 'Formato di output preferito',
    'review_hint' => 'L’originale resta invariato nel DMS. I dati riconosciuti automaticamente sono suggerimenti, non un’approvazione.',
    'format' => ['pdf' => 'PDF', 'xrechnung' => 'XRechnung (XML)', 'zugferd' => 'ZUGFeRD (PDF ibrido)', 'pdf_xrechnung' => 'PDF e XRechnung (XML)'],
    'default_line' => 'Prestazioni secondo la fattura originale :number', 'source_title' => 'File originale della fattura :number', 'source_description' => 'Documento sorgente invariato dell’importazione fattura.',
    'success' => 'File letto e creato come bozza di fattura. Verificare dati e righe.', 'options_title' => 'Dati fattura ed e-fattura', 'options_action' => 'Dati e-fattura', 'options_saved' => 'Dati fattura salvati.',
    'invoice_number' => 'Numero fattura', 'currency' => 'Valuta', 'issue_date' => 'Data fattura', 'due_date' => 'Scadenza', 'buyer_reference' => 'Riferimento acquirente / ID instradamento',
    'buyer_reference_hint' => 'Sostituisce per questa fattura il riferimento presente nel cliente.', 'buyer_reference_create_hint' => 'Facoltativo per fattura; se vuoto si usa il dato del cliente.',
    'imported_notice' => 'Precompilato da un file fattura', 'imported_detail' => 'Punteggio di riconoscimento: :confidence %. Confrontare numero, date, importi, imposta e righe con l’originale.', 'original' => 'File originale',
    'preferred_format' => 'Output preferito:', 'flexibility_hint' => 'PDF, XRechnung e ZUGFeRD restano disponibili separatamente.', 'mail_hint' => 'Selezionare il formato dell’allegato. Le bozze vengono emesse automaticamente all’invio.',
    'error' => ['external_billing' => 'La fatturazione di questo cliente è gestita da un’applicazione esterna. L’importazione locale è bloccata.', 'duplicate' => 'Questo file è già stato importato.', 'no_text' => 'Impossibile leggere dati fattura dal file.', 'unsupported_format' => 'Sono supportati PDF, DOCX, DOC, XLSX e XLS.', 'unreadable' => 'Il file è danneggiato o non può essere letto in sicurezza.', 'proforma' => 'Le fatture proforma possono essere inviate solo in formato PDF.'],
    'warning' => ['missing_number' => 'Il numero fattura non è stato riconosciuto con sicurezza.', 'missing_issued_on' => 'La data fattura non è stata riconosciuta con sicurezza.', 'missing_net' => 'L’importo netto non è stato riconosciuto con sicurezza.', 'totals_mismatch' => 'Gli importi netto, imposta e lordo riconosciuti non coincidono.', 'duplicate_number' => 'Il numero riconosciuto esiste già; è stato usato un numero locale libero.'],
];
