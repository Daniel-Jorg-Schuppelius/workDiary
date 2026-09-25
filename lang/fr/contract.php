<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'template' => [
        'title' => 'Modèles de contrat',
        'subtitle' => 'Les modèles sont créés à partir d’un contrat via « Enregistrer comme modèle » ou d’un profil sectoriel.',
        'name' => 'Nom',
        'obligations' => 'Obligations',
        'active' => 'Actif',
        'edit' => 'Modifier le modèle',
        'delete' => 'Supprimer le modèle',
        'confirm_delete' => 'Supprimer le modèle « :name » ? Les contrats existants restent inchangés.',
        'empty_title' => 'Aucun modèle de contrat',
        'empty' => 'Ouvrez un contrat et choisissez « Enregistrer comme modèle ».',
        'use' => 'Modèle :',
        'save_title' => 'Enregistrer comme modèle',
        'save' => 'Enregistrer le modèle',
        'save_hint' => 'Sont repris : type de contrat, titre, durée, résiliation, reconduction, base de valeur, règle d’indexation et obligations (échéance relative au début du contrat). Le partenaire, les montants et les dates ne le sont pas.',
        'flash' => [
            'created' => 'Modèle « :name » enregistré.',
            'updated' => 'Modèle enregistré.',
            'deleted' => 'Modèle supprimé.',
        ],
    ],
    'cost_center' => [
        'title' => 'Valeurs des contrats par centre de coûts',
        'subtitle' => 'Contrats en cours (actifs ou résiliés mais pas encore terminés) ; valeurs récurrentes ramenées à l’année et au mois, valeurs uniques à part. Vue prévisionnelle, sans écriture.',
        'field' => 'Centre de coûts',
        'count' => 'Contrats',
        'yearly' => 'Annuel',
        'monthly' => 'Mensuel',
        'once' => 'Unique',
        'none' => 'Sans centre de coûts',
        'empty' => 'Aucun contrat en cours.',
    ],
];
