<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : spedition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „spedition" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'transportauftrag' => ['en' => 'Transport order', 'es' => 'Orden de transporte', 'fr' => 'Ordre de transport', 'it' => 'Ordine di trasporto'],
        'disposition' => ['en' => 'Dispatching', 'es' => 'Planificación', 'fr' => 'Dispatching', 'it' => 'Pianificazione'],
        'abholung' => ['en' => 'Pickup', 'es' => 'Recogida', 'fr' => 'Enlèvement', 'it' => 'Ritiro'],
        'beladung' => ['en' => 'Loading', 'es' => 'Carga', 'fr' => 'Chargement', 'it' => 'Carico'],
        'umschlag' => ['en' => 'Transshipment', 'es' => 'Transbordo', 'fr' => 'Transbordement', 'it' => 'Trasbordo'],
        'transport' => ['en' => 'Transport', 'es' => 'Transporte', 'fr' => 'Transport', 'it' => 'Trasporto'],
        'zustellung' => ['en' => 'Delivery', 'es' => 'Entrega', 'fr' => 'Livraison', 'it' => 'Consegna'],
        'rueckladung' => ['en' => 'Return load', 'es' => 'Carga de retorno', 'fr' => 'Fret de retour', 'it' => 'Carico di ritorno'],
        'wartezeit' => ['en' => 'Waiting time', 'es' => 'Tiempo de espera', 'fr' => 'Temps d\'attente', 'it' => 'Tempo di attesa'],
        'schaden' => ['en' => 'Damage', 'es' => 'Daño', 'fr' => 'Avarie', 'it' => 'Danno'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
        'nachkalkulation' => ['en' => 'Post-calculation', 'es' => 'Cálculo posterior', 'fr' => 'Calcul a posteriori', 'it' => 'Consuntivo'],
    ],
];
