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
use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\ResalePeriod;
use App\Notifications\Finance\ResalePeriodsDigestNotification;
use App\Services\Reselling\Register\{ResaleOverview, ResalePriceCheck, ResaleRenewalReport, ResaleUnbilledReport};
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reselling-Digest (Feature 152, Prozesse 6 / Review 2026-09-10 A5): je
 * Organisation die Kennzahlen der Periodenseite — fällige Perioden mit
 * offenem Betrag, unbestätigte Vorschläge, Abos ohne Halter, Verlängerungen
 * in Kürze, Abos ohne Rechnung seit langem, ausstehende Rechnungsentwürfe
 * der letzten Woche, Abos mit geändertem Katalog-Einkaufspreis (Preisliste
 * der letzten Woche) — an die Nutzer mit `reselling.manage`. Ohne Befund wird
 * nichts verschickt.
 */
class ResaleDigestCommand extends Command {
    use IteratesOrganizations;

    /** Vorlauf für „Verlängerung in Kürze" (Prozesse 6: 30 Tage). */
    public const RENEWAL_DAYS = 30;

    /** Rückschau für ausstehende Rechnungsentwürfe (Serienlauf: eine Digest-Woche). */
    public const DRAFT_DAYS = 7;

    /** Rückschau für neue Katalogzeilen (Preisliste), deren Einkaufspreis vom Vertrag abweicht. */
    public const CATALOG_DAYS = 7;

    protected $signature = 'resale:digest ' . self::ORGANIZATION_OPTION;

    protected $description = 'Benachrichtigt die Abo-Verantwortlichen über fällige Perioden, offene Vorschläge, Halterlücken und Verlängerungen (Feature 152)';

    public function handle(ResaleRenewalReport $renewals, ResaleUnbilledReport $unbilled, ResalePriceCheck $prices, ResaleOverview $overview): int {
        $this->forEachOrganization(function (Organization $org) use ($renewals, $unbilled, $prices, $overview): void {
            $today = ResalePeriod::today();

            // Dieselben Zahlen wie der Überblick (Phase 163): Karenz, laufende Serienvorlage, Vorschläge nur als Stichprobe.
            $summary = $overview->build($org, $today)['summary'];
            $proposed = $summary['proposed'];
            $unassigned = $summary['unassigned'];
            $renewalCount = $renewals->build($today, $today->addDays(self::RENEWAL_DAYS), $today)['buckets'][self::RENEWAL_DAYS] ?? 0;
            $staleCount = count($unbilled->build(ResaleUnbilledReport::DEFAULT_DAYS, $today));
            // Ausstehende Entwürfe der letzten 7 Tage (Serienlauf oder Klick): Stempel steht noch, Entwurf wurde noch nicht Rechnung.
            $draftCount = ResalePeriod::query()
                ->whereNotNull('draft_reference')
                ->where('draft_created_at', '>=', now()->subDays(self::DRAFT_DAYS))
                ->distinct()
                ->count('draft_reference');
            // Neue Preisliste: aktive Abos, deren Vertragspreis vom heute gültigen, in der letzten Woche angelegten Katalogpreis abweicht.
            $catalogChanges = $prices->recentCatalogChanges($today, self::CATALOG_DAYS);
            // Feature 185 (MVP-1412): Befunde Bestand ↔ Serienvorlagen.
            $planIssues = count(app(\App\Services\Reselling\Plan\PlanAudit::class)->findings($org));
            // Feature 185 (MVP-1418): Anbieter, deren letzter Export älter als 30 Tage ist.
            $staleImports = count($overview->staleProviders($org, $today));

            $notification = new ResalePeriodsDigestNotification(
                $summary['open'],
                $summary['open_amount'],
                $proposed,
                $unassigned,
                $renewalCount,
                $staleCount,
                self::RENEWAL_DAYS,
                ResaleUnbilledReport::DEFAULT_DAYS,
                $draftCount,
                self::DRAFT_DAYS,
                $catalogChanges,
                $planIssues,
                $staleImports,
                ResaleOverview::IMPORT_STALE_DAYS,
            );
            if ($notification->total() === 0) {
                return;
            }

            $recipients = $this->recipients($org);
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }

            $this->line(sprintf(
                'Organisation #%d (%s): fällig %d, Vorschläge %d, ohne Halter %d, Verlängerungen %d, ohne Rechnung %d, Entwürfe %d, Katalogpreis geändert %d, Serienvorlagen-Befunde %d, veraltete Anbieterstände %d → %d Empfänger benachrichtigt.',
                $org->id,
                $org->name,
                $notification->dueCount,
                $notification->proposedCount,
                $notification->unassignedCount,
                $notification->renewalCount,
                $notification->staleCount,
                $notification->draftCount,
                $notification->catalogChanges,
                $notification->planIssues,
                $notification->staleImports,
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
