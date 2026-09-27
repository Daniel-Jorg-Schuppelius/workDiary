<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : it.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „it" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'incident' => ['en' => 'Incident', 'es' => 'Incidente', 'fr' => 'Incident', 'it' => 'Incidente'],
        'request' => ['en' => 'Request', 'es' => 'Solicitud', 'fr' => 'Demande', 'it' => 'Richiesta'],
        'change' => ['en' => 'Change', 'es' => 'Cambio', 'fr' => 'Changement', 'it' => 'Cambiamento'],
        'problem' => ['en' => 'Problem', 'es' => 'Problema', 'fr' => 'Problème', 'it' => 'Problema'],
        'maintenance' => ['en' => 'Maintenance', 'es' => 'Mantenimiento', 'fr' => 'Maintenance', 'it' => 'Manutenzione'],
        'advice' => ['en' => 'Consulting', 'es' => 'Asesoramiento', 'fr' => 'Conseil', 'it' => 'Consulenza'],
    ],
    'waste_code' => [
        'avv_168001' => ['en' => '16 80 01 — Magnetic and optical data carriers', 'es' => '16 80 01 — Soportes de datos magnéticos y ópticos', 'fr' => '16 80 01 — Supports de données magnétiques et optiques', 'it' => '16 80 01 — Supporti dati magnetici e ottici'],
        'avv_200133_h' => ['en' => '20 01 33* — Mixed batteries (including hazardous)', 'es' => '20 01 33* — Pilas mezcladas (con peligrosas)', 'fr' => '20 01 33* — Piles mélangées (dont dangereuses)', 'it' => '20 01 33* — Batterie miste (incluse pericolose)'],
        'avv_200134' => ['en' => '20 01 34 — Batteries (non-hazardous)', 'es' => '20 01 34 — Pilas (no peligrosas)', 'fr' => '20 01 34 — Piles (non dangereuses)', 'it' => '20 01 34 — Batterie (non pericolose)'],
    ],
    'activity' => [
        'analysis' => ['en' => 'Analysis', 'es' => 'Análisis', 'fr' => 'Analyse', 'it' => 'Analisi'],
        'configure' => ['en' => 'Configure', 'es' => 'Configurar', 'fr' => 'Configurer', 'it' => 'Configurare'],
        'deploy' => ['en' => 'Deploy', 'es' => 'Despliegue', 'fr' => 'Déploiement', 'it' => 'Rilascio'],
        'patch' => ['en' => 'Patching', 'es' => 'Aplicar parches', 'fr' => 'Appliquer des correctifs', 'it' => 'Applicare patch'],
        'backup' => ['en' => 'Backup', 'es' => 'Copia de seguridad', 'fr' => 'Sauvegarde', 'it' => 'Backup'],
        'restore' => ['en' => 'Restore', 'es' => 'Restauración', 'fr' => 'Restauration', 'it' => 'Ripristino'],
        'monitor' => ['en' => 'Monitoring', 'es' => 'Monitorización', 'fr' => 'Surveillance', 'it' => 'Monitoraggio'],
    ],
    'defect_type' => [
        'hardware' => ['en' => 'Hardware', 'es' => 'Hardware', 'fr' => 'Matériel', 'it' => 'Hardware'],
        'software' => ['en' => 'Software', 'es' => 'Software', 'fr' => 'Logiciel', 'it' => 'Software'],
        'network' => ['en' => 'Network', 'es' => 'Red', 'fr' => 'Réseau', 'it' => 'Rete'],
        'security' => ['en' => 'Safety', 'es' => 'Seguridad', 'fr' => 'Sécurité', 'it' => 'Sicurezza'],
        'user' => ['en' => 'User', 'es' => 'Usuario', 'fr' => 'Utilisateur', 'it' => 'Utente'],
        'integration' => ['en' => 'Integration', 'es' => 'Integración', 'fr' => 'Intégration', 'it' => 'Integrazione'],
    ],
    'root_cause' => [
        'bug' => ['en' => 'Bug', 'es' => 'Error', 'fr' => 'Bogue', 'it' => 'Bug'],
        'misconfiguration' => ['en' => 'Misconfiguration', 'es' => 'Configuración errónea', 'fr' => 'Configuration erronée', 'it' => 'Configurazione errata'],
        'capacity' => ['en' => 'Capacity', 'es' => 'Capacidad', 'fr' => 'Capacité', 'it' => 'Capacità'],
        'hardwareFailure' => ['en' => 'Hardware failure', 'es' => 'Fallo de hardware', 'fr' => 'Panne matérielle', 'it' => 'Guasto hardware'],
        'dependency' => ['en' => 'Dependency', 'es' => 'Dependencia', 'fr' => 'Dépendance', 'it' => 'Dipendenza'],
        'externalProvider' => ['en' => 'External provider', 'es' => 'Proveedor externo', 'fr' => 'Prestataire externe', 'it' => 'Fornitore esterno'],
    ],
    'result' => [
        'resolved' => ['en' => 'Resolved', 'es' => 'Resuelto', 'fr' => 'Résolu', 'it' => 'Risolto'],
        'workaround' => ['en' => 'Workaround', 'es' => 'Solución alternativa', 'fr' => 'Solution de contournement', 'it' => 'Soluzione temporanea'],
        'knownIssue' => ['en' => 'Known issue', 'es' => 'Problema conocido', 'fr' => 'Problème connu', 'it' => 'Problema noto'],
        'deferred' => ['en' => 'Postponed', 'es' => 'Aplazado', 'fr' => 'Reporté', 'it' => 'Rinviato'],
        'escalated' => ['en' => 'Escalated', 'es' => 'Escalado', 'fr' => 'Escaladé', 'it' => 'Escalato'],
    ],
    'product_group' => [
        'router' => ['en' => 'Router', 'es' => 'Router', 'fr' => 'Routeur', 'it' => 'Router'],
        'switch' => ['en' => 'Switch', 'es' => 'Conmutador', 'fr' => 'Commutateur', 'it' => 'Switch'],
        'firewall' => ['en' => 'Firewall', 'es' => 'Cortafuegos', 'fr' => 'Pare-feu', 'it' => 'Firewall'],
        'accessPoint' => ['en' => 'Access point', 'es' => 'Punto de acceso', 'fr' => 'Point d\'accès', 'it' => 'Access point'],
        'server' => ['en' => 'Server', 'es' => 'Servidor', 'fr' => 'Serveur', 'it' => 'Server'],
        'workstation' => ['en' => 'Workstation', 'es' => 'Estación de trabajo', 'fr' => 'Poste de travail', 'it' => 'Workstation'],
        'printer' => ['en' => 'Printer', 'es' => 'Impresora', 'fr' => 'Imprimante', 'it' => 'Stampante'],
        'virtualization' => ['en' => 'Virtualisation', 'es' => 'Virtualización', 'fr' => 'Virtualisation', 'it' => 'Virtualizzazione'],
        'saas' => ['en' => 'SaaS', 'es' => 'SaaS', 'fr' => 'SaaS', 'it' => 'SaaS'],
    ],
];
