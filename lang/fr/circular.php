<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Circulaires',
    'subtitle' => 'Communications commerciales à un ensemble filtré de clients',
    'empty' => 'Aucune circulaire créée pour l’instant.',
    'empty_recipients' => 'Aucun destinataire enregistré.',
    'created' => 'Circulaire créée.',
    'sent' => 'Circulaire envoyée.',
    'already_sent' => 'Cette circulaire a déjà été envoyée.',
    'no_recipients' => 'Le filtre sélectionné ne correspond à aucun client.',
    'mandatory_short' => 'Communication obligatoire',
    'portal_short' => 'Visible dans le portail',
    'no_email' => 'aucune adresse e-mail',
    'confirm_send' => 'Envoyer la circulaire à :count destinataires maintenant ?',
    'body_hint' => 'Espaces réservés : :firma, :kunde, :ansprechpartner',
    'mandatory_hint' => 'Les communications obligatoires atteignent aussi les clients ayant refusé les envois groupés — uniquement pour les informations légalement requises.',
    'portal_hint' => 'La communication apparaît également dans le portail client.',

    'audience' => [
        'heading' => 'Destinataires (:count)',
    ],

    'action' => [
        'create' => 'Créer une circulaire',
        'save_draft' => 'Enregistrer comme brouillon',
        'send' => 'Envoyer',
        'show' => 'Afficher',
    ],

    'column' => [
        'subject' => 'Objet',
        'status' => 'Statut',
        'recipients' => 'Destinataires',
        'skipped' => 'Non atteints',
        'sent_at' => 'Envoyée le',
        'customer' => 'Client',
        'email' => 'E-mail',
    ],

    'field' => [
        'body' => 'Texte',
        'is_mandatory' => 'Communication obligatoire',
        'portal_notice' => 'Afficher dans le portail client',
    ],

    'filter' => [
        'search' => 'Recherche',
        'city' => 'Ville',
        'zip_prefix' => 'Le code postal commence par',
        'zip_hint' => 'p. ex. 30 pour la région de Hanovre',
        'with_active_projects' => 'uniquement les clients avec un projet actif',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'sending' => 'envoi en cours',
        'sent' => 'envoyée',
    ],

    'recipient_status' => [
        'pending' => 'en attente',
        'sent' => 'remise',
        'skipped' => 'ignoré',
        'failed' => 'échec',
    ],

    'reason' => [
        'no_email' => 'aucune adresse e-mail enregistrée',
    ],
];
