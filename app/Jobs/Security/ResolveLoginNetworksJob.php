<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveLoginNetworksJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Security;

use App\Models\Auth\OrganizationLoginPolicy;
use App\Services\Auth\LoginPolicy\LoginNetworks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Löst die DNS-Namen der Büronetze einer Organisation nach dem Speichern der
 * Anmelderichtlinie auf (Feature 205, Sicherheitsprüfung M3) — außerhalb der
 * Anfrage, die gespeichert hat; danach übernimmt der Scheduler.
 */
class ResolveLoginNetworksJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Zehn Namen à drei Sekunden plus Reserve. */
    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(private readonly int $organizationId) {}

    public function handle(LoginNetworks $networks): void {
        $policy = OrganizationLoginPolicy::forOrganization($this->organizationId);
        $networks->refresh($this->organizationId, $policy?->networkEntries() ?? []);
    }
}
