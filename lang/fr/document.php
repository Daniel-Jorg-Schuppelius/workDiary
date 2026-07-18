<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Documents',
        'versions' => 'Versions',
        'version_history' => 'Historique des versions',
    ],

    'subtitle' => 'Gérer les contrats, certificats, rapports de contrôle et autres documents.',

    'field' => [
        'title' => 'Titre',
        'type' => 'Type',
        'status' => 'Statut',
        'reference' => 'Référence',
        'validity' => 'Validité',
        'valid_from' => 'Valide à partir du',
        'valid_until' => 'Valide jusqu’au',
        'description' => 'Description',
        'file' => 'Fichier',
        'version' => 'Version',
        'version_note' => 'Note de version',
        'creator' => 'Créé par',
    ],

    'action' => [
        'create' => 'Ajouter un document',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'archive' => 'Archiver',
        'download' => 'Télécharger',
        'add_version' => 'Téléverser une nouvelle version',
    ],

    'filter' => [
        'all' => 'Tous',
        'search' => 'Recherche',
        'search_placeholder' => 'Rechercher dans les titres',
        'expiring' => 'Expire',
        'expiring_days' => 'sous :days jours',
    ],

    'ref' => [
        'customer' => 'Client',
        'project' => 'Projet',
        'diary' => 'Intervention',
        'asset' => 'Équipement',
        'none' => 'Sans référence',
    ],

    'badge' => [
        'current' => 'Actuelle',
        'expired' => 'Expiré',
        'expires_soon' => 'Expire bientôt',
    ],

    'flash' => [
        'created' => 'Le document a été créé.',
        'updated' => 'Le document a été mis à jour.',
        'deleted' => 'Le document a été supprimé.',
        'archived' => 'Le document a été archivé.',
        'version_added' => 'La version :no a été téléversée.',
    ],

    'error' => [
        'unknown_type' => 'Type de document inconnu.',
        'valid_until_before_from' => 'La fin de validité doit être postérieure à son début.',
    ],

    'hint' => [
        'upload' => 'Autorisé : PDF, images, fichiers Office, texte/CSV, ZIP — max. :mb Mo.',
    ],

    // Publication vers le portail client (vague D — miroir de documents).
    'customer' => [
        'section' => 'Publication client',
        'released' => 'Publié sur le portail client',
        'not_released' => 'Non publié',
        'released_at' => 'Publié le',
        'released_by' => 'Publié par',
        'badge' => 'Portail',
        'not_linked_hint' => 'Seuls les documents liés à un client ou à une mission peuvent être publiés.',
        'action' => [
            'release' => 'Publier sur le portail client',
            'revoke' => 'Retirer la publication',
        ],
        'confirm_revoke' => 'Retirer vraiment la publication sur le portail client ?',
        'flash' => [
            'released' => 'Le document a été publié sur le portail client.',
            'revoked' => 'La publication sur le portail client a été retirée.',
        ],
        'error' => [
            'not_linked' => 'Seuls les documents liés à un client ou à une mission peuvent être publiés.',
        ],
        'portal' => [
            'title' => 'Documents',
            'subtitle' => 'Les documents publiés pour vous.',
            'empty' => 'Aucun document n\'a encore été publié pour vous.',
        ],
    ],

    'empty' => 'Aucun document pour le moment.',
    'empty_title' => 'Aucun document trouvé',
    'empty_filtered' => 'Aucun document ne correspond aux filtres actuels.',
    'empty_versions' => 'Aucune version pour le moment.',
    'confirm_delete' => 'Supprimer vraiment ce document et toutes ses versions ?',
    'confirm_archive' => 'Archiver vraiment ce document ?',
];
