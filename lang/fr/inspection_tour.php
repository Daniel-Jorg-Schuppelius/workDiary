<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfertouren (MVP-918).
return [
    'title' => 'Tournée d\'inspection',
    'subtitle' => 'Planifier les inspections échues d\'un inspecteur en tournée : chaque rendez-vous devient une intervention sur le site de l\'équipement, la planification optimise l\'ordre.',
    'inspector' => 'Inspecteur',
    'due_until' => 'Échéance jusqu\'au',
    'date' => 'Date de la tournée',
    'select' => 'Sélectionner',
    'location' => 'Emplacement',
    'no_coordinates' => 'sans coordonnées',
    'no_coordinates_hint' => 'Sans coordonnées sur l\'équipement, l\'arrêt reste dans la tournée mais n\'entre pas dans le calcul de l\'itinéraire.',
    'none' => 'Aucune inspection ouverte pour cet inspecteur sur la période.',
    'none_selected' => 'Veuillez sélectionner au moins une inspection ouverte.',
    'unavailable' => 'La planification des tournées n\'est pas active pour votre organisation ; les tournées d\'inspection nécessitent le module Planification.',
    'plan' => 'Planifier la tournée',
    'planned' => ':count inspections planifiées en tournée.',
    'entry_title' => 'Inspection :profile – :asset',
    'entry_content' => 'Inspection issue de la tournée, échéance le :due.',
];
