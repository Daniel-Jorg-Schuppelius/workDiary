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
        'document_unavailable' => 'Le document n’est pas encore disponible dans le logiciel comptable. Veuillez réessayer plus tard.',
    ],

    // Aperçu en tant que client (phase 153, MVP-1229–1231).
    'preview' => [
        'action' => 'Voir le portail',
        'action_as' => 'Voir en tant que cette personne',
        'no_account' => 'Aperçu disponible après la création d’un accès',
        'running' => 'Aperçu en cours en tant que :name jusqu’à :until.',
        'stop' => 'Terminer l’aperçu',
        'banner' => 'Aperçu : vous voyez le portail en tant que :name (:customer). Les actions sont verrouillées.',
        'banner_until' => 'Se termine à :time.',
        'title_prefix' => 'Aperçu',
        'blocked' => 'Pendant l’aperçu, les actions sont verrouillées.',
        'blocked_page' => 'Cette page n’est pas disponible pendant l’aperçu.',
        'nested_support' => 'Aucun aperçu n’est possible pendant une session de support.',
        'deactivated' => 'Il n’y a pas d’aperçu pour un accès désactivé.',
        'ended_manual' => 'Aperçu terminé.',
        'ended_replaced' => 'L’aperçu précédent a été terminé.',
        'ended_expired' => 'L’aperçu a expiré après :minutes minutes.',
        'ended_actor_changed' => 'L’aperçu a été terminé parce que la connexion a changé.',
        'ended_permission_lost' => 'L’aperçu a été terminé parce que le droit manque.',
        'ended_organization_changed' => 'L’aperçu a été terminé parce que l’organisation a changé.',
        'ended_account_changed' => 'L’aperçu a été terminé parce que le compte du portail a changé.',
        'ended_account_deactivated' => 'L’aperçu a été terminé parce que le compte du portail n’est plus disponible.',
    ],
    // Accès avec mot de passe initial (MVP-1232).
    'direct' => [
        'action' => 'Créer un accès',
        'title' => 'Créer un accès au portail avec mot de passe initial',
        'intro' => 'Vous recevez un mot de passe initial aléatoire qui n’est affiché qu’une seule fois. Remettez-le en personne ou par téléphone, jamais par e-mail. À la première connexion, la personne définit son propre mot de passe.',
        'submit' => 'Créer l’accès',
        'flash' => 'Accès pour :email créé.',
        'password_heading' => 'Mot de passe initial pour :email',
        'password_hint' => 'Affiché une seule fois. À remettre en personne ou par téléphone, jamais par e-mail. À la première connexion, un mot de passe personnel doit être défini.',
        'copy' => 'Copier',
        'copied' => 'Copié',
        'state_provisional' => 'Mot de passe initial en attente',
        'reissue_action' => 'Générer un mot de passe initial',
        'reissue_confirm_title' => 'Générer un nouveau mot de passe initial',
        'reissue_confirm_message' => 'Le mot de passe actuel de :email cesse immédiatement d’être valable, une invitation ouverte expire et toutes les sessions sont terminées. Le nouveau mot de passe initial est affiché une seule fois ; à la prochaine connexion, un mot de passe personnel doit être défini. Les méthodes à deux facteurs sont conservées.',
        'reissue_confirm_label' => 'Générer',
        'reissue_flash' => 'Nouveau mot de passe initial pour :email généré.',
        'reauth_hint' => 'La création d’un accès avec mot de passe initial exige une connexion récente. Confirmez d’abord votre mot de passe, puis rouvrez la boîte de dialogue.',
        'reauth_action' => 'Confirmer le mot de passe',
    ],
    // Changer le mot de passe dans le portail (MVP-1233).
    'password_change' => [
        'title' => 'Changer le mot de passe',
        'forced_hint' => 'Vous avez reçu un mot de passe initial. Veuillez définir maintenant votre propre mot de passe avant d’utiliser le portail.',
        'intro' => 'Saisissez votre mot de passe actuel et un nouveau mot de passe. Les connexions de cet accès sur d’autres appareils seront terminées.',
        'current' => 'Mot de passe actuel',
        'new' => 'Nouveau mot de passe',
        'confirm' => 'Répéter le nouveau mot de passe',
        'submit' => 'Enregistrer le mot de passe',
        'flash' => 'Votre mot de passe a été modifié.',
        'profile_heading' => 'Mot de passe',
        'profile_intro' => 'Changez votre mot de passe si vous soupçonnez que quelqu’un le connaît ou si vous avez reçu un mot de passe initial.',
        'profile_link' => 'Changer le mot de passe',
    ],
];
