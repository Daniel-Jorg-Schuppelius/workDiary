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
    'title' => 'Email',
    'title_with_subject' => 'Email: :subject',
    'no_subject' => '(no subject)',
    'belongs_to' => 'Belongs to',
    'format' => [
        'eml' => 'Email file (.eml)',
        'msg' => 'Outlook message (.msg)',
    ],
    'field' => [
        'from' => 'From',
        'to' => 'To',
        'cc' => 'Cc',
        'bcc' => 'Bcc',
        'date' => 'Date',
        'subject' => 'Subject',
        'message_id' => 'Message ID',
        'file' => 'File',
    ],
    'text' => [
        'heading' => 'Text',
        'from_html' => 'The email only has an HTML part; the text was derived from it.',
        'truncated' => 'The text is very long and is shown shortened.',
        'rtf_only' => 'The email only contains formatted Outlook text (RTF), which workDiary does not display. Download the file to open it in Outlook.',
        'empty' => 'The email contains no text.',
    ],
    'html' => [
        'show' => 'Show original layout',
        'hide' => 'Hide original layout',
        'heading' => 'Original layout',
        'hint' => 'Shown isolated: without scripts, without forms and without images from the internet.',
        'frame_title' => 'Original layout of the email',
        'frame_unavailable' => 'The original layout is too extensive to show in isolation.',
    ],
    'attachments' => [
        'heading' => 'Attachments',
        'none' => 'The email has no attachments.',
        'inline' => 'embedded in the text',
        'embedded' => 'Embedded email',
        'open' => 'Open',
        'download' => 'Download',
        'adopt' => 'Keep as file',
        'adopt_hint' => 'Stores a copy of the attachment as a separate file on the same record.',
        'preview_heading' => 'Preview: :name',
        'preview_close' => 'Close preview',
        'unchecked' => 'File type not checked — downloaded as .bin.',
    ],
    'download_original' => 'Download email file',
    'unreadable' => [
        'title' => 'The email cannot be displayed',
        'hint' => 'The file stays stored unchanged and can be downloaded.',
        'pending' => 'The email is being read. Reload the page in a moment.',
    ],
    'failure' => [
        'missing' => 'The email file no longer exists.',
        'too_large' => 'The email file is larger than allowed for attachments.',
        'too_many_attachments' => 'The email has more attachments than workDiary reads.',
        'attachments_too_large' => 'The attachments of the email are too large in total.',
        'too_deep' => 'The email contains emails embedded too deeply in one another.',
        'too_complex' => 'The email is structured too intricately to be read safely.',
        'malformed' => 'The file is damaged or not an email.',
        'aborted' => 'The email could not be read in time.',
    ],
    'error' => [
        'adopt_too_large' => ':name is larger than :size and cannot be kept.',
    ],
    'flash' => [
        'adopted' => '“:name” has been stored as a file.',
    ],
    'section' => [
        'view' => 'View email',
        'hint' => 'Max. :mb MB. Allowed: :formats.',
        'drop' => 'Drag a file here, for example an email from Outlook (.msg) or an .eml file.',
        'drop_single' => 'Please drop one file at a time.',
    ],
    // Demo-Daten (erfunden): Rückfrage am Hauptauftrag.
    'demo' => [
        'subject' => 'Question about the quote – window dimensions',
        'body' => "Hello,\n\ncould you confirm the dimensions of the ground-floor windows once more? The order is due to go out this week.\n\nBest regards\nErika Beispiel",
        'sender' => 'Erika Beispiel (Beispiel GmbH)',
        'attachment' => 'Note: ground-floor window, width 1.20 m, height 1.40 m.',
    ],
];
