<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarksLinkedExpenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

/**
 * Quelle, deren bestätigte Auslagen-Verknüpfung (MVP-551) die Auslage
 * geldunwirksam macht: dort führt der zugeordnete Buchhaltungsbeleg. Die
 * Kriterien benennen die `external_references`-Zeilen der Quelle — eine je
 * Programm, der Belegspiegel `accounting_vouchers` trägt mehrere (MVP-1498).
 */
interface MarksLinkedExpenses {
    /**
     * @return list<array{plugin_id: string, external_type: string}>
     */
    public function expenseLinkCriteria(): array;
}
