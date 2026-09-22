<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gebaeudereinigung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „gebaeudereinigung" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'unterhaltsreinigung' => ['en' => 'Routine cleaning', 'es' => 'Limpieza de mantenimiento', 'fr' => 'Nettoyage d\'entretien', 'it' => 'Pulizia ordinaria'],
        'grundreinigung' => ['en' => 'Deep cleaning', 'es' => 'Limpieza a fondo', 'fr' => 'Nettoyage en profondeur', 'it' => 'Pulizia di fondo'],
        'glasreinigung' => ['en' => 'Window cleaning', 'es' => 'Limpieza de cristales', 'fr' => 'Nettoyage des vitres', 'it' => 'Pulizia vetri'],
        'sonderreinigung' => ['en' => 'Special cleaning', 'es' => 'Limpieza especial', 'fr' => 'Nettoyage spécial', 'it' => 'Pulizia speciale'],
        'qualitaetskontrolle' => ['en' => 'Quality check', 'es' => 'Control de calidad', 'fr' => 'Contrôle qualité', 'it' => 'Controllo qualità'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'begehung' => ['en' => 'Walk-through', 'es' => 'Inspección', 'fr' => 'Visite', 'it' => 'Sopralluogo'],
    ],
];
