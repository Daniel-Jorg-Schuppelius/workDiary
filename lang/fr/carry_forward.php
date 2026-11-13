<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carry_forward.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Merker für die nächste Periode (Feature 201, MVP-1645): Komponente <x-carry-forward-notes>.
return [
    'title' => 'Mémos pour cette fois',
    'empty' => [
        'title' => 'Aucun mémo ouvert',
        'message' => 'Rien n’a été laissé pour :period lors des périodes précédentes.',
    ],
    'open_heading' => 'Mémos ouverts des périodes précédentes',
    'left_by' => 'Laissé par :name en :period, valable à partir de :from.',
    'resolution_label' => 'Remarque en cochant',
    'resolution_placeholder' => 'Courte remarque (facultative), p. ex. « attestation demandée »',
    'resolve' => 'Cocher comme fait',
    'resolved_heading' => 'Mémos cochés ici',
    'resolved_by' => 'Coché par :name le :date',
    'left_heading' => 'Laissé pour plus tard',
    'from_period' => 'à partir de :period',
    'withdraw' => 'Retirer le mémo',
    'withdraw_confirm' => 'Voulez-vous vraiment retirer ce mémo ? Il n’apparaîtra plus la prochaine fois.',
    'new_label' => 'Mémo pour la prochaine fois',
    'new_hint' => 'Apparaît en haut lors de la prochaine occurrence de cette série jusqu’à ce que quelqu’un le coche. Les autres notes de ce dossier ne sont pas reprises.',
    'target_label' => 'Valable',
    'create' => 'Laisser un mémo',
    'help' => 'Comment fonctionnent les mémos ?',
    // Cockpit von Kunde, Lieferant, Projekt und Asset (MVP-1646).
    'cockpit' => [
        'summary' => 'Les mémos ouverts n’ont pas été cochés ; ils apparaissent à la prochaine occurrence de la série à partir de leur période cible.',
        'contracts' => 'Mémos sur les obligations contractuelles',
        'inspections' => 'Mémos sur les obligations de contrôle',
        'recurring_orders' => 'Mémos sur les commandes récurrentes',
        'period_orders' => 'Mémos sur les commandes avec période',
    ],
    'flash' => [
        'created' => 'Mémo pour la prochaine fois enregistré.',
        'resolved' => 'Mémo coché comme fait.',
        'withdrawn' => 'Mémo retiré.',
    ],
    'error' => [
        'no_series' => 'Ce dossier n’appartient à aucune série avec période ; un mémo n’est pas possible ici.',
        'target_not_after' => 'Un mémo est valable au plus tôt à partir de la période suivante.',
        'empty' => 'Veuillez saisir un texte pour le mémo.',
        'foreign_series' => 'Ce mémo n’appartient pas à cette série.',
        'already_resolved' => 'Ce mémo est déjà coché.',
        'not_due' => 'Ce mémo n’est valable qu’à partir d’une période ultérieure.',
    ],
];
