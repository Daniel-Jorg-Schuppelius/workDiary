<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecalculateTimeRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{Attendance, AuditLog, Organization, TimeExport, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Surcharge\{SurchargeRule, TimeRuleResult};
use App\Services\Surcharge\TimeRuleEngine;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * MVP-513 (Feature 103): explizite, auditierte Neuberechnung der
 * Zeitregel-Ergebnisse eines Monats. Regeländerungen wirken NIE still auf
 * historische Zeiträume — nur dieser Befehl bewertet sie neu.
 *
 * Bereits erstellte Zeitexporte bleiben unverändert (revisionssicheres
 * Original mit payload_hash); der Befehl weist auf existierende Exporte
 * des Zeitraums hin, damit Abweichungen bewusst sind.
 */
class RecalculateTimeRules extends Command {
    protected $signature = 'rules:recalculate
        {--org= : Organisations-ID (Pflicht)}
        {--year= : Jahr (Pflicht)}
        {--month= : Monat 1-12 (Pflicht)}
        {--user= : Nur dieser Benutzer (ID, optional)}';

    protected $description = 'Zeitregel-Ergebnisse (time_rule_results) eines Monats auditiert neu berechnen (MVP-513)';

    public function handle(TimeRuleEngine $engine): int {
        $orgId = (int) $this->option('org');
        $year = (int) $this->option('year');
        $month = (int) $this->option('month');
        if ($orgId <= 0 || $year < 2000 || $month < 1 || $month > 12) {
            $this->error('Pflichtoptionen: --org=<id> --year=<jahr> --month=<1-12>');

            return self::FAILURE;
        }

        $organization = Organization::query()->find($orgId);
        if (! $organization instanceof Organization) {
            $this->error("Organisation {$orgId} nicht gefunden.");

            return self::FAILURE;
        }
        // Org-Kontext binden (Tz::current, Setting::get der Feiertage).
        app()->instance('currentOrganization', $organization);

        $start = CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month))->startOfDay();
        $end = $start->endOfMonth();

        $rules = SurchargeRule::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $userIds = $this->resolveUserIds($orgId, $start, $end);
        if ($userIds === []) {
            $this->info('Keine Benutzer mit Anwesenheiten im Zeitraum.');

            return self::SUCCESS;
        }

        $existingExports = TimeExport::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('status', ['ready', 'delivered'])
            ->count();
        if ($existingExports > 0) {
            $this->warn(sprintf(
                '%d fertige(r) Zeitexport(e) für %04d-%02d vorhanden — Exporte bleiben unverändert; neue Ergebnisse können abweichen.',
                $existingExports,
                $year,
                $month,
            ));
        }

        $results = 0;
        foreach ($userIds as $uid) {
            $acc = $engine->evaluateUserPeriod($orgId, $uid, $start, $end, $rules, null);
            $results += array_sum(array_map(static fn (array $row): int => count($row['sources']), $acc));
        }
        $persisted = TimeRuleResult::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereBetween('date', DateRange::days($start, $end))
            ->count();

        AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => null,
            'event' => 'rules.recalculated',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => [
                'year' => $year,
                'month' => $month,
                'users' => count($userIds),
                'results' => $persisted,
                'active_rules' => $rules->count(),
                'exports_present' => $existingExports,
            ],
        ]);

        $this->info(sprintf(
            'Neuberechnung %04d-%02d: %d Benutzer, %d Ergebnisse persistiert.',
            $year,
            $month,
            count($userIds),
            $persisted,
        ));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function resolveUserIds(int $orgId, CarbonImmutable $start, CarbonImmutable $end): array {
        $only = $this->option('user');
        if ($only !== null && $only !== '') {
            $user = User::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->find((int) $only);

            return $user instanceof User ? [(int) $user->id] : [];
        }

        return array_values(array_map('intval', Attendance::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereNotNull('ended_at')
            ->distinct()
            ->pluck('user_id')
            ->all()));
    }
}
