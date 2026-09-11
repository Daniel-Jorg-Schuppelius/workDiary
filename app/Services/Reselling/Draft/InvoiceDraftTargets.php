<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceDraftTargets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Draft;

use App\Models\Customer;

/**
 * Registry der Entwurfsziele (Feature 152, Review 2026-09-11). Singleton
 * (AppServiceProvider): das lokale Ziel steht immer drin, externe Ziele
 * registrieren die Plugins beim Boot — der Kern kennt keinen Anbieter.
 */
final class InvoiceDraftTargets {
    /** @var array<string, InvoiceDraftTarget> Zielschlüssel → Ziel */
    private array $targets = [];

    public function register(InvoiceDraftTarget $target): void {
        $this->targets[$target->key()] = $target;
    }

    /** @return list<InvoiceDraftTarget> */
    public function all(): array {
        return array_values($this->targets);
    }

    public function get(string $key): ?InvoiceDraftTarget {
        return $this->targets[$key] ?? null;
    }

    public function local(): ?InvoiceDraftTarget {
        return $this->targets[LocalInvoiceDraftTarget::KEY] ?? null;
    }

    /** Erstes externes Ziel, das den Empfänger bedient; null ohne passendes Plugin. */
    public function externalFor(Customer $recipient): ?InvoiceDraftTarget {
        foreach ($this->targets as $key => $target) {
            if ($key !== LocalInvoiceDraftTarget::KEY && $target->supports($recipient)) {
                return $target;
            }
        }

        return null;
    }
}
