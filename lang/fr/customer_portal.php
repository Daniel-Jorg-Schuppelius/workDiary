<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal: Passwort vergessen, Zugang zurücksetzen, Rechnungsdokument (MVP-1096/1097).
return [
    'password' => [
        'mail_subject' => 'Réinitialiser votre mot de passe du portail client de :org',
        'mail_heading' => 'Réinitialiser le mot de passe',
        'mail_intro' => 'Une réinitialisation du mot de passe a été demandée pour votre accès au portail client de :org. Le lien suivant vous permet de définir un nouveau mot de passe.',
        'mail_validity' => 'Le lien est à usage unique et valable :minutes minutes.',
        'mail_ignore' => 'Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail — votre mot de passe actuel reste valable.',
        'sessions_hint' => 'Le nouveau mot de passe met fin à toutes les sessions ouvertes de cet accès. Un second facteur déjà configuré est conservé.',
    ],
    'reset' => [
        'action' => 'Réinitialiser l’accès',
        'confirm_title' => 'Réinitialiser l’accès au portail',
        'confirm_message' => 'Le mot de passe actuel cesse immédiatement d’être valable, toutes les sessions sont fermées et :email reçoit une nouvelle invitation. Les méthodes à deux facteurs configurées sont conservées.',
        'confirm_label' => 'Réinitialiser',
        'flash' => 'Accès réinitialisé — nouvelle invitation envoyée à :email.',
        'mail_heading' => 'Votre accès au portail client a été réinitialisé',
        'mail_intro' => ':org a réinitialisé votre accès au portail client. Votre mot de passe précédent n’est plus valable. Le lien suivant vous permet de définir un nouveau mot de passe, puis de vous connecter.',
    ],
    'second_factor' => [
        'action' => 'Réinitialiser le second facteur',
        'confirm_title' => 'Réinitialiser le second facteur',
        'confirm_message' => 'Toutes les méthodes à deux facteurs de :email (application, clés d’accès, codes de récupération) sont supprimées et toutes les sessions sont fermées. N’utilisez cette option que si le client ne dispose plus d’aucun facteur et que vous avez vérifié son identité.',
        'confirm_label' => 'Réinitialiser',
        'flash' => 'Second facteur de :email réinitialisé — le client a été informé.',
        'mail_subject' => 'Connexion à deux facteurs du portail client de :org réinitialisée',
        'mail_heading' => 'Votre second facteur a été réinitialisé',
        'mail_intro' => ':org a supprimé les méthodes à deux facteurs de votre accès au portail client. Connectez-vous avec votre mot de passe ; si :org exige une connexion à deux facteurs, vous la configurerez à nouveau à ce moment-là.',
        'mail_unexpected' => 'Si vous n’êtes pas à l’origine de cette demande, veuillez contacter immédiatement votre interlocuteur chez :org.',
    ],
    'invoices' => [
        'pdf_label' => 'Télécharger la facture :number au format PDF',
    ],
];
