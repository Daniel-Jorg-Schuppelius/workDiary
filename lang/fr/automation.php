<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : automation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'trigger' => [
        'user_created' => 'Collaborateur créé',
        'asset_checked_out' => 'Équipement remis',
        'expense_submitted' => 'Note de frais soumise',
        'open_issue_created' => 'Point ouvert créé',
        'procedure_deviation_recorded' => 'Écart de procédure enregistré',
        'incoming_invoice_received' => 'Facture entrante reçue',
    ],
    'action' => [
        'learning_enroll' => 'Inscrire au cours',
        'expense_approve' => 'Approuver les frais',
        'diary_follow_up' => 'Créer une commande de suivi',
        'incoming_invoice_discard' => 'Rejeter et supprimer la facture entrante',
        'incoming_invoice_reject' => 'Classer la facture entrante comme « pas une facture »',
        'incoming_invoice_assign' => 'Attribuer la facture entrante',
    ],
    'form' => [
        'target' => 'Cible',
        'target_hint' => 'Uniquement pour les actions avec une cible, par exemple le cours d’une inscription.',
        'target_course' => 'Cours',
        'title' => 'Nouvelle règle d’automatisation',
        'trigger' => 'Déclencheur',
        'action' => 'Action',
        'action_hint' => 'L’action doit correspondre au déclencheur.',
        'conditions' => 'Conditions (JSON)',
        'conditions_hint' => 'Une condition vide {"all":[]} s’applique toujours. Exemples :',
    ],
    'error' => [
        'target_required' => 'Veuillez choisir une cible dans la liste.',
        'invalid_json' => 'Les conditions ne sont pas un objet JSON valide.',
        'action_trigger_mismatch' => 'Cette action ne correspond pas au déclencheur choisi.',
    ],
];
