<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuppressesCoreInvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use Illuminate\Database\Query\Builder;

/**
 * Quelle, deren gespiegelte Belege lokale Rechnungen verdrängen
 * (Dublettenregel 1: extern führt). Die Dubletten-Klausel liegt damit bei der
 * Plugin-Quelle — der Kern referenziert keine Plugin-Tabelle.
 */
interface SuppressesCoreInvoices {
    /**
     * Ergänzt am Builder der lokalen Rechnungen die `whereNotExists`-Klausel
     * dieser Quelle. Der äußere Builder steht auf `invoices`
     * (Korrelation über `invoices.organization_id`, `invoices.number`,
     * `invoices.external_number`).
     */
    public function suppressCoreInvoices(Builder $invoices): void;
}
