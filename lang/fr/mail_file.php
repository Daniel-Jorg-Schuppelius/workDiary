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
    'title_with_subject' => 'E-mail : :subject',
    'no_subject' => '(sans objet)',
    'belongs_to' => 'Appartient à',
    'format' => [
        'eml' => 'Fichier e-mail (.eml)',
        'msg' => 'Message Outlook (.msg)',
    ],
    'field' => [
        'from' => 'De',
        'to' => 'À',
        'cc' => 'Cc',
        'bcc' => 'Cci',
        'date' => 'Date',
        'subject' => 'Objet',
        'message_id' => 'Message-ID',
        'file' => 'Fichier',
    ],
    'text' => [
        'heading' => 'Texte',
        'from_html' => 'L’e-mail ne contient qu’une partie HTML ; le texte en a été extrait.',
        'truncated' => 'Le texte est très long et s’affiche de manière abrégée.',
        'rtf_only' => 'L’e-mail ne contient que du texte Outlook mis en forme (RTF), que workDiary n’affiche pas. Téléchargez le fichier pour l’ouvrir dans Outlook.',
        'empty' => 'L’e-mail ne contient aucun texte.',
    ],
    'html' => [
        'show' => 'Afficher la présentation d’origine',
        'hide' => 'Masquer la présentation d’origine',
        'heading' => 'Présentation d’origine',
        'hint' => 'Affichée de manière isolée : sans scripts, sans formulaires et sans images provenant d’internet.',
        'frame_title' => 'Présentation d’origine de l’e-mail',
        'frame_unavailable' => 'La présentation d’origine est trop volumineuse pour être affichée de manière isolée.',
    ],
    'attachments' => [
        'heading' => 'Pièces jointes',
        'none' => 'L’e-mail n’a pas de pièces jointes.',
        'inline' => 'intégrée au texte',
        'embedded' => 'E-mail intégré',
        'open' => 'Ouvrir',
        'download' => 'Télécharger',
        'adopt' => 'Conserver comme fichier',
        'adopt_hint' => 'Enregistre une copie de la pièce jointe comme fichier distinct sur le même dossier.',
        'preview_heading' => 'Aperçu : :name',
        'preview_close' => 'Fermer l’aperçu',
        'unchecked' => 'Type de fichier non vérifié — téléchargé en .bin.',
    ],
    'download_original' => 'Télécharger le fichier e-mail',
    'unreadable' => [
        'title' => 'L’e-mail ne peut pas être affiché',
        'hint' => 'Le fichier reste enregistré sans modification et peut être téléchargé.',
        'pending' => 'L’e-mail est en cours de lecture. Rechargez la page dans un instant.',
    ],
    'failure' => [
        'missing' => 'Le fichier e-mail n’existe plus.',
        'too_large' => 'Le fichier e-mail dépasse la taille autorisée pour les pièces jointes.',
        'too_many_attachments' => 'L’e-mail contient plus de pièces jointes que workDiary n’en lit.',
        'attachments_too_large' => 'Les pièces jointes de l’e-mail sont trop volumineuses au total.',
        'too_deep' => 'L’e-mail contient des e-mails imbriqués trop profondément les uns dans les autres.',
        'too_complex' => 'La structure de l’e-mail est trop imbriquée pour le lire en toute sécurité.',
        'malformed' => 'Le fichier est endommagé ou n’est pas un e-mail.',
        'aborted' => 'L’e-mail n’a pas pu être lu à temps.',
    ],
    'error' => [
        'adopt_too_large' => ':name dépasse :size et ne peut pas être conservé.',
    ],
    'flash' => [
        'adopted' => '« :name » a été enregistré comme fichier.',
    ],
    'section' => [
        'view' => 'Voir l’e-mail',
        'hint' => 'Max. :mb Mo. Autorisés : :formats.',
        'drop' => 'Faites glisser un fichier ici, par exemple un e-mail d’Outlook (.msg) ou un fichier .eml.',
        'drop_single' => 'Déposez un seul fichier à la fois.',
    ],
    // Demo-Daten (erfunden): Rückfrage am Hauptauftrag.
    'demo' => [
        'subject' => 'Question sur le devis – dimensions des fenêtres',
        'body' => "Bonjour,\n\npourriez-vous nous confirmer à nouveau les dimensions des fenêtres du rez-de-chaussée ? La commande doit partir cette semaine.\n\nCordialement\nErika Beispiel",
        'sender' => 'Erika Beispiel (Beispiel GmbH)',
        'attachment' => 'Note : fenêtre rez-de-chaussée, largeur 1,20 m, hauteur 1,40 m.',
    ],
];
