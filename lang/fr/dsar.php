<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'portal' => [
        'title' => 'Demande relative à la protection des données',
        'subtitle' => 'Vos droits en tant que personne concernée',
        'footer' => 'Cette page sert exclusivement à exercer vos droits de personne concernée. N’y transmettez ni données de paiement ni identifiants.',
    ],

    'landing' => [
        'title' => 'Déposer une demande de protection des données',
        'intro' => 'Cette procédure permet aux personnes concernées d’exercer leurs droits au titre du règlement général sur la protection des données.',
        'no_link' => 'Pour déposer une demande, il vous faut le lien du responsable du traitement. Adressez-vous à l’organisation dont les données vous concernent.',
        'rights' => 'Types de demande possibles',
    ],

    'legal_note' => 'Ces indications sont une information et non un conseil juridique. Le texte de loi fait foi.',
    'privacy_notice' => 'Vos informations servent uniquement au traitement de cette demande, sont conservées chiffrées et supprimées à l’expiration du délai de conservation. La base légale est l’art. 6, par. 1, point c) du RGPD, combiné aux art. 15 à 21 du RGPD.',
    'identity_hint' => 'Avant toute communication, le responsable du traitement vérifie votre identité (art. 12, par. 6 du RGPD) et peut vous contacter séparément à cette fin.',

    'form' => [
        'title' => 'Déposer une demande',
        'what' => 'De quoi s’agit-il ?',
        'what_text' => 'Vous pouvez demander l’accès aux données vous concernant, leur rectification ou leur effacement, la limitation du traitement, la portabilité de vos données ou vous opposer au traitement.',
        'submit' => 'Envoyer la demande',
    ],

    'field' => [
        'type' => 'Type de demande',
        'full_name' => 'Nom et prénom',
        'email' => 'Adresse e-mail pour la réponse',
        'reference' => 'Référence, numéro de client ou de personnel (facultatif)',
        'message' => 'Votre demande',
        'attachments' => 'Pièces jointes (facultatif)',
        'attachments_hint' => 'Au maximum :max fichiers, de :size Mo chacun.',
        'honeypot' => 'Ne pas remplir',
        'privacy_ack' => 'J’ai lu la note d’information et je fournis mes indications en toute bonne foi.',
    ],

    'receipt' => [
        'title' => 'Demande reçue',
        'headline' => 'Votre demande a bien été reçue.',
        'number' => 'Référence : :nr',
        'mail_sent' => 'Un accusé de réception a été envoyé à l’adresse indiquée. Le délai légal de traitement court à compter de la réception de ce jour.',
        'back' => 'Retour au formulaire',
    ],

    'confirmed' => [
        'title' => 'Adresse confirmée',
        'headline' => 'Merci — votre adresse e-mail est confirmée.',
        'text' => 'La confirmation a été consignée au dossier :nr.',
        'no_deadline_effect' => 'Le délai de traitement continue de courir à compter de la réception de votre demande ; la confirmation ne le reporte pas.',
    ],

    'mail' => [
        'subject' => 'Accusé de réception de votre demande de protection des données :nr',
        'headline' => 'Votre demande de protection des données a été reçue',
        'intro' => 'Une demande de protection des données a été déposée avec cette adresse e-mail sous la référence :nr.',
        'deadline' => 'Le délai légal de traitement court à compter de la réception et prend fin le :date.',
        'confirm_button' => 'Confirmer l’adresse e-mail',
        'confirm_note' => 'La confirmation atteste que cette adresse est joignable. Elle ne remplace pas la vérification de votre identité — le responsable du traitement vous contactera séparément à ce sujet. Le clic est sans effet sur le délai.',
        'not_you' => 'Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail. Aucune information n’est communiquée sans vérification d’identité.',
    ],

    'subject' => [
        'email' => 'E-mail : :value',
        'reference' => 'Référence : :value',
    ],

    'internal' => [
        'from_portal' => 'Entrée par le portail',
        'portal_banner' => 'Cette demande provient du portail public des personnes concernées. Les données d’identité sont une déclaration non vérifiée.',
        'contact_email' => 'Adresse de réponse',
        'email_confirmed' => 'confirmée le :date',
        'email_unconfirmed' => 'non confirmée',
        'identity_required' => 'L’identité doit être vérifiée et confirmée avant toute communication (entrée par le portail).',
    ],

    'admin' => [
        'nav' => 'Portail des personnes concernées',
        'title' => 'Gérer le portail des personnes concernées',
        'subtitle' => 'Configurer le formulaire public des demandes de personnes concernées.',
        'link' => 'Lien public',
        'link_hint' => 'Publiez ce lien dans votre politique de confidentialité. Il ne peut pas être déduit du nom de l’organisation.',
        'rotate' => 'Renouveler le lien',
        'rotate_confirm' => 'Renouveler vraiment le lien ? Les liens déjà publiés deviendront invalides.',
        'not_created' => 'Aucun portail des personnes concernées n’a encore été créé. Enregistrez pour en créer un avec un lien aléatoire.',
        'settings' => 'Paramètres',
        'visibility' => 'Visibilité',
        'is_enabled' => 'Portail actif (accessible publiquement)',
        'allow_attachments' => 'Autoriser les pièces jointes',
        'presentation' => 'Présentation',
        'intro_text' => 'Texte d’introduction (facultatif)',
        'default_locale' => 'Langue par défaut (facultatif, p. ex. fr)',
        'saved' => 'Portail des personnes concernées enregistré.',
        'rotated' => 'Le lien du portail a été renouvelé. Les liens déjà publiés sont désormais invalides.',
    ],
];
