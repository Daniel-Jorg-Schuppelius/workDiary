<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedSourceRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use Illuminate\Database\Query\Builder;

/**
 * Registry der Belegfluss-Quellen (Vollscan 2026-08, B9). Muss als Singleton
 * gebunden sein (AppServiceProvider): Kern-Quellen kommen aus der Bindung,
 * Plugin-Quellen registriert der jeweilige Plugin-ServiceProvider beim Boot —
 * gleiche Mechanik wie {@see \App\Services\Shipping\ShippingProviderRegistry}.
 *
 * Ob eine Quelle in einer konkreten Abfrage sichtbar ist, entscheidet weiter
 * ausschließlich der Filter ({@see \App\Services\Billing\DocumentFeedFilters}
 * — Policy-`sources`, Herkunft, Richtung/Art), nicht die Registrierung.
 */
class DocumentFeedSourceRegistry {
    /** @var array<string, DocumentFeedSource> */
    private array $sources = [];

    public function register(DocumentFeedSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    /** @return list<DocumentFeedSource> */
    public function sources(): array {
        return array_values($this->sources);
    }

    /**
     * Wendet die Dubletten-Klauseln aller Quellen an, deren gespiegelte
     * Belege lokale Rechnungen verdrängen (Dublettenregel 1: extern führt).
     */
    public function suppressCoreInvoices(Builder $invoices): void {
        foreach ($this->sources as $source) {
            if ($source instanceof SuppressesCoreInvoices) {
                $source->suppressCoreInvoices($invoices);
            }
        }
    }

    /**
     * Verknüpfungs-Kriterien aller Quellen, deren bestätigte Zuordnung eine
     * Auslage geldunwirksam macht (MVP-551).
     *
     * @return list<array{plugin_id: string, external_type: string}>
     */
    public function expenseLinkCriteria(): array {
        $criteria = [];
        foreach ($this->sources as $source) {
            if ($source instanceof MarksLinkedExpenses) {
                $criteria[] = $source->expenseLinkCriteria();
            }
        }

        return $criteria;
    }
}
