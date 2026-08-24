<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgeResolvedInboxItems.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Integration;

use App\Models\IntegrationInboxItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention-Cleanup der generischen Integrations-Inbox (MVP-103): entfernt
 * ABGESCHLOSSENE Einträge (zugeordnet/neu angelegt/lokal/remote/verworfen), deren
 * Auflösung länger als die Aufbewahrungsfrist zurückliegt. Offene Einträge
 * (`status = open`) werden NIE automatisch gelöscht — sie brauchen eine
 * Entscheidung. Idempotent, mandantenübergreifend (Wartungslauf ohne
 * Org-Kontext), Default-Frist aus config, per `--days` überschreibbar.
 */
class PurgeResolvedInboxItems extends Command {
    protected $signature = 'integration:purge-inbox
        {--days= : Aufbewahrungsfrist in Tagen (Default aus config/integration.php (INTEGRATION_INBOX_RETENTION_DAYS), 90)}';

    protected $description = 'Entfernt abgeschlossene Integrations-Inbox-Einträge, deren Auflösung älter als die Aufbewahrungsfrist ist.';

    /** Abgeschlossene (nicht mehr offene) Zustände, die gepurgt werden dürfen. */
    private const RESOLVED_STATUSES = [
        IntegrationInboxItem::STATUS_RESOLVED_LINKED,
        IntegrationInboxItem::STATUS_RESOLVED_CREATED,
        IntegrationInboxItem::STATUS_RESOLVED_LOCAL,
        IntegrationInboxItem::STATUS_RESOLVED_REMOTE,
        IntegrationInboxItem::STATUS_DISMISSED,
    ];

    public function handle(): int {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('integration.inbox_retention_days', 90);

        if ($days <= 0) {
            $this->info('Aufbewahrung unbegrenzt (retention_days <= 0) – nichts zu löschen.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->whereIn('status', self::RESOLVED_STATUSES)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', $cutoff)
            ->delete();

        $this->info("Gelöscht: {$deleted} abgeschlossene Inbox-Einträge älter als {$days} Tage.");

        return self::SUCCESS;
    }
}
