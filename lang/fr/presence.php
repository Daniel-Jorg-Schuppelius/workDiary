<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : presence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669).
return [
    'board' => 'Présence actuelle',
    'kind' => [
        'do_not_disturb' => 'Ne pas déranger',
        'brief_away' => 'Brièvement absent',
        'out_of_office' => 'Hors du bureau',
    ],
    'until' => ':kind jusqu’à :time',
    'validation' => [
        'until_required' => 'Ce statut nécessite une fin.',
        'until_range' => 'La fin doit se situer dans le futur et au plus dans :hours heures.',
    ],
    'dialog' => [
        'title' => 'Mon statut',
        'submit' => 'Définir le statut',
        'hint' => 'Seul votre statut actuel est affiché – sans motif ni historique. Il prend fin à l’heure indiquée, lors du pointage de sortie ou lorsque vous le réinitialisez.',
        'current' => 'Actuel',
        'kind' => 'Statut',
        'duration' => 'Pendant',
        'no_duration' => 'Sans fin (jusqu’à réinitialisation ou pointage de sortie)',
        'until' => 'Ou exactement jusqu’à',
        'until_hint' => 'Prime sur « Pendant ». « Brièvement absent » et « Hors du bureau » nécessitent une fin.',
    ],
    'duration' => [
        '15' => '15 minutes',
        '30' => '30 minutes',
        '60' => '1 heure',
        '120' => '2 heures',
        '240' => '4 heures',
        '480' => '8 heures',
    ],
    'action' => [
        'set' => 'Mon statut',
        'clear' => 'Réinitialiser le statut',
    ],
    'flash' => [
        'set' => 'Votre statut « :status » est défini.',
        'cleared' => 'Votre statut a été réinitialisé.',
    ],
];
