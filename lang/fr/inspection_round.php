<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_round.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfmittelrunden (Feature 075, MVP-899).
return [
    'nav' => 'Tournées de contrôle',
    'title' => 'Tournées de contrôle',
    'subtitle' => 'Liste des contrôles à échéance d\'un site ou d\'un groupe — scannez l\'objet, saisissez le résultat.',
    'open' => 'Créer une tournée',
    'show' => 'Ouvrir la tournée',
    'name' => 'Désignation',
    'due_until' => 'Échéance jusqu\'au',
    'progress' => 'Effectués',
    'status' => 'Statut',
    'none_title' => 'Aucune tournée de contrôle',
    'none' => 'Créez une tournée pour un site ou un groupe.',
    'location' => 'Site',
    'category' => 'Groupe (catégorie)',
    'profile' => 'Profil de contrôle',
    'customer' => 'Client',
    'any' => 'Tous',
    'form_hint' => 'La tournée reprend toutes les obligations de contrôle actives échues à cette date. Les échéances ultérieures ne sont pas ajoutées.',
    'empty' => 'Aucun contrôle n\'est dû à cette date pour cette sélection.',
    'opened' => 'Tournée créée avec :count contrôles.',
    'scan' => 'Scanner l\'objet',
    'scan_submit' => 'Ouvrir',
    'scan_unknown' => 'Code d\'objet inconnu.',
    'scan_not_in_round' => '« :asset » ne fait pas partie de cette tournée.',
    'scan_done' => 'Tous les contrôles de cet objet sont effectués dans la tournée.',
    'scan_several' => 'Plusieurs contrôles sont ouverts pour cet objet :',
    'kpi_done' => 'Effectués',
    'kpi_missing' => 'Manquants',
    'kpi_overdue' => 'En retard',
    'asset' => 'Objet',
    'due_on' => 'Échéance',
    'overdue' => 'En retard',
    'pending' => 'Ouvert',
    'capture' => 'Saisir le contrôle',
    'capture_submit' => 'Enregistrer le contrôle',
    'result' => 'Résultat',
    'note' => 'Remarque',
    'signature_name' => 'Signature (nom)',
    'certificate_hint' => 'Ce profil exige un certificat pour « réussi ». Saisissez alors le contrôle dans le calendrier des contrôles.',
    'recorded' => 'Contrôle documenté.',
    'close' => 'Clôturer la tournée',
    'confirm_close' => 'Clôturer la tournée ? :missing contrôles sont encore ouverts et restent manquants.',
    'closed' => 'La tournée est clôturée.',
    'closed_flash' => 'Tournée clôturée.',
    'already_done' => 'Ce contrôle est déjà saisi dans la tournée.',
];
