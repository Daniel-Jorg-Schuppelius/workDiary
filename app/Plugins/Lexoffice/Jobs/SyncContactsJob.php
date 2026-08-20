<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncContactsJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Jobs;

use App\Models\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeContactSync, LexofficeMatchPolicy, LexofficeNumberAuthority};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Kontakt-Pull-Sync EINER Organisation als Queue-Job (Audit 2026-08,
 * Welle 1.3) — Impuls-Ziel des Webhook-Empfängers (contact.created/changed).
 * Parameter kommen aus den Plugin-Einstellungen (wie beim
 * `lexoffice:sync-contacts`-Command); {@see ShouldBeUnique} dedupliziert
 * Event-Bursts je Organisation.
 */
class SyncContactsJob implements ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $organizationId) {}

    public function uniqueId(): string {
        return (string) $this->organizationId;
    }

    public function handle(LexofficeContactSync $sync, LexofficeNumberAuthority $numberAuthority): void {
        $config = LexofficeConfig::resolve($this->organizationId);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return;
        }

        $organization = Organization::query()->find($this->organizationId);
        if ($organization === null) {
            return;
        }

        $numberAuthority->apply($organization, (bool) $config['number_authority']);

        // Staging hat Vorrang vor create_missing_local (wie im Command) —
        // Webhook-getriebene Läufe legen nie still lokale Kontakte an.
        $sync->sync(
            $organization,
            LexofficeMatchPolicy::fromSetting((string) $config['match_policy']),
            $config['api_key'],
            $config['base_url'],
            false,
            'both',
            true,
        );
    }
}
