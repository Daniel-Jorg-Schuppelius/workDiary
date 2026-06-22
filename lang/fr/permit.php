<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Autorisations',
    'subtitle' => 'Autorisations administratives pour les événements – statut, délais et justificatifs.',
    'label' => 'Autorisation',
    'create' => 'Ajouter une autorisation',
    'edit' => 'Modifier l’autorisation',
    'delete_confirm' => 'Supprimer définitivement cette autorisation ?',

    'sections' => [
        'base' => 'Informations',
        'dates' => 'Délais',
    ],

    'fields' => [
        'title' => 'Intitulé',
        'status' => 'Statut',
        'event' => 'Événement',
        'event_none' => '— aucun —',
        'permit_type' => 'Type d’autorisation',
        'authority' => 'Autorité',
        'reference_no' => 'Référence',
        'applied_at' => 'Demandée le',
        'valid_from' => 'Valable à partir du',
        'valid_until' => 'Valable jusqu’au / délai',
        'notes' => 'Notes',
        'evidence' => 'Justificatif',
    ],

    'filter' => [
        'all_status' => 'Tous les statuts',
    ],

    'status' => [
        'required' => 'Requise',
        'applied' => 'Demandée',
        'granted' => 'Accordée',
        'rejected' => 'Refusée',
        'expired' => 'Expirée',
    ],

    'messages' => [
        'created' => 'Autorisation créée.',
        'updated' => 'Autorisation mise à jour.',
        'deleted' => 'Autorisation supprimée.',
    ],

    'evidence' => [
        'upload' => 'Téléverser le justificatif',
        'replace' => 'Remplacer le justificatif',
        'replace_hint' => 'Un nouveau téléversement remplace le justificatif existant.',
        'hint' => 'Autorisé : PDF, JPG, PNG, DOCX (max. 25 Mo).',
        'remove' => 'Supprimer le justificatif',
        'remove_confirm' => 'Supprimer définitivement le justificatif ?',
        'too_large' => 'Le fichier est trop volumineux (max. 25 Mo).',
        'invalid_type' => 'Type de fichier non autorisé (PDF, JPG, PNG, DOCX).',
    ],
];
