<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstalter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „veranstalter" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'briefing' => ['en' => 'Client briefing', 'es' => 'Briefing del cliente', 'fr' => 'Briefing client', 'it' => 'Briefing del cliente'],
        'konzept' => ['en' => 'Concept', 'es' => 'Concepto', 'fr' => 'Concept', 'it' => 'Concetto'],
        'budgetierung' => ['en' => 'Budgeting', 'es' => 'Presupuestación', 'fr' => 'Budgétisation', 'it' => 'Budgeting'],
        'locationScouting' => ['en' => 'Location scouting', 'es' => 'Búsqueda de ubicación', 'fr' => 'Repérage de lieu', 'it' => 'Ricerca location'],
        'genehmigung' => ['en' => 'Permits', 'es' => 'Permisos', 'fr' => 'Autorisations', 'it' => 'Autorizzazioni'],
        'dienstleisterBuchung' => ['en' => 'Vendor booking', 'es' => 'Contratación de proveedores', 'fr' => 'Réservation de prestataires', 'it' => 'Prenotazione fornitori'],
        'ticketing' => ['en' => 'Ticketing', 'es' => 'Venta de entradas', 'fr' => 'Billetterie', 'it' => 'Biglietteria'],
        'aufbauKoordination' => ['en' => 'Setup coordination', 'es' => 'Coordinación del montaje', 'fr' => 'Coordination du montage', 'it' => 'Coordinamento allestimento'],
        'durchfuehrung' => ['en' => 'Execution', 'es' => 'Ejecución', 'fr' => 'Déroulement', 'it' => 'Svolgimento'],
        'abbauKoordination' => ['en' => 'Teardown coordination', 'es' => 'Coordinación del desmontaje', 'fr' => 'Coordination du démontage', 'it' => 'Coordinamento smontaggio'],
        'nachbereitung' => ['en' => 'Follow-up / settlement', 'es' => 'Cierre / liquidación', 'fr' => 'Suivi / décompte', 'it' => 'Chiusura / rendiconto'],
        'zwischenfall' => ['en' => 'Incident', 'es' => 'Incidente', 'fr' => 'Incident', 'it' => 'Incidente'],
    ],
];
