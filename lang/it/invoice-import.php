<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoice-import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => 'Convertire un file fattura in fattura elettronica', 'title' => 'Importa file fattura', 'eyebrow' => 'Assistente fattura elettronica', 'submit' => 'Leggi fattura',
    'intro' => 'Le fatture PDF, Word ed Excel vengono lette senza eseguire macro. I valori riconosciuti vanno verificati prima dell\'emissione.',
    'group_source' => 'Documento di origine', 'group_target' => 'Destinazione e output', 'group_invoice' => 'Dati fattura', 'group_einvoice' => 'Fattura elettronica',
    'file' => 'File fattura', 'file_hint' => 'PDF, XML (XRechnung), DOCX, DOC, XLSX o XLS fino a 20 MB. I PDF ZUGFeRD e gli XML XRechnung vengono importati in modo strutturato; per le scansioni PDF viene usato l\'OCR se disponibile.', 'delivery_format' => 'Formato di output preferito',
    'review_hint' => 'L\'originale resta invariato nel DMS. I dati riconosciuti automaticamente sono proposte, non un\'approvazione.',
    'format' => ['pdf' => 'PDF', 'xrechnung' => 'XRechnung (XML)', 'zugferd' => 'ZUGFeRD (PDF ibrido)', 'pdf_xrechnung' => 'PDF e XRechnung (XML)'],
    'default_line' => 'Prestazioni secondo la fattura originale :number', 'source_title' => 'File originale della fattura :number', 'source_description' => 'Documento di origine invariato dell\'importazione fattura.',
    'success' => 'File fattura letto e creato come bozza. Verificare dati e righe della fattura.', 'options_title' => 'Dati fattura e fattura elettronica', 'options_action' => 'Dati e-fattura', 'options_saved' => 'Dati fattura e fattura elettronica salvati.',
    'invoice_number' => 'Numero fattura', 'currency' => 'Valuta', 'issue_date' => 'Data fattura', 'due_date' => 'Scadenza', 'buyer_reference' => 'Riferimento acquirente / Leitweg-ID',
    'buyer_reference_hint' => 'Sostituisce per questa fattura il riferimento acquirente dell\'anagrafica cliente.', 'buyer_reference_create_hint' => 'Facoltativo per fattura; se vuoto vale l\'anagrafica cliente.',
    'imported_notice' => 'Precompilata da un file fattura', 'imported_detail' => 'Grado di riconoscimento: :confidence %. Verificare numero, date, importi, IVA e righe sull\'originale.', 'original' => 'File originale',
    'structured_detail' => 'Fattura elettronica strutturata (:profile) — :lines riga/righe importate.',
    'table_lines_detail' => ':lines riga/righe importate da una tabella riconosciuta (controllo dei totali superato).',
    'validation' => ['passed' => 'Validazione KoSIT superata.', 'failed' => 'Validazione KoSIT fallita (:count errori) — dettagli nella validazione e-fattura.', 'unavailable' => 'Validazione KoSIT non disponibile (validatore non installato).'],
    'preferred_format' => 'Output preferito:', 'flexibility_hint' => 'PDF, XRechnung e ZUGFeRD restano disponibili separatamente.', 'mail_hint' => 'Scegliere il formato dell\'allegato. Le bozze vengono emesse automaticamente all\'invio.',
    'customer_default_option' => 'Standard cliente (altrimenti PDF)', 'customer_default_format' => 'Formato e-fattura predefinito', 'customer_default_format_hint' => 'Preimpostazione per nuove fatture e importazioni file di questo cliente.', 'no_default_format' => 'Nessuno standard (PDF)',
    'review_title' => 'Verifica importazione — :nr', 'review_nav' => 'Verifica importazione', 'review_action' => 'Verifica importazione', 'review_confirm' => 'Conferma verifica', 'review_confirmed' => 'Verifica dell\'importazione confermata.',
    'review_badge_open' => 'Verifica in sospeso', 'review_badge_done' => 'Verificata', 'review_back_to_invoice' => 'Alla fattura', 'review_original' => 'File originale', 'review_detected' => 'Valori riconosciuti',
    'review_no_preview' => 'Nessuna anteprima integrata per questo formato — scaricare il file originale.',
    'review_field' => 'Campo', 'review_detected_value' => 'Riconosciuto', 'review_current_value' => 'Nella bozza',
    'review_net' => 'Importo netto', 'review_tax' => 'Importo IVA', 'review_gross' => 'Importo lordo', 'review_tax_rate' => 'Aliquota IVA (%)',
    'review_skonto' => 'Sconto per pagamento anticipato', 'review_skonto_value' => ':percent % con pagamento entro :days giorni', 'review_payment_terms' => 'Termine di pagamento (giorni)',
    'review_iban' => 'IBAN nel documento', 'review_seller_vat' => 'Partita IVA nel documento',
    'review_items' => 'Righe della bozza', 'review_items_hint' => 'Le righe si modificano nella pagina della fattura; dati di testata ed e-fattura tramite «Dati e-fattura».',
    'review_item_description' => 'Descrizione', 'review_item_quantity' => 'Quantità', 'review_item_unit' => 'Unità', 'review_item_price' => 'Prezzo unitario', 'review_item_amount' => 'Importo',
    'error' => ['external_billing' => 'Un\'applicazione esterna gestisce la fatturazione di questo cliente. L\'importazione locale è bloccata.', 'duplicate' => 'Questo file fattura è già stato importato.', 'no_text' => 'Non è stato possibile leggere dati fattura dal file.', 'xml_not_einvoice' => 'Il file XML non è una fattura elettronica leggibile (XRechnung UBL/CII).', 'unsupported_format' => 'Sono supportati PDF, XML, DOCX, DOC, XLSX e XLS.', 'unreadable' => 'Il file fattura è danneggiato o non è stato possibile leggerlo in sicurezza.', 'proforma' => 'Le fatture pro forma possono essere inviate solo come PDF.'],
    'warning' => [
        'missing_number' => 'Il numero fattura non è stato riconosciuto in modo affidabile.', 'missing_issued_on' => 'La data fattura non è stata riconosciuta in modo affidabile.', 'missing_net' => 'L\'importo netto non è stato riconosciuto in modo affidabile.',
        'totals_mismatch' => 'Gli importi netto, IVA e lordo riconosciuti sono incoerenti.', 'duplicate_number' => 'Il numero fattura riconosciuto esiste già; è stato usato un numero locale libero.',
        'credit_note_source' => 'Il documento di origine è una nota di credito/fattura rettificativa — verificare il tipo di documento.',
        'prepaid_ignored' => 'Il documento di origine contiene acconti (BT-113); non sono stati importati.',
        'charges_present' => 'Il documento di origine contiene maggiorazioni a livello documento; non sono state importate.',
        'kosit_invalid' => 'La validazione KoSIT del documento di origine segnala errori.',
        'totals_recalculated_mismatch' => 'Il ricalcolo locale differisce dal totale del documento di origine.',
        'line_items_rejected_totals' => 'È stata riconosciuta una tabella di righe ma la sua somma contraddice il netto riconosciuto — è stata usata la riga riepilogativa.',
        'seller_iban_mismatch' => 'L\'IBAN nel documento differisce dall\'IBAN registrato dell\'organizzazione.',
        'seller_vat_mismatch' => 'La partita IVA nel documento differisce da quella registrata dell\'organizzazione.',
        'ai_fields_filled' => 'Alcuni campi sono stati completati da una proposta IA (con controllo di confidenza) — verificare sull\'originale.',
    ],
];
