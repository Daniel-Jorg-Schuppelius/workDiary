<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : pflege.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „pflege" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'grundpflege' => ['en' => 'Basic care (SGB XI)', 'es' => 'Cuidados básicos (SGB XI)', 'fr' => 'Soins de base (SGB XI)', 'it' => 'Assistenza di base (SGB XI)'],
        'behandlungspflege' => ['en' => 'Medical care (SGB V)', 'es' => 'Cuidados médicos (SGB V)', 'fr' => 'Soins médicaux (SGB V)', 'it' => 'Assistenza sanitaria (SGB V)'],
        'hauswirtschaft' => ['en' => 'Domestic help', 'es' => 'Ayuda doméstica', 'fr' => 'Aide ménagère', 'it' => 'Aiuto domestico'],
        'betreuung' => ['en' => 'Support service (§45b)', 'es' => 'Servicio de acompañamiento (§45b)', 'fr' => 'Prestation d\'accompagnement (§45b)', 'it' => 'Servizio di assistenza (§45b)'],
        'beratungsbesuch' => ['en' => 'Advisory visit (§37.3)', 'es' => 'Visita de asesoramiento (§37.3)', 'fr' => 'Visite de conseil (§37.3)', 'it' => 'Visita di consulenza (§37.3)'],
        'erstbesuch' => ['en' => 'First visit / care assessment', 'es' => 'Primera visita / anamnesis', 'fr' => 'Première visite / anamnèse', 'it' => 'Prima visita / anamnesi'],
        'pflegevisite' => ['en' => 'Care review', 'es' => 'Visita de supervisión', 'fr' => 'Visite de supervision', 'it' => 'Visita di supervisione'],
        'reklamation' => ['en' => 'Complaint', 'es' => 'Queja / reclamación', 'fr' => 'Réclamation', 'it' => 'Reclamo'],
    ],
];
