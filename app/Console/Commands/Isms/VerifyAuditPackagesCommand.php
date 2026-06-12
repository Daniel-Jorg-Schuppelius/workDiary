<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VerifyAuditPackagesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Isms;

use App\Enums\Isms\AuditPackageStatus;
use App\Models\Isms\IsmsAuditPackage;
use App\Services\Isms\AuditPackageService;
use Illuminate\Console\Command;

/**
 * Integritätsprüfung aller finalisierten Auditpakete (Feature 046,
 * Inkrement E): vergleicht den bei der Finalisierung persistierten
 * SHA-256 (file_hash) mit dem aktuellen Hash der abgelegten Datei.
 * Exit 0 = alle Pakete unverändert, Exit 1 = mindestens ein Paket
 * manipuliert oder Datei fehlt (046-Akzeptanzkriterium „gegen
 * nachträgliche unbemerkte Änderung geschützt").
 *
 * Läuft ohne Mandantenkontext über alle Organisationen (Cron/Monitoring);
 * optional auf eine Organisation begrenzbar.
 */
class VerifyAuditPackagesCommand extends Command {
    protected $signature = 'isms:verify-packages
        {--org= : Nur Pakete dieser Organisations-ID prüfen}';

    protected $description = 'Prüft die Integrität (SHA-256) aller finalisierten ISMS-Auditpakete.';

    public function handle(AuditPackageService $service): int {
        $org = $this->option('org');

        $packages = IsmsAuditPackage::query()
            ->withoutGlobalScopes() // TENANT-BYPASS: Konsolen-Integritätslauf über alle Organisationen (analog audit:verify)
            ->whereNull('deleted_at')
            ->where('status', AuditPackageStatus::Finalized->value)
            ->when(is_string($org) && $org !== '', fn($query) => $query->where('organization_id', (int) $org))
            ->orderBy('organization_id')
            ->orderBy('package_no')
            ->get();

        if ($packages->isEmpty()) {
            $this->info('Keine finalisierten Auditpakete vorhanden.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($packages as $package) {
            if ($service->verify($package)) {
                $this->line(sprintf('OK       Org %d %s — %s', (int) $package->organization_id, $package->displayNo(), (string) $package->file_path));

                continue;
            }

            $failed++;
            $this->error(sprintf('MISMATCH Org %d %s — %s', (int) $package->organization_id, $package->displayNo(), (string) $package->file_path));
        }

        if ($failed > 0) {
            $this->error(sprintf('%d von %d Auditpaket(en) sind manipuliert oder die Datei fehlt.', $failed, $packages->count()));

            return self::FAILURE;
        }

        $this->info(sprintf('Alle %d Auditpaket(e) sind unverändert.', $packages->count()));

        return self::SUCCESS;
    }
}
