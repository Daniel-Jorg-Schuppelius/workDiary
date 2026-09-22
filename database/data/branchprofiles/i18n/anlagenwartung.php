<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : anlagenwartung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „anlagenwartung" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'inspektion' => ['en' => 'Inspection', 'es' => 'Inspección', 'fr' => 'Inspection', 'it' => 'Ispezione'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'inbetriebnahme' => ['en' => 'Commissioning', 'es' => 'Puesta en marcha', 'fr' => 'Mise en service', 'it' => 'Messa in servizio'],
        'kalibrierung' => ['en' => 'Calibration', 'es' => 'Calibración', 'fr' => 'Étalonnage', 'it' => 'Calibrazione'],
        'stillstand' => ['en' => 'Downtime', 'es' => 'Parada', 'fr' => 'Arrêt', 'it' => 'Fermo macchina'],
        'ersatzteil' => ['en' => 'Spare part', 'es' => 'Repuesto', 'fr' => 'Pièce de rechange', 'it' => 'Ricambio'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
    ],
];
