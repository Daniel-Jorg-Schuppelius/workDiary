<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : photovoltaik-energieberatung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „photovoltaik-energieberatung" (MVP-1062); Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'begehung' => ['en' => 'Site survey', 'es' => 'Visita in situ', 'fr' => 'Visite sur site', 'it' => 'Sopralluogo'],
        'montage' => ['en' => 'PV installation', 'es' => 'Montaje fotovoltaico', 'fr' => 'Pose photovoltaïque', 'it' => 'Montaggio fotovoltaico'],
        'speicher' => ['en' => 'Storage/wallbox', 'es' => 'Batería/wallbox', 'fr' => 'Stockage/borne', 'it' => 'Accumulo/wallbox'],
        'inbetriebnahme' => ['en' => 'Commissioning', 'es' => 'Puesta en marcha', 'fr' => 'Mise en service', 'it' => 'Messa in servizio'],
        'wartung' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'beratung' => ['en' => 'Energy consulting', 'es' => 'Asesoría energética', 'fr' => 'Conseil en énergie', 'it' => 'Consulenza energetica'],
    ],
    'activity' => [
        'planen' => ['en' => 'Plan', 'es' => 'Planificar', 'fr' => 'Planifier', 'it' => 'Pianificare'],
        'montieren' => ['en' => 'Install', 'es' => 'Montar', 'fr' => 'Monter', 'it' => 'Montare'],
        'verkabeln' => ['en' => 'Wire', 'es' => 'Cablear', 'fr' => 'Câbler', 'it' => 'Cablare'],
        'messen' => ['en' => 'Measure', 'es' => 'Medir', 'fr' => 'Mesurer', 'it' => 'Misurare'],
        'anmelden' => ['en' => 'Register with grid operator', 'es' => 'Registrar ante el operador de red', 'fr' => 'Déclarer au gestionnaire de réseau', 'it' => 'Registrare presso il gestore di rete'],
        'dokumentieren' => ['en' => 'Document', 'es' => 'Documentar', 'fr' => 'Documenter', 'it' => 'Documentare'],
    ],
    'defect_type' => [
        'ertrag' => ['en' => 'Reduced yield', 'es' => 'Rendimiento reducido', 'fr' => 'Rendement réduit', 'it' => 'Resa ridotta'],
        'wechselrichter' => ['en' => 'Inverter fault', 'es' => 'Avería del inversor', 'fr' => 'Panne de l’onduleur', 'it' => 'Guasto dell’inverter'],
        'isolation' => ['en' => 'Insulation fault', 'es' => 'Fallo de aislamiento', 'fr' => 'Défaut d’isolement', 'it' => 'Guasto di isolamento'],
        'verschattung' => ['en' => 'Shading', 'es' => 'Sombreado', 'fr' => 'Ombrage', 'it' => 'Ombreggiamento'],
        'kommunikation' => ['en' => 'Communication error', 'es' => 'Error de comunicación', 'fr' => 'Erreur de communication', 'it' => 'Errore di comunicazione'],
    ],
    'root_cause' => [
        'verschmutzung' => ['en' => 'Soiling', 'es' => 'Suciedad', 'fr' => 'Encrassement', 'it' => 'Sporcizia'],
        'defekt' => ['en' => 'Defect', 'es' => 'Avería', 'fr' => 'Défaut', 'it' => 'Guasto'],
        'planung' => ['en' => 'Planning', 'es' => 'Planificación', 'fr' => 'Conception', 'it' => 'Progettazione'],
        'netz' => ['en' => 'Grid side', 'es' => 'Lado de red', 'fr' => 'Côté réseau', 'it' => 'Lato rete'],
    ],
    'result' => [
        'inBetrieb' => ['en' => 'In operation', 'es' => 'En servicio', 'fr' => 'En service', 'it' => 'In servizio'],
        'eingeschraenkt' => ['en' => 'Running with limitations', 'es' => 'En servicio con limitaciones', 'fr' => 'En service limité', 'it' => 'In servizio con limitazioni'],
        'ersatzteil' => ['en' => 'Spare part ordered', 'es' => 'Repuesto pedido', 'fr' => 'Pièce commandée', 'it' => 'Ricambio ordinato'],
        'netzbetreiber' => ['en' => 'Waiting for grid operator', 'es' => 'Esperando al operador de red', 'fr' => 'En attente du gestionnaire de réseau', 'it' => 'In attesa del gestore di rete'],
    ],
];
