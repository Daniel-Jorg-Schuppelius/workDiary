<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunRecurringAccountingCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Enums\Finance\AccountingSovereignty;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingSovereigntyResolver, RecurringAccountingService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Feature 125, MVP-675: fällige Belegerwartungen eröffnen und
 * Buchungsvorlagen als ENTWURF erzeugen — nie festschreiben, nie einen
 * Eingangsbeleg erfinden.
 *
 * Läuft nur für Organisationen mit lokaler Buchungshoheit; für alle anderen
 * gäbe es kein Hauptbuch, in das der Entwurf gehören würde.
 */
class RunRecurringAccountingCommand extends Command {
    protected $signature = 'accounting:run-recurring {--date= : Stichtag (Standard: heute)}';

    protected $description = 'Erzeugt fällige Belegerwartungen und Buchungsentwürfe der lokalen Buchhaltung (MVP-675)';

    public function handle(RecurringAccountingService $service, AccountingSovereigntyResolver $sovereignty): int {
        $lock = Cache::lock('accounting:run-recurring', 600);
        if (! $lock->get()) {
            $this->warn('Läuft bereits (Lease aktiv) — Abbruch.');

            return self::SUCCESS;
        }

        try {
            $asOf = CarbonImmutable::parse((string) ($this->option('date') ?? 'today'))->startOfDay();
            $created = 0;
            $blocked = 0;
            $notified = 0;

            foreach (Organization::query()->orderBy('id')->cursor() as $organization) {
                if ($sovereignty->at($organization, $asOf) !== AccountingSovereignty::Local) {
                    continue;
                }

                $actor = $this->systemActor($organization);
                if (! $actor instanceof User) {
                    continue;
                }

                $result = $service->runDue($organization, $asOf, $actor);
                $created += $result['created'];
                $blocked += $result['blocked'];
                $notified += $service->notifyOverdue($organization, $asOf);
            }

            $this->info(sprintf('Vorgänge: %d erzeugt, %d blockiert, %d gemeldet.', $created, $blocked, $notified));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * Ausführende Person: der Verantwortliche einer Vorlage wäre je Vorlage
     * verschieden — für den Lauf reicht ein Admin der Organisation, damit die
     * Entwürfe einen Ersteller haben.
     */
    private function systemActor(Organization $organization): ?User {
        return User::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();
    }
}
