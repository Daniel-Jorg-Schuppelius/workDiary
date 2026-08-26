<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\Retention;

use App\Models\{Organization, User};
use App\Models\Privacy\RetentionProposal;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Retention-Review (Restpunkt 66): der Scan erzeugt Lösch-VORSCHLÄGE für
 * fristüberfällige Datensätze (Ausnahme-Prädikate — z. B. GoBD — verhindern
 * Vorschläge); gelöscht wird erst nach zweistufiger Bestätigung
 * (approve → purge), jede Entscheidung auditiert.
 */
class RetentionScanService {
    public function __construct(private readonly RetentionRegistry $registry) {}

    /**
     * @return array{proposed: int, exempt: int}
     */
    public function scan(Organization $organization): array {
        $proposed = 0;
        $exempt = 0;

        foreach ($this->registry->policies() as $policy) {
            $cutoff = $this->registry->cutoffFor($organization, $policy->area);
            if ($cutoff === null) {
                continue; // Bereich ohne konfigurierte Frist
            }

            $basis = $this->registry->basisFor($organization, $policy->area);
            $query = ($policy->overdueQuery)($organization, $cutoff);

            foreach ($query->get() as $model) {
                $exemptReason = $policy->exempt !== null ? ($policy->exempt)($model) : null;
                if ($exemptReason !== null) {
                    $exempt++;

                    continue;
                }

                $proposal = RetentionProposal::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'area' => $policy->area,
                    'subject_type' => $model->getMorphClass(),
                    'subject_id' => (int) $model->getKey(),
                ], [
                    'retention_until' => $cutoff->toDateString(),
                    'reason' => trim(sprintf(
                        '%s (%s)',
                        __('Aufbewahrungsfrist abgelaufen'),
                        $basis ?? $policy->area,
                    )),
                    'status' => RetentionProposal::STATUS_PENDING,
                ]);
                if ($proposal->wasRecentlyCreated) {
                    $proposed++;
                }
            }
        }

        return ['proposed' => $proposed, 'exempt' => $exempt];
    }

    /** Erste Stufe: Vorschlag bestätigen (noch keine Löschung). */
    public function approve(RetentionProposal $proposal, User $actor): RetentionProposal {
        $this->assertStatus($proposal, RetentionProposal::STATUS_PENDING);

        $proposal->update([
            'status' => RetentionProposal::STATUS_APPROVED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $proposal->audit('retention.approved', ['area' => $proposal->area, 'subject' => $proposal->subject_type . '#' . $proposal->subject_id]);

        return $proposal;
    }

    public function reject(RetentionProposal $proposal, User $actor): RetentionProposal {
        $this->assertStatus($proposal, RetentionProposal::STATUS_PENDING);

        $proposal->update([
            'status' => RetentionProposal::STATUS_REJECTED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $proposal->audit('retention.rejected', ['area' => $proposal->area, 'subject' => $proposal->subject_type . '#' . $proposal->subject_id]);

        return $proposal;
    }

    /**
     * Zweite Stufe: endgültig löschen (nur bestätigte Vorschläge). Die
     * Audit-Zeile bleibt als Nachweis; der Datensatz selbst wird über die
     * Policy-Löschlogik (Default delete()) entfernt.
     */
    public function purge(RetentionProposal $proposal, User $actor): RetentionProposal {
        $this->assertStatus($proposal, RetentionProposal::STATUS_APPROVED);

        $policy = $this->registry->policy($proposal->area);

        /** @var Model|null $subject */
        $subject = $proposal->subject()->first();
        if ($subject !== null) {
            if ($policy?->purge !== null) {
                // Actor als zweites Argument (Feature 130): Anonymisierungs-
                // Policies auditieren den Bestätiger; Ein-Parameter-Closures
                // ignorieren das Extra-Argument.
                ($policy->purge)($subject, $actor);
            } else {
                $subject->delete();
            }
        }

        $proposal->update([
            'status' => RetentionProposal::STATUS_PURGED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $proposal->audit('retention.purged', [
            'area' => $proposal->area,
            'subject' => $proposal->subject_type . '#' . $proposal->subject_id,
            'existed' => $subject !== null,
        ]);

        return $proposal;
    }

    private function assertStatus(RetentionProposal $proposal, string $expected): void {
        if ($proposal->status !== $expected) {
            throw new RuntimeException("Vorschlag ist nicht im Status {$expected}.");
        }
    }
}
