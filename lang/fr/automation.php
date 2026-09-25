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
        'expense_submitted' => 'Note de frais soumise',
        'open_issue_created' => 'Point ouvert créé',
        'procedure_deviation_recorded' => 'Écart de procédure enregistré',
    ],
    'action' => [
        'expense_approve' => 'Approuver les frais',
        'diary_follow_up' => 'Créer une commande de suivi',
    ],
    'form' => [
        'title' => 'Nouvelle règle d’automatisation',
        'trigger' => 'Déclencheur',
        'action' => 'Action',
        'action_hint' => 'L’action doit correspondre au déclencheur.',
        'conditions' => 'Conditions (JSON)',
        'conditions_hint' => 'Une condition vide {"all":[]} s’applique toujours. Exemples :',
    ],
    'error' => [
        'invalid_json' => 'Les conditions ne sont pas un objet JSON valide.',
        'action_trigger_mismatch' => 'Cette action ne correspond pas au déclencheur choisi.',
    ],
];
