<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesDigestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Finance;

use App\Console\Concerns\IteratesOrganizations;
use App\Http\Controllers\Finance\OpenTimesController;
use App\Models\{Organization, TimeEntry, User};
use App\Notifications\Finance\OpenTimesDigestNotification;
use App\Services\Invoicing\LateTimeEntryDetector;
use Illuminate\Console\Command;

/**
 * Offene-Zeiten-Digest (MVP-461): benachrichtigt je Organisation die Nutzer
 * mit Org-weiter Zeit-Sicht, wenn Nachzügler oder überfällige offene Einträge
 * vorliegen. Ohne Befund wird nichts verschickt.
 */
class OpenTimesDigestCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'finance:open-times-digest ' . self::ORGANIZATION_OPTION;

    protected $description = 'Benachrichtigt die Buchhaltung über Nachzügler und überfällige offene Zeiten (MVP-461)';

    public function handle(LateTimeEntryDetector $detector): int {
        $this->forEachOrganization(function (Organization $org) use ($detector): void {
            // Gleiche Grundmenge wie die Arbeitsliste — inklusive Ausblendung
            // saldo-geführter Kunden, sonst mahnt der Digest Zeiten an, die
            // dort gar nicht auftauchen.
            $open = TimeEntry::query()
                ->withoutLedgerManagedCustomers()
                ->where('billable', true)
                ->where('exported', false);

            $openCount = (clone $open)->count();
            if ($openCount === 0) {
                return;
            }

            $lateCount = $detector->countLateInQuery(clone $open);
            $staleCount = (clone $open)
                ->whereDate('date', '<', now()->subDays(OpenTimesController::STALE_AFTER_DAYS)->toDateString())
                ->count();

            if ($lateCount === 0 && $staleCount === 0) {
                return;
            }

            // Konsole hat keinen Spatie-Team-Kontext (setzt sonst die
            // SetOrganizationContext-Middleware) — ohne ihn findet
            // hasEffectivePermission keine Org-Rollen.
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $previousTeamId = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($org->id);

            try {
                $recipients = User::query()
                    ->where('organization_id', $org->id)
                    ->get()
                    ->filter(fn(User $user): bool => $user->isAdmin() || $user->hasEffectivePermission('timeEntry.viewAny'))
                    ->values();
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
            }

            foreach ($recipients as $recipient) {
                $recipient->notify(new OpenTimesDigestNotification(
                    $openCount,
                    $lateCount,
                    $staleCount,
                    OpenTimesController::STALE_AFTER_DAYS,
                ));
            }

            $this->line("Organisation #{$org->id} ({$org->name}): offen {$openCount}, Nachzügler {$lateCount}, überfällig {$staleCount} → {$recipients->count()} Empfänger benachrichtigt.");
        });

        return self::SUCCESS;
    }
}
