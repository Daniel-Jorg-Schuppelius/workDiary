<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstaltungstechnik.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „veranstaltungstechnik" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'angebot' => ['en' => 'Quote', 'es' => 'Presupuesto', 'fr' => 'Devis', 'it' => 'Preventivo'],
        'vorbereitung' => ['en' => 'Preparation', 'es' => 'Preparación', 'fr' => 'Préparation', 'it' => 'Preparazione'],
        'anlieferung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'aufbau' => ['en' => 'Setup', 'es' => 'Montaje', 'fr' => 'Montage', 'it' => 'Allestimento'],
        'safetyCheck' => ['en' => 'Safety check', 'es' => 'Control de seguridad', 'fr' => 'Contrôle de sécurité', 'it' => 'Controllo di sicurezza'],
        'soundcheck' => ['en' => 'Soundcheck', 'es' => 'Prueba de sonido', 'fr' => 'Balance', 'it' => 'Prova audio'],
        'showbetreuung' => ['en' => 'Show support', 'es' => 'Asistencia durante el show', 'fr' => 'Régie de spectacle', 'it' => 'Assistenza durante lo show'],
        'abbau' => ['en' => 'Teardown', 'es' => 'Desmontaje', 'fr' => 'Démontage', 'it' => 'Smontaggio'],
        'ruecknahme' => ['en' => 'Return', 'es' => 'Devolución', 'fr' => 'Retour', 'it' => 'Restituzione'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Dommage', 'it' => 'Danno'],
    ],
];
