<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyFinanceMovements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Services;

use App\Models\Platform\{Organization, User};
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use App\Plugins\Firefly\Http\Controllers\FireflyAdminController;
use App\Plugins\Firefly\Models\FireflyTransaction;
use App\Services\Dashboard\Contracts\FinanceMovementSource;
use App\Services\Dashboard\Dto\{FinanceMovement, FinanceMovementSummary};
use Carbon\CarbonImmutable;

/**
 * Firefly-III-Bewegungen in der Kachel „Finanzbewegungen“ (Feature 176/181,
 * MVP-1617); offen sind Bewegungen ohne oder mit mehrdeutiger Zuordnung, die
 * niemand als erledigt markiert hat (MVP-1628).
 */
final class FireflyFinanceMovements implements FinanceMovementSource {
    public function key(): string {
        return 'firefly';
    }

    public function label(): string {
        return 'Firefly III';
    }

    public function icon(): string {
        return 'savings';
    }

    /** Die Firefly-Seite ist Admins vorbehalten; den Plugin-Schalter prüft der Sammler. */
    public function available(User $viewer): bool {
        return $viewer->isAdmin();
    }

    public function summary(Organization $organization, CarbonImmutable $since, int $latest): FinanceMovementSummary {
        $base = FireflyTransaction::query()->where('organization_id', $organization->id);

        return new FinanceMovementSummary(
            unassigned: (clone $base)->openForReview()->count(),
            url: route('admin.firefly.index'),
            unassignedUrl: route('admin.firefly.index', ['match' => FireflyAdminController::OPEN]),
            latest: array_values(array_map(static fn (FireflyTransaction $t): FinanceMovement => new FinanceMovement(
                bookedOn: $t->booked_on,
                title: (string) ($t->counterparty_name ?? $t->kind->label()),
                amount: $t->kind === FireflyTransactionKind::Withdrawal ? $t->booked_amount->abs()->negated() : $t->booked_amount->abs(),
                assigned: $t->match_status === FireflyMatchStatus::Matched || $t->isSettled(),
            ), (clone $base)->where('booked_on', '>=', $since->toDateString())->orderByDesc('booked_on')->orderByDesc('id')->limit($latest)->get()->all())),
        );
    }
}
