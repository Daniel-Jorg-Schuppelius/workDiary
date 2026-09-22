<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taxi-mietwagen.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „taxi-mietwagen" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'fahrtanfrage' => ['en' => 'Ride request', 'es' => 'Solicitud de viaje', 'fr' => 'Demande de course', 'it' => 'Richiesta di corsa'],
        'vorbestellung' => ['en' => 'Advance booking', 'es' => 'Reserva anticipada', 'fr' => 'Réservation à l\'avance', 'it' => 'Prenotazione anticipata'],
        'sofortfahrt' => ['en' => 'Immediate ride', 'es' => 'Viaje inmediato', 'fr' => 'Course immédiate', 'it' => 'Corsa immediata'],
        'serienfahrt' => ['en' => 'Recurring ride', 'es' => 'Viaje periódico', 'fr' => 'Course récurrente', 'it' => 'Corsa ricorrente'],
        'bereitstellung' => ['en' => 'Standby', 'es' => 'Puesta a disposición', 'fr' => 'Mise à disposition', 'it' => 'Messa a disposizione'],
        'personenfahrt' => ['en' => 'Passenger ride', 'es' => 'Viaje de pasajeros', 'fr' => 'Course passagers', 'it' => 'Corsa passeggeri'],
        'wartezeit' => ['en' => 'Waiting time', 'es' => 'Tiempo de espera', 'fr' => 'Temps d\'attente', 'it' => 'Tempo di attesa'],
        'leerfahrt' => ['en' => 'Empty run', 'es' => 'Viaje en vacío', 'fr' => 'Course à vide', 'it' => 'Corsa a vuoto'],
        'fahrzeugwechsel' => ['en' => 'Vehicle change', 'es' => 'Cambio de vehículo', 'fr' => 'Changement de véhicule', 'it' => 'Cambio veicolo'],
        'stoerung' => ['en' => 'Breakdown', 'es' => 'Avería', 'fr' => 'Panne', 'it' => 'Guasto'],
        'unfall' => ['en' => 'Accident', 'es' => 'Accidente', 'fr' => 'Accident', 'it' => 'Incidente'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'abrechnung' => ['en' => 'Settlement', 'es' => 'Liquidación', 'fr' => 'Décompte', 'it' => 'Rendiconto'],
    ],
];
