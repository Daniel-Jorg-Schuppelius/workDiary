<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'section' => 'SMSI',
        'risks' => 'Registre des risques',
        'controls' => 'Catalogue de mesures',
        'soa' => 'DdA',
    ],

    'subtitle' => [
        'risks' => 'Identifier, évaluer (5×5) et traiter les risques de sécurité de l’information.',
        'controls' => 'Gérer les mesures et documenter la déclaration d’applicabilité par mesure.',
    ],

    'field' => [
        'risk_no' => 'N°',
        'title' => 'Titre',
        'description' => 'Description',
        'category' => 'Catégorie',
        'asset_ref' => 'Référence (système/processus/site)',
        'threat' => 'Menace',
        'likelihood' => 'Probabilité',
        'impact' => 'Impact',
        'score' => 'Score',
        'treatment' => 'Traitement',
        'status' => 'Statut',
        'owner' => 'Responsable',
        'review_due_on' => 'Revue prévue',
        'controls' => 'Mesures liées',
        'code' => 'Code',
        'source' => 'Source',
        'applicable' => 'Applicable',
        'justification' => 'Justification',
        'implementation_status' => 'Statut de mise en œuvre',
        'evidence_note' => 'Note de preuve',
        'risks' => 'Risques liés',
    ],

    'group' => [
        'risk' => 'Risque',
        'assessment' => 'Évaluation et traitement',
        'control' => 'Mesure',
        'soa' => 'Déclaration d’applicabilité',
    ],

    'action' => [
        'create_risk' => 'Ajouter un risque',
        'edit_risk' => 'Modifier le risque',
        'create_control' => 'Ajouter une mesure',
        'edit_control' => 'Modifier la mesure',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'transition' => 'Changer le statut',
        'import_catalog' => 'Charger le catalogue Annexe A',
        'back' => 'Retour',
        'print' => 'Imprimer / enregistrer en PDF',
    ],

    'filter' => [
        'all' => 'Tous',
        'sort' => 'Tri',
        'sort_score' => 'Score le plus élevé d’abord',
        'sort_review' => 'Date de revue',
        'sort_newest' => 'Plus récents d’abord',
        'applicable_yes' => 'Applicable',
        'applicable_no' => 'Non applicable',
    ],

    'scale' => [
        'likelihood' => [
            1 => 'très rare',
            2 => 'rare',
            3 => 'possible',
            4 => 'probable',
            5 => 'très probable',
        ],
        'impact' => [
            1 => 'négligeable',
            2 => 'faible',
            3 => 'notable',
            4 => 'grave',
            5 => 'critique',
        ],
    ],

    'matrix' => [
        'title' => 'Matrice des risques (risques ouverts)',
        'cell' => 'Probabilité :likelihood × impact :impact — :count risque(s)',
        'axes' => 'Lignes : probabilité (1–5) · Colonnes : impact (1–5)',
        'legend' => 'Légende',
        'low' => 'Faible (score ≤ 6)',
        'medium' => 'Moyen (score 7–12)',
        'high' => 'Élevé (score > 12)',
        'review_due' => '{1} 1 revue à faire|[2,*] :count revues à faire',
    ],

    'hint' => [
        'asset_ref' => 'p. ex. système ERP, salle serveur, centre de données …',
        'threat' => 'Quelle menace/vulnérabilité est en cause ?',
        'controls' => 'Sélection multiple (maintenir Ctrl/Cmd)',
        'no_controls_yet' => 'Aucune mesure pour l’instant — chargez d’abord le catalogue Annexe A ou créez vos propres mesures.',
        'code' => 'p. ex. M-01 (mesure propre)',
        'justification' => 'obligatoire si non applicable',
        'evidence_note' => 'Référence à une preuve/un document',
    ],

    'flash' => [
        'risk_created' => 'Le risque a été ajouté.',
        'risk_updated' => 'Le risque a été mis à jour.',
        'risk_transitioned' => 'Le statut du risque a été modifié.',
        'risk_deleted' => 'Le risque a été supprimé.',
        'control_created' => 'La mesure a été ajoutée.',
        'control_updated' => 'La mesure a été mise à jour.',
        'control_deleted' => 'La mesure a été supprimée.',
        'catalog_imported' => 'Catalogue Annexe A chargé (:count nouvelles mesures).',
    ],

    'error' => [
        'invalid_transition' => 'Le changement de statut de « :from » vers « :to » n’est pas autorisé.',
        'justification_required' => 'Une justification DdA est requise pour les mesures non applicables.',
    ],

    'soa' => [
        'document_title' => 'Déclaration d’applicabilité',
        'heading' => 'Déclaration d’applicabilité (DdA)',
        'generated_at' => 'État au',
        'control_count' => ':count mesures',
        'yes' => 'Oui',
        'no' => 'Non',
        'disclaimer' => 'Référence : ISO/IEC 27001:2022 Annexe A (codes et titres courts propres uniquement — pas de textes normatifs). L’évaluation de conformité relève d’un organisme de certification indépendant.',
    ],

    'empty_risks' => 'Aucun risque enregistré pour l’instant.',
    'empty_risks_title' => 'Aucun risque trouvé',
    'empty_controls' => 'Aucune mesure pour l’instant.',
    'empty_controls_title' => 'Aucune mesure trouvée',
    'empty_controls_hint_catalog' => 'Aucune mesure pour l’instant — utilisez « Charger le catalogue Annexe A » pour importer le catalogue de référence ISO/IEC 27001 (93 mesures).',
    'empty_controls_linked' => 'Aucune mesure liée.',
    'empty_filtered' => 'Aucune entrée trouvée pour les filtres actuels.',
    'confirm_delete_risk' => 'Supprimer vraiment ce risque ?',
    'confirm_delete_control' => 'Supprimer vraiment cette mesure ? Les liens vers les risques seront retirés.',
    'confirm_import_catalog' => 'Charger le catalogue de référence ISO/IEC 27001:2022 Annexe A (93 mesures, code + titre court uniquement) dans cette organisation ? Les mesures existantes restent inchangées.',
];
