<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : commission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Commissions',

    'page' => [
        'rules' => 'Règles de commission',
        'runs' => 'Décomptes de commissions',
    ],

    'subtitle' => [
        'index' => 'Lignes de commission par document. La base est la facture payée — jamais la facture émise.',
        'rules' => 'Taux par source de prospect, groupe de produits ou commercial. Une seule règle s’applique par document.',
        'runs' => 'Décompter une période : le brouillon est un aperçu, la clôture le fige. Ensuite, uniquement des reprises.',
    ],

    'section' => [
        'unassigned' => 'Factures payées sans commission',
        'per_user' => 'Totaux par commercial',
        'run_rows' => 'Lignes de commission du décompte',
    ],

    'group' => [
        'rule' => 'Règle',
        'validity' => 'Validité',
        'period' => 'Période',
    ],

    'action' => [
        'create_rule' => 'Créer une règle',
        'edit_rule' => 'Modifier la règle',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'save' => 'Enregistrer',
        'show' => 'Afficher',
        'export' => 'Export CSV',
        'close' => 'Clôturer le décompte',
        'back' => 'Retour',
        'assign' => 'Affecter un commercial',
        'create_run' => 'Créer un décompte',
        'to_rules' => 'Règles',
        'to_runs' => 'Décomptes',
        'to_commissions' => 'Lignes de commission',
    ],

    'field' => [
        'name' => 'Désignation',
        'scope' => 'Portée',
        'scope_value' => 'Valeur de portée',
        'user' => 'Commercial',
        'rate_percent' => 'Taux',
        'priority' => 'Priorité',
        'valid_from' => 'Valable à partir du',
        'valid_to' => 'Valable jusqu’au',
        'validity' => 'Validité',
        'is_active' => 'Actif',
        'note' => 'Note',
        'status' => 'Statut',
        'invoice' => 'Document',
        'customer' => 'Client',
        'earned_on' => 'Date de référence',
        'base_amount' => 'Assiette',
        'commission_amount' => 'Commission',
        'run' => 'Décompte',
        'period' => 'Période',
        'period_start' => 'Période du',
        'period_end' => 'Période au',
        'currency' => 'Devise',
        'entry_count' => 'Lignes',
        'total_base' => 'Total assiette',
        'total_commission' => 'Total commission',
        'closed_by' => 'Clôturé par',
        'paid_on' => 'Payée le',
    ],

    'scope' => [
        'all' => 'Tous les documents',
        'lead_source' => 'Source du prospect',
        'product_group' => 'Groupe de produits',
        'user' => 'Commercial',
    ],

    'status' => [
        'pending' => 'Ouverte',
        'settled' => 'Décomptée',
        'reversed' => 'Reprise',
    ],

    'run_status' => [
        'draft' => 'Brouillon',
        'closed' => 'Clôturé',
    ],

    'assignment' => [
        'lead' => 'Issu du pipeline de prospects',
        'manual' => 'Affecté manuellement',
    ],

    'badge' => [
        'reversal' => 'Reprise',
    ],

    'empty' => [
        'rules' => 'Aucune règle de commission définie.',
        'commissions' => 'Aucune ligne de commission.',
        'runs' => 'Aucun décompte créé.',
        'run_rows' => 'Aucune ligne de commission dans cette période.',
    ],

    'hint' => [
        'scope_value' => 'Uniquement pour la portée source de prospect ou groupe de produits ; la valeur doit correspondre à la portée choisie.',
        'user' => 'Uniquement pour la portée commercial.',
        'priority' => 'Le nombre le plus élevé l’emporte ; à égalité, la portée la plus étroite décide.',
        'period' => 'Libellé lisible, p. ex. 2026-08. Vide = déduit de la date de début.',
        'currency' => 'Un décompte traite exactement une devise — les commissions ne sont jamais converties.',
        'assign' => 'Laisser vide pour revenir à l’origine issue du pipeline de prospects.',
        'current_assignment' => 'Responsable actuel : :user (:source).',
        'no_assignment' => 'Personne n’est responsable — sans affectation, aucune commission n’est créée.',
        'unassigned' => 'Ces factures sont payées mais affectées à personne : ni manuellement ni via un prospect converti.',
        'draft_preview' => 'Brouillon : les lignes sont recalculées à chaque affichage. Seule la clôture les fige.',
        'no_payout' => 'WorkDiary calcule et exporte la commission — le versement se fait dans la paie.',
    ],

    'confirm' => [
        'delete_rule' => 'Supprimer la règle de commission ? Les commissions déjà calculées restent inchangées.',
        'delete_run' => 'Supprimer le brouillon du décompte ?',
        'close_run' => 'Clôturer le décompte ? Il sera figé ; les corrections passent uniquement par une reprise.',
    ],

    'flash' => [
        'rule_created' => 'Règle de commission créée.',
        'rule_updated' => 'Règle de commission enregistrée.',
        'rule_deleted' => 'Règle de commission supprimée.',
        'assigned' => 'Affectation enregistrée.',
        'run_created' => 'Décompte créé.',
        'run_closed' => 'Décompte clôturé et figé.',
        'run_deleted' => 'Décompte supprimé.',
    ],

    'error' => [
        'period_reversed' => 'La fin de la période précède son début.',
        'period_overlap' => 'Un décompte existe déjà pour cette période.',
        'already_closed' => 'Ce décompte est déjà clôturé.',
    ],

    'note' => [
        'credit_note' => 'Reprise en raison de l’avoir :number',
        'cancelled' => 'Reprise en raison de l’annulation',
        'reassigned' => 'Reprise en raison de la réaffectation du commercial',
    ],

    'export' => [
        'period' => 'Période',
        'user' => 'Commercial',
        'invoice' => 'Document',
        'customer' => 'Client',
        'earned_on' => 'Date de référence',
        'currency' => 'Devise',
        'base' => 'Assiette',
        'rate' => 'Taux en pourcentage',
        'commission' => 'Commission',
        'kind' => 'Type',
        'note' => 'Note',
        'reversal' => 'Reprise',
        'regular' => 'Commission',
    ],
];
