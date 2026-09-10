<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDigestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Enums\User\Permission;
use App\Models\{Organization, User};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Notifications\Finance\ResalePeriodsDigestNotification;
use App\Services\Reselling\Register\{ResaleRenewalReport, ResaleUnbilledReport};
use App\Support\Query\DateRange;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reselling-Digest (Feature 152, Prozesse 6 / Review 2026-09-10 A5): je
 * Organisation die Kennzahlen der Periodenseite — fällige Perioden mit
 * offenem Betrag, unbestätigte Vorschläge, Abos ohne Halter, Verlängerungen
 * in Kürze, Abos ohne Rechnung seit langem — an die Nutzer mit
 * `reselling.manage`. Ohne Befund wird nichts verschickt.
 */
class ResaleDigestCommand extends Command {
    use IteratesOrganizations;

    /** Vorlauf für „Verlängerung in Kürze" (Prozesse 6: 30 Tage). */
    public const RENEWAL_DAYS = 30;

    protected $signature = 'resale:digest ' . self::ORGANIZATION_OPTION;

    protected $description = 'Benachrichtigt die Abo-Verantwortlichen über fällige Perioden, offene Vorschläge, Halterlücken und Verlängerungen (Feature 152)';

    public function handle(ResaleRenewalReport $renewals, ResaleUnbilledReport $unbilled): int {
        $this->forEachOrganization(function (Organization $org) use ($renewals, $unbilled): void {
            $today = ResalePeriod::today();

            // Fällig = Beginn erreicht, fremder Halter — eigener Bestand wird nie berechnet (wie die Kachel).
            $due = ResalePeriod::query()
                ->where('starts_on', '<', DateRange::dayAfter($today))
                ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
            $open = (clone $due)->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])->with('links')->get();

            /** @var array<string, Money> $amounts Offener Betrag je Währung (Teilperioden anteilig) */
            $amounts = [];
            foreach ($open as $period) {
                $amount = $period->openAmount();
                if ($amount === null) {
                    continue;
                }
                $code = $amount->getCurrency()->value;
                $amounts[$code] = isset($amounts[$code]) ? $amounts[$code]->plus($amount) : $amount;
            }

            $proposed = (clone $due)
                ->whereIn('status', [PeriodStatus::Billed->value, PeriodStatus::Partial->value])
                ->whereNull('decided_at')
                ->whereHas('links', static fn(Builder $l) => $l->where('origin', LinkOrigin::Proposed->value))
                ->count();
            $unassigned = ResaleSubscription::query()->planning()->unassigned()->count();
            $renewalCount = $renewals->build($today, $today->addDays(self::RENEWAL_DAYS), $today)['buckets'][self::RENEWAL_DAYS] ?? 0;
            $staleCount = count($unbilled->build(ResaleUnbilledReport::DEFAULT_DAYS, $today));

            $notification = new ResalePeriodsDigestNotification(
                $open->count(),
                implode(' · ', array_map(static fn(Money $m): string => $m->withScale(2)->format(), array_values($amounts))),
                $proposed,
                $unassigned,
                $renewalCount,
                $staleCount,
                self::RENEWAL_DAYS,
                ResaleUnbilledReport::DEFAULT_DAYS,
            );
            if ($notification->total() === 0) {
                return;
            }

            $recipients = $this->recipients($org);
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }

            $this->line(sprintf(
                'Organisation #%d (%s): fällig %d, Vorschläge %d, ohne Halter %d, Verlängerungen %d, ohne Rechnung %d → %d Empfänger benachrichtigt.',
                $org->id,
                $org->name,
                $notification->dueCount,
                $notification->proposedCount,
                $notification->unassignedCount,
                $notification->renewalCount,
                $notification->staleCount,
                $recipients->count(),
            ));
        });

        return self::SUCCESS;
    }

    /**
     * Nutzer der Organisation mit `reselling.manage`. Konsole hat keinen
     * Spatie-Team-Kontext (setzt sonst die SetOrganizationContext-Middleware)
     * — ohne ihn findet hasEffectivePermission keine Org-Rollen.
     *
     * @return Collection<int, User>
     */
    private function recipients(Organization $org): Collection {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($org->id);

        try {
            return User::query()
                ->where('organization_id', $org->id)
                ->get()
                ->filter(static fn(User $user): bool => $user->isAdmin() || $user->hasEffectivePermission(Permission::ResellingManage->value))
                ->values();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
