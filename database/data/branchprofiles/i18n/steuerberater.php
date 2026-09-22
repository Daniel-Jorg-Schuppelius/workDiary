<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : steuerberater.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Übersetzungen zum Branchenprofil „steuerberater" (MVP-841): je Domäne und Code die
// Labels für die aktivierbaren Sprachen; der BranchProfileInstaller schreibt
// sie nach classifications.label_i18n. Deutsch ist das Quell-Label im Profil.
return [
    'entry_type' => [
        'fibu' => ['en' => 'Bookkeeping', 'es' => 'Contabilidad financiera', 'fr' => 'Comptabilité', 'it' => 'Contabilità'],
        'lohn' => ['en' => 'Payroll', 'es' => 'Nóminas', 'fr' => 'Paie', 'it' => 'Paghe'],
        'abschluss' => ['en' => 'Annual accounts', 'es' => 'Cierre anual', 'fr' => 'Clôture annuelle', 'it' => 'Bilancio annuale'],
        'steuererklaerung' => ['en' => 'Tax return', 'es' => 'Declaración de impuestos', 'fr' => 'Déclaration fiscale', 'it' => 'Dichiarazione dei redditi'],
        'voranmeldung' => ['en' => 'VAT advance return', 'es' => 'Declaración previa de IVA', 'fr' => 'Déclaration de TVA', 'it' => 'Dichiarazione IVA periodica'],
        'beratung' => ['en' => 'Consulting', 'es' => 'Asesoramiento', 'fr' => 'Conseil', 'it' => 'Consulenza'],
        'fristensache' => ['en' => 'Deadline matter', 'es' => 'Asunto con plazo', 'fr' => 'Dossier à échéance', 'it' => 'Pratica con scadenza'],
        'kommunikation' => ['en' => 'Communication', 'es' => 'Comunicación', 'fr' => 'Communication', 'it' => 'Comunicazione'],
        'pruefung' => ['en' => 'Audit', 'es' => 'Inspección', 'fr' => 'Contrôle', 'it' => 'Verifica'],
    ],
];
