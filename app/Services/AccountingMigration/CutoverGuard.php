<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CutoverGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration;

use App\Enums\Finance\TransferTarget;
use App\Enums\Migration\MigrationProvider;
use App\Models\Customer;
use Carbon\CarbonInterface;

/**
 * Stichtagssperre des Buchhaltungswechsels (MVP-653, Issue #86).
 *
 * Verbindlicher Grundsatz: „Ab dem Umschaltstichtag entstehen neue
 * Rechnungen ausschließlich im Zielsystem." Diese Klasse ist der einzige
 * Prüfpunkt dafür — sie entscheidet je Kunde und Zeitpunkt, ob ein
 * Übergabeziel noch zulässig ist. Dadurch kann dieselbe Zeit-, Material-,
 * Reise- oder Auslagenquelle nie an beide Fakturaziele gehen: das
 * Quellsystem ist ab dem Stichtag technisch gesperrt, Altbelege werden dort
 * lesend zu Ende geführt.
 *
 * Ohne gesetzten Stichtag verhält sich alles wie bisher (kein Wechsel aktiv).
 */
class CutoverGuard {
    /** Greift für diesen Kunden bereits der Stichtag? */
    public function isCutoverReached(Customer $customer, ?CarbonInterface $at = null): bool {
        $cutover = $customer->billing_cutover_on;
        if ($cutover === null) {
            return false;
        }

        return ($at ?? now())->startOfDay()->greaterThanOrEqualTo($cutover->startOfDay());
    }

    /**
     * Darf an dieses Ziel noch übergeben werden? Gesperrt ist ausschließlich
     * das QUELLSYSTEM des Wechsels dieses Kunden — welches das ist, steht am
     * Kunden (`billing_cutover_from`) und nicht fest im Code. Damit gilt die
     * Sperre in beide Richtungen.
     */
    public function allowsTarget(Customer $customer, TransferTarget $target, ?CarbonInterface $at = null): bool {
        $blocked = $this->blockedTarget($customer);
        if ($blocked === null || $blocked !== $target) {
            return true;
        }

        return ! $this->isCutoverReached($customer, $at);
    }

    /** Das nach dem Stichtag gesperrte Übergabeziel (Quellsystem) oder null. */
    public function blockedTarget(Customer $customer): ?TransferTarget {
        $provider = MigrationProvider::tryFrom((string) ($customer->billing_cutover_from ?? ''));

        return $provider?->transferTarget();
    }

    /**
     * Entfernt gesperrte Ziele aus einer Auswahl (UI + Validierung nutzen
     * dieselbe Quelle der Wahrheit).
     *
     * @param  array<int, TransferTarget>  $targets
     * @return array<int, TransferTarget>
     */
    public function filterTargets(Customer $customer, array $targets, ?CarbonInterface $at = null): array {
        return array_values(array_filter(
            $targets,
            fn (TransferTarget $target): bool => $this->allowsTarget($customer, $target, $at),
        ));
    }

    /** Begründung für die Sperre (UI-Meldung). */
    public function blockReason(Customer $customer, TransferTarget $target): ?string {
        if ($this->allowsTarget($customer, $target)) {
            return null;
        }

        return (string) __('Seit dem Umschaltstichtag :date entstehen neue Fakturavorgänge ausschließlich im Zielsystem — :target ist für diesen Kunden gesperrt.', [
            'date' => $customer->billing_cutover_on?->format(\App\Support\Formats::date()) ?? '—',
            'target' => $target->label(),
        ]);
    }
}
