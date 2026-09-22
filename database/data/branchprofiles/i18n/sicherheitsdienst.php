<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sicherheitsdienst.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „sicherheitsdienst" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'wachbuch' => ['en' => 'Logbook entry', 'es' => 'Entrada en el libro de guardia', 'fr' => 'Entrée du registre de garde', 'it' => 'Registrazione nel registro di guardia'],
        'revierfahrt' => ['en' => 'Patrol drive', 'es' => 'Ronda en vehículo', 'fr' => 'Ronde motorisée', 'it' => 'Ronda con veicolo'],
        'kontrollgang' => ['en' => 'Patrol round', 'es' => 'Ronda de control', 'fr' => 'Ronde de contrôle', 'it' => 'Giro di controllo'],
        'alarm' => ['en' => 'Alarm response', 'es' => 'Verificación de alarma', 'fr' => 'Levée de doute', 'it' => 'Intervento su allarme'],
        'zutritt' => ['en' => 'Access control', 'es' => 'Control de acceso', 'fr' => 'Contrôle d\'accès', 'it' => 'Controllo accessi'],
        'schluessel' => ['en' => 'Key handover/return', 'es' => 'Entrega/devolución de llaves', 'fr' => 'Remise/retour de clés', 'it' => 'Consegna/restituzione chiavi'],
        'vorfall' => ['en' => 'Incident report', 'es' => 'Informe de incidente', 'fr' => 'Rapport d\'incident', 'it' => 'Segnalazione incidente'],
        'uebergabe' => ['en' => 'Shift handover', 'es' => 'Relevo de turno', 'fr' => 'Passation de service', 'it' => 'Passaggio di turno'],
        'sonderdienst' => ['en' => 'Special assignment', 'es' => 'Servicio especial', 'fr' => 'Mission spéciale', 'it' => 'Servizio speciale'],
    ],
];
