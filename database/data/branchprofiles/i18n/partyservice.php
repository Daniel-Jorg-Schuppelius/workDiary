<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : partyservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „partyservice" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'anfrage' => ['en' => 'Enquiry', 'es' => 'Consulta', 'fr' => 'Demande', 'it' => 'Richiesta'],
        'angebot' => ['en' => 'Quote', 'es' => 'Presupuesto', 'fr' => 'Devis', 'it' => 'Preventivo'],
        'menueplanung' => ['en' => 'Menu planning', 'es' => 'Planificación del menú', 'fr' => 'Planification du menu', 'it' => 'Pianificazione del menu'],
        'einkauf' => ['en' => 'Purchasing', 'es' => 'Compras', 'fr' => 'Achats', 'it' => 'Acquisti'],
        'vorbereitung' => ['en' => 'Preparation / mise en place', 'es' => 'Preparación / mise en place', 'fr' => 'Préparation / mise en place', 'it' => 'Preparazione / mise en place'],
        'anlieferung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'aufbau' => ['en' => 'Buffet setup', 'es' => 'Montaje del bufé', 'fr' => 'Installation du buffet', 'it' => 'Allestimento buffet'],
        'service' => ['en' => 'On-site service', 'es' => 'Servicio en el lugar', 'fr' => 'Service sur place', 'it' => 'Servizio in loco'],
        'abbau' => ['en' => 'Dismantling', 'es' => 'Desmontaje', 'fr' => 'Démontage', 'it' => 'Smontaggio'],
        'ruecknahme' => ['en' => 'Return / cleaning', 'es' => 'Recogida / limpieza', 'fr' => 'Reprise / nettoyage', 'it' => 'Ritiro / pulizia'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
];
