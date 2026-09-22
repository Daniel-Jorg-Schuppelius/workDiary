<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kfz-fuhrparkservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „kfz-fuhrparkservice" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'annahme' => ['en' => 'Vehicle check-in', 'es' => 'Recepción del vehículo', 'fr' => 'Prise en charge du véhicule', 'it' => 'Accettazione veicolo'],
        'wartung' => ['en' => 'Maintenance/service', 'es' => 'Mantenimiento/servicio', 'fr' => 'Entretien/révision', 'it' => 'Manutenzione/tagliando'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'diagnose' => ['en' => 'Diagnosis', 'es' => 'Diagnóstico', 'fr' => 'Diagnostic', 'it' => 'Diagnosi'],
        'reifenwechsel' => ['en' => 'Tyre change', 'es' => 'Cambio de neumáticos', 'fr' => 'Changement de pneus', 'it' => 'Cambio pneumatici'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Sinistre', 'it' => 'Danno'],
        'huAu' => ['en' => 'Roadworthiness test prep', 'es' => 'Preparación ITV', 'fr' => 'Préparation contrôle technique', 'it' => 'Preparazione revisione'],
        'uebergabe' => ['en' => 'Vehicle handover', 'es' => 'Entrega del vehículo', 'fr' => 'Remise du véhicule', 'it' => 'Consegna veicolo'],
        'rueckgabe' => ['en' => 'Vehicle return', 'es' => 'Devolución del vehículo', 'fr' => 'Restitution du véhicule', 'it' => 'Restituzione veicolo'],
        'nachkalkulation' => ['en' => 'Post-calculation', 'es' => 'Cálculo posterior', 'fr' => 'Calcul a posteriori', 'it' => 'Consuntivo'],
    ],
];
