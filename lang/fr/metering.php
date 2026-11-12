<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zählerstands-Faktura (Feature 116, MVP-605).
return [
    'title' => 'Facturation au compteur',
    'subtitle' => 'Facturation à la consommation par client et appareil à partir des relevés',
    'empty' => 'Aucune convention enregistrée.',
    'created' => 'Convention enregistrée.',
    'updated' => 'Convention mise à jour.',
    'draft_notice' => 'Le traitement ne crée que des brouillons de facture — la vérification et l’émission restent manuelles.',
    'blocked_external' => 'Un système externe gère la facturation de ce client — aucun document n’est créé.',
    'run_done' => 'Facturé : :created brouillon(s), :skipped ignoré(s).',
    'form_hint' => 'Sans relevé de fin sur la période, aucun brouillon n’est créé, seulement une alerte — rien n’est estimé.',
    'not_runnable' => 'Cet accord n’est pas facturé (statut : :status). Pour le facturer, passez le statut à « Actif ».',
    'project_hint' => 'Les brouillons de facture du traitement sont rattachés à ce projet.',
    'status_hint' => 'Le traitement ignore les accords en pause et terminés. Après une pause, il rattrape à partir de « Prochaine facturation » — avancez la date si nécessaire.',
    'tiers' => [
        'legend' => 'Barème par tranches',
        'hint' => 'Facultatif. Avec un barème, le prix de chaque tranche s’applique à la place du prix unitaire ; le calcul est progressif sur la consommation après déduction de la franchise. La première tranche commence à 0, les lignes vides sont ignorées.',
        'from' => 'Tranche :n à partir de la quantité',
        'price' => 'Tranche :n prix par unité',
        'badge' => 'Barème (:count tranches)',
        'badge_title' => 'Un barème par tranches s’applique à la place du prix unitaire.',
        'first_from_zero' => 'La première tranche doit commencer à 0, sinon une partie de la consommation ne serait pas facturée.',
        'duplicate_from' => 'Chaque tranche doit avoir sa propre quantité « à partir de ».',
    ],
    'unit_default' => 'unités',
    'action' => [
        'create' => 'Enregistrer une convention',
        'edit' => 'Modifier la convention',
        'run' => 'Facturer maintenant',
    ],
    'column' => [
        'title' => 'Intitulé',
        'customer' => 'Client',
        'asset' => 'Appareil',
        'base_price' => 'Prix de base',
        'unit_price' => 'Prix unitaire',
        'free_units' => 'Quantité incluse',
        'unit' => 'Unité',
        'interval' => 'Périodicité',
        'interval_count' => 'Facteur',
        'next_run_on' => 'Prochaine facturation',
        'end_on' => 'Fin',
        'project' => 'Projet',
        'status' => 'Statut',
    ],
    'interval' => [
        'monthly' => 'mensuelle',
        'quarterly' => 'trimestrielle',
        'yearly' => 'annuelle',
    ],
    'status' => [
        'active' => 'Active',
        'paused' => 'En pause',
        'ended' => 'Terminée',
    ],
    'skipped' => [
        'heading' => 'Facturations ignorées',
        'hint' => 'Sans relevé, pas de facture. Ajouter le relevé puis refacturer.',
        'reason' => [
            'missing_start_reading' => 'Pas de relevé initial avant la période',
            'missing_end_reading' => 'Aucun relevé dans la période',
            'negative_consumption' => 'Consommation négative (changement de compteur ?)',
            'nothing_to_bill' => 'Ni consommation ni prix de base',
        ],
    ],
    'line' => [
        'base' => ':title — prix de base du :from au :to',
        'usage' => ':title — consommation :consumption :unit, dont :free incluses',
        'estimated' => '(relevé estimé)',
    ],
];
