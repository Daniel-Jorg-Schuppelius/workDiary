<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail_file.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Mail-Dateien ablegen und ansehen (Feature 204, MVP-1652/1653).
 */

return [
    'title' => 'E-mail',
    'title_with_subject' => 'E-mail: :subject',
    'no_subject' => '(senza oggetto)',
    'belongs_to' => 'Appartiene a',
    'format' => [
        'eml' => 'File e-mail (.eml)',
        'msg' => 'Messaggio di Outlook (.msg)',
    ],
    'field' => [
        'from' => 'Da',
        'to' => 'A',
        'cc' => 'Cc',
        'bcc' => 'Ccn',
        'date' => 'Data',
        'subject' => 'Oggetto',
        'message_id' => 'Message-ID',
        'file' => 'File',
    ],
    'text' => [
        'heading' => 'Testo',
        'from_html' => 'L’e-mail ha solo una parte HTML; il testo è stato ricavato da essa.',
        'truncated' => 'Il testo è molto lungo e viene mostrato abbreviato.',
        'rtf_only' => 'L’e-mail contiene solo testo formattato di Outlook (RTF), che workDiary non mostra. Scarichi il file per aprirlo in Outlook.',
        'empty' => 'L’e-mail non contiene testo.',
    ],
    'html' => [
        'show' => 'Mostra la visualizzazione originale',
        'hide' => 'Nascondi la visualizzazione originale',
        'heading' => 'Visualizzazione originale',
        'hint' => 'Mostrata in modo isolato: senza script, senza moduli e senza immagini da internet.',
        'frame_title' => 'Visualizzazione originale dell’e-mail',
        'frame_unavailable' => 'La visualizzazione originale è troppo ampia per essere mostrata in modo isolato.',
    ],
    'attachments' => [
        'heading' => 'Allegati',
        'none' => 'L’e-mail non ha allegati.',
        'inline' => 'incorporato nel testo',
        'embedded' => 'E-mail incorporata',
        'open' => 'Apri',
        'download' => 'Scarica',
        'adopt' => 'Conserva come file',
        'adopt_hint' => 'Salva una copia dell’allegato come file a sé nella stessa pratica.',
        'preview_heading' => 'Anteprima: :name',
        'preview_close' => 'Chiudi anteprima',
        'unchecked' => 'Tipo di file non verificato: viene scaricato come .bin.',
    ],
    'download_original' => 'Scarica il file e-mail',
    'unreadable' => [
        'title' => 'L’e-mail non può essere visualizzata',
        'hint' => 'Il file resta salvato senza modifiche e può essere scaricato.',
        'pending' => 'L’e-mail è in fase di lettura. Ricarichi la pagina tra un momento.',
    ],
    'failure' => [
        'missing' => 'Il file e-mail non esiste più.',
        'too_large' => 'Il file e-mail supera la dimensione consentita per gli allegati.',
        'too_many_attachments' => 'L’e-mail ha più allegati di quanti workDiary ne legga.',
        'attachments_too_large' => 'Gli allegati dell’e-mail sono troppo grandi nel complesso.',
        'too_deep' => 'L’e-mail contiene e-mail incorporate l’una nell’altra a una profondità eccessiva.',
        'too_complex' => 'L’e-mail ha una struttura troppo annidata per essere letta in sicurezza.',
        'malformed' => 'Il file è danneggiato o non è un’e-mail.',
        'aborted' => 'Non è stato possibile leggere l’e-mail in tempo.',
    ],
    'error' => [
        'adopt_too_large' => ':name supera :size e non può essere conservato.',
    ],
    'flash' => [
        'adopted' => '«:name» è stato salvato come file.',
    ],
    'section' => [
        'view' => 'Visualizza e-mail',
        'hint' => 'Max. :mb MB. Consentiti: :formats.',
        'drop' => 'Trascini qui un file, ad esempio un’e-mail da Outlook (.msg) o un file .eml.',
        'drop_single' => 'Rilasci un file alla volta.',
    ],
    // Demo-Daten (erfunden): Rückfrage am Hauptauftrag.
    'demo' => [
        'subject' => 'Domanda sul preventivo – misure delle finestre',
        'body' => "Buongiorno,\n\npotreste confermarci di nuovo le misure delle finestre al piano terra? L'ordine deve partire questa settimana.\n\nCordiali saluti\nErika Beispiel",
        'sender' => 'Erika Beispiel (Beispiel GmbH)',
        'attachment' => 'Nota: finestra piano terra, larghezza 1,20 m, altezza 1,40 m.',
    ],
];
