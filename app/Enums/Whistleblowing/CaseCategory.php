<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Whistleblowing;

/**
 * Meldekategorien (Abschnitt 2 des Konzepts).
 */
enum CaseCategory: string {
    case Corruption = 'corruption';
    case Fraud = 'fraud';
    case MoneyLaundering = 'money_laundering';
    case Procurement = 'procurement';
    case DataProtection = 'data_protection';
    case ProductSafety = 'product_safety';
    case Environment = 'environment';
    case Discrimination = 'discrimination';
    case PolicyViolation = 'policy_violation';
    case Other = 'other';
}
