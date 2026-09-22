<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : handwerk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „handwerk" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'service' => ['en' => 'Service', 'es' => 'Servicio', 'fr' => 'Service', 'it' => 'Servizio'],
        'maintenance' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'repair' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'inspection' => ['en' => 'Inspection', 'es' => 'Inspección', 'fr' => 'Inspection', 'it' => 'Ispezione'],
        'advice' => ['en' => 'Consulting', 'es' => 'Asesoramiento', 'fr' => 'Conseil', 'it' => 'Consulenza'],
        'aufmass' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Métré', 'it' => 'Misurazione'],
    ],
];
