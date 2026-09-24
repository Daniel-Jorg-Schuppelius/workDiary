<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\ProblemStatus;
use App\Models\Knowledge\ContentReference;
use App\Models\Platform\User;
use App\Models\ServiceTicket\{Problem, ServiceTicket};
use App\Services\Concerns\AssertsStatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * Problem-Management (Feature 065, MVP-156): Übergangsmatrix (Muster
 * ServiceTicketStatus), Eröffnung aus Incidents, Known-Error →
 * Wissensartikel (idempotent über den Verweis (ContentReference)), Wirksamkeits-
 * prüfung mit Frist. Incidents schließen Probleme NIE automatisch —
 * es gibt bewusst keinerlei Kopplungs-Code.
 */
class ProblemService {
    use AssertsStatusTransition;

    /**
     * Problem aus einem oder mehreren Incidents eröffnen (Pivot-Verknüpfung).
     *
     * @param array<int, ServiceTicket> $tickets
     */
    public function openFromIncidents(array $tickets, string $title, User $actor, ?string $description = null): Problem {
        if ($tickets === []) {
            throw new \InvalidArgumentException('Mindestens ein Incident erforderlich.');
        }

        return DB::transaction(function () use ($tickets, $title, $actor, $description): Problem {
            $first = $tickets[0];
            $problem = Problem::query()->create([
                'organization_id' => $first->organization_id,
                'title' => $title,
                'description' => $description,
                'owner_id' => $actor->id,
            ]);

            foreach ($tickets as $ticket) {
                if ((int) $ticket->organization_id !== (int) $problem->organization_id) {
                    throw new \RuntimeException((string) __('Verknüpfung über Organisationsgrenzen ist nicht zulässig.'));
                }
                $problem->tickets()->syncWithoutDetaching([$ticket->id]);
            }

            $problem->audit('problem.opened', ['tickets' => array_map(fn(ServiceTicket $t): int => (int) $t->id, $tickets)]);

            return $problem->refresh();
        });
    }

    public function transition(Problem $problem, ProblemStatus|string $to, ?User $actor = null, ?\DateTimeInterface $effectivenessDue = null): Problem {
        $to = $to instanceof ProblemStatus ? $to : (ProblemStatus::tryFrom($to) ?? throw new \InvalidArgumentException("Unbekannter Problem-Status: {$to}"));
        if ($problem->status !== $to) {
            $this->assertStatusTransition($problem->status, $to);
        }

        $payload = ['status' => $to];
        if ($to === ProblemStatus::Resolved) {
            // Wirksamkeitsprüfung: Frist Pflicht beim Lösen (Scanner-Hook).
            if ($effectivenessDue === null) {
                throw new \InvalidArgumentException((string) __('Lösen braucht eine Frist für die Wirksamkeitsprüfung.'));
            }
            $payload['effectiveness_check_due_at'] = $effectivenessDue;
        }

        $problem->update($payload);
        $problem->audit('problem.status_changed', ['to' => $to->value, 'actor' => $actor?->id]);

        return $problem->refresh();
    }

    public function recordEffectiveness(Problem $problem, User $actor, string $result): Problem {
        $problem->update([
            'effectiveness_checked_at' => now(),
            'effectiveness_result' => trim($result),
        ]);
        $problem->audit('problem.effectiveness_checked', ['actor' => $actor->id]);

        return $problem->refresh();
    }

    /**
     * Known Error → Wissensartikel über den Contract KnownErrorPublisher
     * (Wissensmodul); idempotent über den Verweis (ContentReference)
     * (linkable=Problem): ein zweiter Aufruf liefert den bestehenden Artikel.
     */
    public function publishKnownError(Problem $problem, User $actor): \App\Models\Knowledge\KnowledgeArticle {
        $existing = $this->knownErrorLink($problem);
        if ($existing !== null) {
            return \App\Models\Knowledge\KnowledgeArticle::query()->findOrFail($existing->source_id);
        }

        return DB::transaction(function () use ($problem, $actor): \App\Models\Knowledge\KnowledgeArticle {
            $article = app(\App\Services\ServiceTicket\Contracts\KnownErrorPublisher::class)->publish($problem, $actor);

            $article->links()->create([
                'organization_id' => $article->organization_id,
                'target_type' => $problem->getMorphClass(),
                'target_id' => $problem->id,
                'kind' => ContentReference::KIND_LINKED,
                'created_by' => $actor->id,
            ]);

            $problem->audit('problem.known_error_published', ['article' => $article->id]);

            return $article;
        });
    }

    /** Verknüpfung des Known-Error-Artikels mit dem Problem, falls es sie gibt. */
    public function knownErrorLink(Problem $problem): ?ContentReference {
        return ContentReference::query()
            ->where('source_type', (new \App\Models\Knowledge\KnowledgeArticle)->getMorphClass())
            ->where('kind', ContentReference::KIND_LINKED)
            ->where('target_type', $problem->getMorphClass())
            ->where('target_id', $problem->id)
            ->first();
    }
}
