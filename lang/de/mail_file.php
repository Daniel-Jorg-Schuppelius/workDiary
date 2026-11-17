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
    'title' => 'Mail',
    'title_with_subject' => 'Mail: :subject',
    'no_subject' => '(ohne Betreff)',
    'belongs_to' => 'Gehört zu',
    'format' => [
        'eml' => 'E-Mail-Datei (.eml)',
        'msg' => 'Outlook-Nachricht (.msg)',
    ],
    'field' => [
        'from' => 'Von',
        'to' => 'An',
        'cc' => 'Kopie',
        'bcc' => 'Blindkopie',
        'date' => 'Datum',
        'subject' => 'Betreff',
        'message_id' => 'Message-ID',
        'file' => 'Datei',
    ],
    'text' => [
        'heading' => 'Text',
        'from_html' => 'Die Mail hat nur einen HTML-Teil; der Text ist daraus gewonnen.',
        'truncated' => 'Der Text ist sehr lang und wird gekürzt angezeigt.',
        'rtf_only' => 'Die Mail enthält nur formatierten Outlook-Text (RTF), den workDiary nicht anzeigt. Laden Sie die Datei herunter, um sie in Outlook zu öffnen.',
        'empty' => 'Die Mail enthält keinen Text.',
    ],
    'html' => [
        'show' => 'Originaldarstellung anzeigen',
        'hide' => 'Originaldarstellung ausblenden',
        'heading' => 'Originaldarstellung',
        'hint' => 'Abgeschottet angezeigt: ohne Skripte, ohne Formulare und ohne Bilder aus dem Internet.',
        'frame_title' => 'Originaldarstellung der Mail',
        'frame_unavailable' => 'Die Originaldarstellung ist zu umfangreich, um sie abgeschottet anzuzeigen.',
    ],
    'attachments' => [
        'heading' => 'Anhänge',
        'none' => 'Die Mail hat keine Anhänge.',
        'inline' => 'im Text eingebettet',
        'embedded' => 'Eingebettete Mail',
        'open' => 'Öffnen',
        'download' => 'Herunterladen',
        'adopt' => 'Als Datei übernehmen',
        'adopt_hint' => 'Legt eine Kopie des Anhangs als eigene Datei am selben Vorgang ab.',
        'preview_heading' => 'Vorschau: :name',
        'preview_close' => 'Vorschau schließen',
        'unchecked' => 'Dateityp nicht geprüft — wird als .bin heruntergeladen.',
    ],
    'download_original' => 'Mail-Datei herunterladen',
    'unreadable' => [
        'title' => 'Die Mail kann nicht angezeigt werden',
        'hint' => 'Die Datei bleibt unverändert abgelegt und lässt sich herunterladen.',
        'pending' => 'Die Mail wird gelesen. Laden Sie die Seite gleich neu.',
    ],
    'failure' => [
        'missing' => 'Die Mail-Datei ist nicht mehr vorhanden.',
        'too_large' => 'Die Mail-Datei ist größer als für Anhänge erlaubt.',
        'too_many_attachments' => 'Die Mail hat mehr Anhänge, als workDiary liest.',
        'attachments_too_large' => 'Die Anhänge der Mail sind zusammen zu groß.',
        'too_deep' => 'Die Mail enthält zu tief ineinander eingebettete Mails.',
        'too_complex' => 'Die Mail ist zu verschachtelt aufgebaut, um sie sicher zu lesen.',
        'malformed' => 'Die Datei ist beschädigt oder keine Mail.',
        'aborted' => 'Die Mail konnte nicht rechtzeitig gelesen werden.',
    ],
    'error' => [
        'adopt_too_large' => ':name ist größer als :size und kann nicht übernommen werden.',
    ],
    'flash' => [
        'adopted' => '„:name“ ist als Datei abgelegt.',
    ],
    'section' => [
        'view' => 'Mail ansehen',
        'hint' => 'Max. :mb MB. Erlaubt: :formats.',
        'drop' => 'Datei hierher ziehen, etwa eine Mail aus Outlook (.msg) oder eine .eml-Datei.',
        'drop_single' => 'Bitte jeweils eine Datei ablegen.',
    ],
    // Demo-Daten (erfunden): Rückfrage am Hauptauftrag.
    'demo' => [
        'subject' => 'Rückfrage zum Angebot – Maße der Fenster',
        'body' => "Guten Tag,\n\nkönnten Sie uns die Maße der Fenster im Erdgeschoss noch einmal bestätigen? Die Bestellung soll diese Woche raus.\n\nViele Grüße\nErika Beispiel",
        'sender' => 'Erika Beispiel (Beispiel GmbH)',
        'attachment' => 'Notiz: Fenster EG, Breite 1,20 m, Höhe 1,40 m.',
    ],
];
