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
    'extraction' => [
        'title' => 'Suggestions issues de « :document »',
        'check' => 'Les informations détectées sont préremplies. Veuillez les comparer au document ; elles ne sont reprises qu\'à l\'enregistrement.',
        'none' => 'Aucune information contractuelle n\'a été détectée dans le document (ou le texte n\'était pas lisible).',
        'create' => 'Contrat depuis le document',
        'leasing_create' => 'Dossier de leasing depuis le document',
        'field' => [
            'rate_amount' => 'Mensualité',
            'payment_rhythm' => 'Périodicité',
            'special_payment' => 'Paiement spécial',
            'residual_value' => 'Valeur résiduelle',
            'purchase_option_amount' => 'Option d\'achat',
            'starts_on' => 'Début',
            'ends_on' => 'Fin',
            'min_term_months' => 'Durée minimale',
            'notice_period_days' => 'Délai de résiliation (converti en jours)',
            'renew_period_months' => 'Reconduction',
            'auto_renew' => 'Reconduction automatique',
            'value_amount' => 'Valeur du contrat',
            'value_period' => 'Base de valeur',
        ],
    ],
    // Verbraucherpreisindex und Indexanpassung (MVP-952).
    'price_index' => [
        'title' => 'Indice des prix à la consommation',
        'subtitle' => 'IPC Allemagne (base 2020 = 100) issu de la série de la Bundesbank. Les valeurs nouvelles ou révisées ne s’appliquent qu’après validation.',
        'pending' => 'En attente de validation : :count valeurs',
        'add' => 'Ajouter une valeur',
        'approve' => 'Valider',
        'reject' => 'Rejeter',
        'empty' => 'Aucune valeur d’indice pour l’instant. L’import s’exécute chaque mois (contracts:price-index-sync).',
        'field' => [
            'period' => 'Mois',
            'value' => 'Valeur de l’indice',
            'source' => 'Source',
        ],
        'source' => [
            'bundesbank' => 'Bundesbank',
            'manual' => 'Saisie manuelle',
        ],
        'status' => [
            'pending' => 'Validation en attente',
            'approved' => 'Validé',
            'rejected' => 'Rejeté',
        ],
        'flash' => [
            'approved' => 'Valeur d’indice validée.',
            'rejected' => 'Valeur d’indice rejetée.',
            'saved' => 'Valeur d’indice enregistrée et validée.',
        ],
    ],
    'indexation' => [
        'title' => 'Indexation (IPC)',
        'configure' => 'Clause d’indexation',
        'check' => 'Vérifier maintenant',
        'save' => 'Enregistrer',
        'apply' => 'Appliquer',
        'dismiss' => 'Rejeter',
        'not_configured' => 'Aucun indice de base enregistré. Saisissez l’indice et le mois de base de la clause d’indexation.',
        'base_line' => 'Indice de base :value (:period)',
        'preview_line' => 'actuellement :value (:period), :change % → :amount :currency',
        'effective' => 'effectif à partir du :date',
        'confirm_apply' => 'Porter la valeur du contrat à :amount et mettre à jour l’indice de base ?',
        'superseded' => 'Remplacé par une valeur d’indice plus récente.',
        'notification' => 'Indexation proposée : :number',
        'disclaimer' => 'Le calcul suit les paramètres enregistrés. Il ne remplace pas l’examen de la clause d’indexation.',
        'section' => [
            'base' => 'Base et règle',
        ],
        'field' => [
            'base_value' => 'Indice de base',
            'base_period' => 'Mois de base',
            'threshold' => 'Seuil (%)',
            'pass_through' => 'Répercussion (%)',
            'index' => 'Indice',
            'change' => 'Variation',
            'old_amount' => 'Avant',
            'new_amount' => 'Nouveau',
        ],
        'hint' => [
            'base_value' => 'Niveau de l’indice à la signature ou au dernier ajustement, base 2020 = 100.',
            'threshold' => 'Ajustement seulement à partir de cette variation, vide = toute variation.',
            'pass_through' => 'Part de la variation répercutée, vide = 100 %.',
        ],
        'status' => [
            'proposed' => 'Proposé',
            'applied' => 'Appliqué',
            'dismissed' => 'Rejeté',
        ],
        'flash' => [
            'saved' => 'Clause d’indexation enregistrée.',
            'proposed' => 'Proposition d’ajustement créée.',
            'none' => 'Aucune proposition : pas de nouvelle valeur validée ou seuil non atteint.',
            'applied' => 'Indexation appliquée.',
            'dismissed' => 'Indexation rejetée.',
        ],
    ],
];
