<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scorecard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Traductions (fr) — Scorecards de performance fournisseur (Bauturbo vague D).
 * Structure de référence : lang/de/scorecard.php
 */

return [
    'title' => 'Scorecards fournisseurs',
    'overview_subtitle' => 'Classement par score global issu des achats, du stock, des réclamations et de l’ISMS (définition v:version).',
    'apply' => 'Afficher',
    'weights_hint' => 'Pondération du score global : ponctualité :ontime %, taux de réclamation :complaints %, qualité ISMS :quality %, évolution des prix :price %. Les indicateurs indisponibles sont ignorés et les poids re-normalisés.',
    'empty_ranking' => 'Aucun fournisseur avec des données d’achat, de réclamation ou ISMS exploitables sur la période.',

    'chart_ranking' => 'Score global par fournisseur (top 15)',
    'unit_score' => 'Score',

    'col_supplier' => 'Fournisseur',
    'col_overall' => 'Score global',
    'no_data' => 'aucune donnée',
    'open_detail' => 'Ouvrir le détail',

    'metric_ontime' => 'Ponctualité',
    'metric_complaints' => 'Taux de réclamation',
    'metric_price' => 'Évolution des prix',
    'metric_quality' => 'Qualité ISMS',

    'detail_subtitle' => 'Scorecard issue des achats/stock/réclamations/ISMS (définition v:version) · :label',
    'back_to_ranking' => 'Retour au classement',
    'supplier_master' => 'Fiche fournisseur',
    'overall_title' => 'Score global',
    'overall_hint' => 'Synthèse pondérée des indicateurs disponibles (0–100, plus élevé est meilleur).',
    'goodness' => 'Score :g',

    'ontime_no_source' => 'Aucune réception de marchandises comptabilisée avec date de livraison promise sur la période.',
    'ontime_detail' => ':on livraisons ponctuelles sur :total.',
    'complaints_no_source' => 'Aucune commande comme base sur la période.',
    'complaints_detail' => ':count réclamations pour :base commandes.',
    'price_no_source' => 'Aucun article avec au moins deux points de prix sur la période.',
    'price_dir_up' => 'Prix d’achat en hausse.',
    'price_dir_down' => 'Prix d’achat en baisse.',
    'price_dir_flat' => 'Prix d’achat stables.',
    'price_dir_none' => 'Aucune évolution des prix.',
    'quality_no_source' => 'Aucune évaluation fournisseur ISMS disponible.',
    'quality_detail' => 'Classification de risque ISMS actuelle.',

    'drill_deliveries' => 'Réceptions & échéances',
    'drill_claims' => 'Réclamations',
    'drill_prices' => 'Historique des prix',

    'chart_ontime' => 'Évolution de la ponctualité',
    'chart_price_index' => 'Indice de prix (base 100)',
    'chart_complaints' => 'Réclamations par mois',
    'unit_percent' => 'Pour cent',
    'unit_index' => 'Indice',
    'unit_count' => 'Nombre',
    'axis_month' => 'Mois',

    'price_articles' => 'Évolution des prix par article',
    'col_article' => 'Article',
    'col_first_price' => 'Premier prix',
    'col_last_price' => 'Dernier prix',
    'col_change' => 'Variation',

    'col_order' => 'Commande',
    'col_expected' => 'Promis',
    'col_delivered' => 'Livré',
    'col_ontime_flag' => 'Échéance',
    'pending' => 'ouvert',
    'on_time' => 'à temps',
    'late' => 'en retard',

    'col_claim' => 'Réclamation',
    'col_title' => 'Titre',
    'col_reported' => 'Signalé',
    'col_status' => 'Statut',
    'col_ordered_at' => 'Date de commande',
    'col_unit_price' => 'Prix unitaire',
];
