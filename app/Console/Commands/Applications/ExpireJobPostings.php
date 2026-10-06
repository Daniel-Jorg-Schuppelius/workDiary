<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpireJobPostings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Applications;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Applications\JobPostingStatus;
use App\Models\Applications\JobPosting;
use Illuminate\Console\Command;

/**
 * Täglicher Lauf (Feature 068): Stellenanzeigen, deren Ablaufdatum oder
 * Bewerbungsschluss vorbei ist, wechseln auf „abgelaufen“. Registriert in
 * config/scheduler.php (recruiting.expire_postings).
 */
class ExpireJobPostings extends Command {
    use IteratesOrganizations;

    protected $signature = 'recruiting:expire-postings ' . self::ORGANIZATION_OPTION;

    protected $description = 'Setzt Stellenanzeigen nach Ablaufdatum oder Bewerbungsschluss auf „abgelaufen“.';

    public function handle(): int {
        // Welche Stände ablaufen dürfen, sagt allein die Übergangstabelle.
        $sources = array_values(array_filter(
            JobPostingStatus::cases(),
            static fn (JobPostingStatus $status): bool => $status->canTransitionTo(JobPostingStatus::Expired),
        ));
        $expired = 0;

        $failures = $this->forEachOrganization(function () use ($sources, &$expired): void {
            // Der Organisations-Scope begrenzt die Abfrage auf den gebundenen Mandanten.
            foreach (JobPosting::query()->whereIn('status', $sources)->pastDue()->orderBy('id')->get() as $posting) {
                $posting->update(['status' => JobPostingStatus::Expired]);
                $posting->audit('recruiting.posting_expired', [
                    'expires_at' => $posting->expires_at?->toDateString(),
                    'application_deadline' => $posting->application_deadline?->toDateString(),
                ]);
                $expired++;
            }
        });

        $this->info("{$expired} Stellenanzeige(n) abgelaufen.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
