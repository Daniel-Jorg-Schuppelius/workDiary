<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoPruneCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Demo;

use App\Models\{AuditLog, Organization};
use App\Services\OrganizationLifecycleService;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Räumt Demo-Organisationen nach der Aufbewahrungsfrist ab (MVP-836):
 * deaktivieren, dann endgültig löschen. Wirkt ausschließlich auf
 * `is_demo`-Organisationen, deren letzter Seed älter als die Frist ist.
 * Ohne konfigurierte Frist (`demo.retention_days`) und ohne `--days` ist
 * der Lauf ein No-Op — eine Demo verschwindet nie aus Versehen.
 */
class DemoPruneCommand extends Command {
    protected $signature = 'demo:prune
        {--days= : Aufbewahrung in Tagen (Default: demo.retention_days; leer = aus)}
        {--dry-run : Nur auflisten, nichts löschen}';

    protected $description = 'Löscht Demo-Organisationen nach Ablauf der Aufbewahrungsfrist endgültig (nur is_demo).';

    public function handle(OrganizationLifecycleService $lifecycle): int {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : config('demo.retention_days');

        if ($days === null) {
            $this->info('Keine Aufbewahrungsfrist konfiguriert (DEMO_RETENTION_DAYS) — nichts zu tun.');

            return self::SUCCESS;
        }
        if ((int) $days < 1) {
            $this->error('Die Aufbewahrungsfrist muss mindestens 1 Tag betragen.');

            return self::FAILURE;
        }

        $cutoff = CarbonImmutable::now()->subDays((int) $days);
        $candidates = Organization::query()
            ->where('is_demo', true)
            ->whereNotNull('demo_seeded_at')
            ->where('demo_seeded_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info(sprintf('Keine Demo-Organisation älter als %d Tage (Stichtag %s).', (int) $days, $cutoff->toDateString()));

            return self::SUCCESS;
        }

        foreach ($candidates as $organization) {
            if ((bool) $this->option('dry-run')) {
                $this->line(sprintf('Würde löschen: %s (#%d, Seed %s).', $organization->name, $organization->id, $organization->demo_seeded_at?->toDateString() ?? '-'));

                continue;
            }

            // Der Audit-Trail überdauert den Purge (audit_logs bleiben) — deshalb
            // VOR dem Löschen schreiben, damit der Eintrag die Organisation nennt.
            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'user_id' => null,
                'event' => 'demo.pruned',
                'auditable_type' => MorphMap::stableKey(Organization::class),
                'auditable_id' => $organization->id,
                'changes' => [
                    'name' => $organization->name,
                    'demo_seeded_at' => $organization->demo_seeded_at?->toIso8601String(),
                    'retention_days' => (int) $days,
                ],
            ]);

            $lifecycle->deactivate($organization, null);
            $lifecycle->purge($organization, null);

            $this->info(sprintf('Gelöscht: %s (#%d).', $organization->name, $organization->id));
        }

        return self::SUCCESS;
    }
}
