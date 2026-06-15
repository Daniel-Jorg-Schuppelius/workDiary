<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : external.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'party' => [
        'subcontractor' => 'Sous-traitant',
        'inspector' => 'Inspecteur',
        'expert' => 'Expert',
        'other' => 'Autre',
    ],
    'ability' => [
        'view' => 'Consulter',
        'comment' => 'Commenter',
        'upload' => 'Téléverser un fichier',
        'confirm' => 'Confirmer',
    ],
    'status' => [
        'invited' => 'Invité',
        'accessed' => 'Consulté',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
    ],
    'subject' => [
        'order' => 'Mission',
        'generic' => 'Élément',
    ],
    'panel' => [
        'title' => 'Participants externes',
        'invite' => 'Inviter',
        'empty' => 'Aucun participant externe invité pour le moment.',
        'link_once' => 'Copiez ce lien une seule fois et envoyez-le au participant externe — il ne sera plus affiché.',
    ],
    'col' => [
        'name' => 'Nom',
        'party' => 'Type',
        'abilities' => 'Droits',
        'status' => 'Statut',
        'expires' => 'Valable jusqu’au',
    ],
    'group' => [
        'contact' => 'Contact',
        'abilities' => 'Actions autorisées',
        'validity' => 'Validité',
    ],
    'field' => [
        'name' => 'Nom',
        'email' => 'E-mail (facultatif)',
        'role' => 'Rôle',
        'party' => 'Type',
        'ttl_days' => 'Validité (jours)',
    ],
    'hint' => [
        'role' => 'p. ex. Électricien, inspecteur TÜV',
        'abilities' => 'La consultation est toujours autorisée. Les actions supplémentaires sont strictement appliquées côté serveur.',
        'ttl_days' => '1 à 180 jours. L’accès expire automatiquement ensuite.',
    ],
    'invite' => [
        'title' => 'Inviter un participant externe',
        'eyebrow' => 'Participants externes',
        'submit' => 'Inviter et générer le lien',
        'once_hint' => 'Le lien d’accès est affiché une seule fois après la création — seul le hachage est conservé.',
    ],
    'revoke' => [
        'action' => 'Révoquer',
        'title' => 'Révoquer l’accès',
        'message' => 'L’accès externe sera bloqué immédiatement. Continuer ?',
        'confirm' => 'Révoquer',
    ],
    'flash' => [
        'invited' => 'Participant externe « :name » invité.',
        'revoked' => 'Accès externe révoqué.',
    ],
    'public' => [
        'title' => 'Accès externe',
        'hello' => 'Bonjour :name',
        'expires_note' => 'Cet accès est valable jusqu’au :date.',
        'view_only' => 'Cet accès est limité à la consultation.',
        'comment_heading' => 'Laisser un commentaire',
        'comment_placeholder' => 'Votre remarque …',
        'comment_submit' => 'Envoyer le commentaire',
        'comment_saved' => 'Commentaire enregistré.',
        'upload_heading' => 'Téléverser un fichier ou une photo',
        'upload_hint' => 'Autorisé : JPG, PNG, GIF, WEBP, PDF (max. 25 Mo).',
        'upload_submit' => 'Téléverser',
        'upload_saved' => 'Fichier téléversé.',
        'upload_rejected' => 'Type de fichier non autorisé.',
        'confirm_heading' => 'Confirmer / Réceptionner',
        'confirm_note_placeholder' => 'Remarque facultative pour la confirmation …',
        'confirm_accept' => 'Je confirme l’exactitude des informations.',
        'confirm_submit' => 'Confirmer',
        'confirmed' => 'Confirmation enregistrée.',
    ],
];
