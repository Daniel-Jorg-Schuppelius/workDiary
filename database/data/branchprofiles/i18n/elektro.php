<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : elektro.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „elektro" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'installation' => ['en' => 'Installation', 'es' => 'Instalación', 'fr' => 'Installation', 'it' => 'Installazione'],
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'stoerung' => ['en' => 'Fault', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'pruefung' => ['en' => 'Test', 'es' => 'Comprobación', 'fr' => 'Contrôle', 'it' => 'Verifica'],
        'messung' => ['en' => 'Measurement', 'es' => 'Medición', 'fr' => 'Mesure', 'it' => 'Misurazione'],
        'verteilerarbeit' => ['en' => 'Distribution board work', 'es' => 'Trabajo en cuadro eléctrico', 'fr' => 'Travaux sur tableau', 'it' => 'Lavoro sul quadro elettrico'],
        'eCheck' => ['en' => 'E-check', 'es' => 'Revisión eléctrica', 'fr' => 'Contrôle électrique', 'it' => 'Controllo elettrico'],
        'wallbox' => ['en' => 'Wallbox', 'es' => 'Wallbox', 'fr' => 'Wallbox', 'it' => 'Wallbox'],
        'pvAnschluss' => ['en' => 'PV connection', 'es' => 'Conexión fotovoltaica', 'fr' => 'Raccordement PV', 'it' => 'Collegamento fotovoltaico'],
        'abnahme' => ['en' => 'Acceptance', 'es' => 'Recepción', 'fr' => 'Réception', 'it' => 'Collaudo'],
        'nacharbeit' => ['en' => 'Rework', 'es' => 'Retrabajo', 'fr' => 'Reprise', 'it' => 'Rilavorazione'],
    ],
];
