<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyFinanceMovements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Models\Platform\{Organization, User};
use App\Plugins\Etsy\Models\EtsyLedgerEntry;
use App\Services\Dashboard\Contracts\FinanceMovementSource;
use App\Services\Dashboard\Dto\{FinanceMovement, FinanceMovementSummary};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Buchungen des Etsy-Shopkontos (Gebühren, Auszahlungen) in der Kachel
 * „Finanzbewegungen“ (Phase 160 E9, MVP-1617). Etsy ordnet nichts zu; die
 * Summen je Art bleiben auf der Etsy-Seite.
 */
final class EtsyFinanceMovements implements FinanceMovementSource {
    public function key(): string {
        return 'etsy';
    }

    public function label(): string {
        return 'Etsy';
    }

    public function icon(): string {
        return 'storefront';
    }

    /** Die Etsy-Seite ist Admins vorbehalten; den Plugin-Schalter prüft der Sammler. */
    public function available(User $viewer): bool {
        return $viewer->isAdmin();
    }

    public function summary(Organization $organization, CarbonImmutable $since, int $latest): FinanceMovementSummary {
        return new FinanceMovementSummary(
            unassigned: null,
            url: route('admin.etsy.index'),
            unassignedUrl: null,
            latest: array_values(array_map(static fn (EtsyLedgerEntry $entry): FinanceMovement => new FinanceMovement(
                bookedOn: CarbonImmutable::instance($entry->posted_at ?? $entry->created_at ?? CarbonImmutable::now()),
                title: (string) ($entry->description ?: $entry->ledger_type ?: 'Etsy'),
                // Etsy liefert Beträge in der kleinsten Einheit.
                amount: Money::ofMinor($entry->amount, CurrencyCode::tryFrom(strtoupper((string) $entry->currency)) ?? CurrencyCode::Euro),
                detail: $entry->description !== null ? $entry->ledger_type : null,
            ), EtsyLedgerEntry::query()
                ->where('organization_id', $organization->id)
                ->where('posted_at', '>=', $since->startOfDay())
                ->orderByDesc('posted_at')->orderByDesc('id')
                ->limit($latest)->get()->all())),
        );
    }
}
