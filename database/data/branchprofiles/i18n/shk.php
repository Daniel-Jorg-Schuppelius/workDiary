<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „shk" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'reparatur' => ['en' => 'Repair', 'es' => 'Reparación', 'fr' => 'Réparation', 'it' => 'Riparazione'],
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'inbetriebnahme' => ['en' => 'Commissioning', 'es' => 'Puesta en marcha', 'fr' => 'Mise en service', 'it' => 'Messa in servizio'],
        'druckpruefung' => ['en' => 'Pressure test', 'es' => 'Prueba de presión', 'fr' => 'Essai de pression', 'it' => 'Prova di pressione'],
        'dichtheitspruefung' => ['en' => 'Leak test', 'es' => 'Prueba de estanqueidad', 'fr' => 'Essai d\'étanchéité', 'it' => 'Prova di tenuta'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
        'notdienst' => ['en' => 'Emergency service', 'es' => 'Servicio de urgencia', 'fr' => 'Service d\'urgence', 'it' => 'Servizio di emergenza'],
    ],
];
