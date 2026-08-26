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
];
