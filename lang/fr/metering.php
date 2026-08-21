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
