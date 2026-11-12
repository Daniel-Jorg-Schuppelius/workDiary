<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfwesen: Bearbeiten, Deaktivieren, Storno, Roh-Messwerte (MVP-1575).
return [
    'action' => [
        'deactivate' => 'Désactiver',
        'activate' => 'Activer',
    ],
    'state' => [
        'inactive' => 'désactivée',
    ],
    'profile' => [
        'edit' => 'Modifier le profil de contrôle',
        'updated' => 'Profil de contrôle enregistré.',
        'activated' => 'Profil de contrôle activé.',
        'deactivated' => 'Profil de contrôle désactivé.',
        'deactivate_confirm' => 'Désactiver le profil de contrôle ? Il n\'est alors plus proposé pour de nouvelles affectations ; les obligations et rapports existants sont conservés.',
        'inactive_title' => 'Profils d\'organisation désactivés',
        'interval_hint' => 'S\'applique à toutes les obligations sans intervalle propre, à partir du prochain contrôle.',
    ],
    'requirement' => [
        'edit' => 'Modifier l\'exigence',
        'edit_hint' => 'Les limites modifiées s\'appliquent aux nouveaux contrôles ; les anciens rapports conservent leurs limites d\'origine.',
        'updated' => 'Exigence enregistrée.',
        'activated' => 'Exigence activée.',
        'deactivated' => 'Exigence désactivée.',
    ],
    'assignment' => [
        'edit' => 'Modifier l\'obligation de contrôle',
        'updated' => 'Obligation de contrôle enregistrée.',
        'activated' => 'Obligation de contrôle reprise.',
        'deactivated' => 'Obligation de contrôle désactivée.',
        'deactivate_confirm' => 'Désactiver l\'obligation de contrôle ? Les rendez-vous ouverts sont annulés et le blocage pour retard de cette obligation prend fin ; les rapports sont conservés.',
        'interval_override' => 'Intervalle pour cette ressource (mois)',
        'interval_override_hint' => 'Vide = intervalle du profil de contrôle (:months mois).',
        'next_due_on' => 'Prochaine échéance',
        'next_due_hint' => 'Vide = recalculer à partir du dernier contrôle si l\'intervalle change.',
        'inactive_title' => 'Obligations de contrôle désactivées',
        'show_active' => 'Afficher les actives',
        'show_inactive' => 'Désactivées (:count)',
        'block_released' => 'Obligation de contrôle modifiée ou désactivée.',
    ],
    'schedule' => [
        'cancel_title' => 'Annuler le rendez-vous de contrôle',
        'cancel_action' => 'Annuler',
        'cancel_hint' => 'Le rendez-vous reste dans la liste avec son motif (filtre « Annulé »). L\'obligation de contrôle elle-même continue.',
        'cancelled' => 'Rendez-vous de contrôle annulé.',
        'none_for_status' => 'Aucun rendez-vous de contrôle avec ce statut.',
        'cancelled_by_deactivation' => 'Obligation de contrôle désactivée.',
        'cancelled_by_decommission' => 'Ressource mise hors service.',
        'error' => [
            'not_cancellable' => 'Ce rendez-vous de contrôle a déjà été effectué ou annulé.',
        ],
    ],
    'measurement' => [
        'title' => 'Mesures brutes',
        'hint' => 'Série de mesures libre sans contrôle des limites ; les lignes sans valeur ne sont pas enregistrées.',
        'label' => 'Point de mesure',
        'value' => 'Valeur mesurée',
    ],
    'protocol' => [
        'title' => 'Rapports de contrôle (20 derniers)',
        'performed_at' => 'Effectué',
        'values' => 'Résultats et mesures',
        'out_of_limit' => 'hors limite',
        'empty' => 'Aucun contrôle documenté pour l\'instant.',
    ],
];
