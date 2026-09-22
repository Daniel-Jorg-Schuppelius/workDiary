<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : facility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „facility" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'objektkontrolle' => ['en' => 'Site inspection', 'es' => 'Control del inmueble', 'fr' => 'Contrôle du site', 'it' => 'Controllo dell\'immobile'],
        'maengelmeldung' => ['en' => 'Defect report', 'es' => 'Aviso de defecto', 'fr' => 'Signalement de défaut', 'it' => 'Segnalazione difetto'],
        'kleinreparatur' => ['en' => 'Minor repair', 'es' => 'Reparación menor', 'fr' => 'Petite réparation', 'it' => 'Piccola riparazione'],
        'wartungsrunde' => ['en' => 'Maintenance round', 'es' => 'Ronda de mantenimiento', 'fr' => 'Tournée de maintenance', 'it' => 'Giro di manutenzione'],
        'winterdienst' => ['en' => 'Winter service', 'es' => 'Servicio invernal', 'fr' => 'Service hivernal', 'it' => 'Servizio invernale'],
        'zaehlerstand' => ['en' => 'Meter reading', 'es' => 'Lectura de contador', 'fr' => 'Relevé de compteur', 'it' => 'Lettura contatore'],
        'schluessel' => ['en' => 'Key handover/return', 'es' => 'Entrega/devolución de llaves', 'fr' => 'Remise/retour de clés', 'it' => 'Consegna/restituzione chiavi'],
        'notfall' => ['en' => 'Emergency', 'es' => 'Emergencia', 'fr' => 'Urgence', 'it' => 'Emergenza'],
    ],
];
