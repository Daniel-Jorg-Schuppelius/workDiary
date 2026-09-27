<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Dossier personnel numérique (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Dossier personnel',
        'title_mine' => 'Mon dossier personnel',
        'nav' => 'Mon dossier personnel',
        'subtitle' => 'Dossier personnel de :name — confidentiel, visible uniquement par le cercle RH et la personne concernée.',
        'subtitle_mine' => 'Votre propre dossier personnel (accès personnel, lecture seule).',
        'back' => 'Retour à la liste du personnel',
        'empty' => 'Aucun document dans le dossier personnel pour le moment.',
        'confidential_fixed' => 'Les dossiers personnels sont toujours confidentiels — le commutateur est omis, la marque est imposée.',
        'retention_pending' => 'à partir du départ',
        'confirm_delete' => 'Détruire définitivement ce document du dossier personnel ? Les fichiers et versions sont supprimés ; le journal d\'audit est conservé.',
        'field' => [
            'title' => 'Titre',
            'category' => 'Catégorie',
            'validity' => 'Validité',
            'valid_from' => 'Valable à partir du',
            'valid_until' => 'Valable jusqu\'au',
            'retention_until' => 'Conservation jusqu\'au',
            'version' => 'Version',
            'updated_at' => 'Mis à jour',
            'description' => 'Description',
            'file' => 'Fichier',
            'version_note' => 'Note de version',
            'documents' => 'Documents',
        ],
        'action' => [
            'upload' => 'Ajouter un document',
            'edit' => 'Modifier',
            'save' => 'Enregistrer',
            'download' => 'Télécharger',
            'versions' => 'Versions',
            'delete' => 'Détruire',
        ],
        'flash' => [
            'created' => 'Le document a été ajouté au dossier personnel.',
            'updated' => 'Le document du dossier personnel a été mis à jour.',
        ],
    ],
    // Personal-Kapazität (MVP-940).
    'capacity' => [
        'title' => 'Capacité du personnel',
        'button' => 'Capacité',
        'subtitle' => 'Besoin planifié (ordres attribués) face au temps théorique des membres par semaine ; jours fériés et congés approuvés déduits.',
        'team' => 'Équipe',
        'week' => 'Semaine du :date',
        'members' => ':count membres',
        'empty' => 'Aucune équipe.',
        'hint' => 'Valeurs en heures : prévu / disponible.',
        'open_requisitions' => 'Postes ouverts au total : :count.',
    ],
    // Vertretungen beim Austritt (MVP-941).
    'offboarding' => [
        'deputies' => 'Réattribuer les suppléances',
        'deputies_hint' => 'Ces personnes ont désigné le membre sortant comme suppléant. Sans choix, la suppléance prend fin.',
        'deputy_for' => 'Nouveau suppléant pour :name',
        'no_deputy' => '— aucun suppléant —',
    ],
    // Arbeitsvertrag zur Unterschrift (MVP-939).
    'employment' => [
        'title' => 'Contrat de travail à signer',
        'intro' => 'Le contrat est envoyé par lien à la personne ; l\'organisation contresigne ensuite. La version signée est classée dans le dossier du personnel.',
        'send' => 'Envoyer pour signature',
        'default_title' => 'Contrat de travail :name',
        'default_declaration' => 'J\'ai lu le contrat de travail et je l\'accepte.',
        'filed_note' => 'Version signée du contrat :number.',
        'field' => [
            'title' => 'Intitulé',
            'starts_on' => 'Début',
            'email' => 'E-mail de la personne',
            'declaration_text' => 'Déclaration de consentement',
            'file' => 'Contrat (PDF)',
        ],
        'flash' => [
            'sent' => 'Contrat de travail envoyé à :email pour signature.',
        ],
        'error' => [
            'email' => 'Veuillez indiquer une adresse e-mail.',
        ],
    ],
];
