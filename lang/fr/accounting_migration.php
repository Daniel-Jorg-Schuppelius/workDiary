<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting_migration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Buchhaltungswechsel (Feature 008/045/077, MVP-653).
return [
    'title' => 'Changement de logiciel comptable',
    'intro' => 'Planifier le changement de logiciel comptable, le vérifier en simulation, le sécuriser en double exploitation, basculer à la date de bascule et le clôturer avec un justificatif. WorkDiary rattache les deux systèmes externes aux mêmes objets locaux — les pièces finalisées ne sont jamais reconstruites.',
    'plan_heading' => 'Planifier le changement',
    'plan_hint' => 'Un seul changement par organisation à la fois. L’analyse n’écrit dans aucun système externe.',
    'areas' => 'Domaines de données',
    'read_only' => 'historique seulement',
    'cutover_on' => 'Date de bascule',
    'cutover_hint' => 'À partir de ce jour, les nouvelles pièces de facturation naissent exclusivement dans le système cible ; le système source est bloqué pour cela.',
    'plan_submit' => 'Planifier le changement',
    'no_cutover' => 'pas encore définie',
    'dry_run_badge' => 'Simulation',
    'run_heading' => 'Changement :source → :target',
    'analyze' => 'Analyse (simulation)',
    'start_parallel' => 'Démarrer la double exploitation',
    'cutover' => 'Basculer',
    'cutover_confirm' => 'Basculer maintenant ? À partir de la date de bascule, les nouvelles pièces naissent exclusivement dans le système cible ; l’envoi vers la source est bloqué.',
    'complete' => 'Clôturer',
    'report' => 'Protocole (CSV)',
    'cancel' => 'Annuler',
    'cancel_confirm' => 'Annuler vraiment le changement ? Les décisions déjà prises sont conservées comme justificatif.',
    'blockers_heading' => 'Points ouverts',
    'counters_heading' => 'Compteurs',
    'area' => 'Domaine',
    'counter_read' => 'lus',
    'counter_matched' => 'rattachés',
    'counter_pending' => 'ouverts',
    'counter_conflict' => 'conflits',
    'items_heading' => 'Enregistrements',
    'item_title' => 'Libellé',
    'item_source' => 'Source',
    'item_target' => 'Cible',
    'item_status' => 'Statut',
    'item_decision' => 'Décision',
    'history_heading' => 'Changements précédents',
    'status.pending' => 'ouvert',
    'status.matched' => 'rattaché',
    'status.transferred' => 'transféré',
    'status.conflict' => 'conflit',
    'status.skipped' => 'ignoré',
    'status.historic' => 'historique',
    'status.failed' => 'en erreur',
    'source' => 'Système source',
    'target' => 'Système cible',
];
