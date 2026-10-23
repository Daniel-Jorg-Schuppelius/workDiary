<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalInbox.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Offene Stufen des Genehmigungs-Eingangs (Feature 065, MVP-154) — geteilt von
 * Eingang und Arbeitsvorrat: offene Schritte (decision null ODER question)
 * angemeldeter Gegenstände, je Gegenstand nur der NIEDRIGSTE offene Schritt
 * der geltenden Runde, gefiltert auf die Zuständigkeit.
 */
class ApprovalInbox {
    /** @var array<class-string, ApprovalInboxSubject>|null */
    private ?array $subjects = null;

    public function __construct(
        private readonly ApprovalResponsibility $responsibility,
        private readonly ModuleRegistry $modules,
    ) {}

    /** @return Collection<int, Approval> */
    public function openStepsFor(User $user): Collection {
        $subjects = $this->subjects();
        $steps = Approval::query()
            ->currentRound()
            ->whereIn('approvable_type', array_map(MorphMap::alias(...), array_keys($subjects)))
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            // Nach einer Ablehnung ist die Kette beendet — ihre offenen Stufen stehen nicht mehr zur Entscheidung.
            ->whereNotExists(fn($q) => $q->from('approvals as rejected')
                ->whereColumn('rejected.approvable_type', 'approvals.approvable_type')
                ->whereColumn('rejected.approvable_id', 'approvals.approvable_id')
                ->whereColumn('rejected.round', 'approvals.round')
                ->where('rejected.decision', 'rejected'))
            ->orderBy('step')
            ->orderBy('id')
            ->get()
            ->groupBy(fn(Approval $a): string => $a->approvable_type . ':' . $a->approvable_id)
            ->flatMap(function (Collection $steps): Collection {
                $lowest = (int) $steps->min('step');

                return $steps->filter(fn(Approval $a): bool => (int) $a->step === $lowest);
            })
            ->filter(fn(Approval $a): bool => $this->responsibility->isResponsible($a, $user))
            ->values();

        $steps = new EloquentCollection($steps->all());
        $steps->load('approvable');

        return $steps->toBase()
            ->filter(fn(Approval $a): bool => $a->approvable !== null && (bool) $this->subjectFor($a)?->awaitsDecision($a->approvable))
            ->values();
    }

    /** question-Schritte zählen nicht als erledigt — nur echte Entscheide. */
    public function isLowestOpenStep(Approval $approval): bool {
        return ! Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('round', $approval->round)
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->where('step', '<', $approval->step)
            ->exists();
    }

    /** @param EloquentCollection<int, Approval> $approvals */
    public function preload(EloquentCollection $approvals): void {
        $approvals->loadMissing('approvable');
        $approvals->loadMorph('approvable', array_map(
            static fn(ApprovalInboxSubject $subject): array => $subject->eagerLoad(),
            $this->subjects(),
        ));
    }

    public function entryFor(Approval $approval, User $viewer): ApprovalInboxEntry {
        $approvable = $approval->approvable;
        $subject = $this->subjectFor($approval);

        return $approvable === null || $subject === null
            ? new ApprovalInboxEntry('—')
            : $subject->present($approvable, $viewer);
    }

    public function subjectFor(Approval $approval): ?ApprovalInboxSubject {
        $class = MorphMap::classFor($approval->approvable_type);

        return $class === null ? null : ($this->subjects()[$class] ?? null);
    }

    /** @return array<class-string, ApprovalInboxSubject> Modellklasse → angemeldeter Gegenstand */
    private function subjects(): array {
        if ($this->subjects === null) {
            $this->subjects = [];
            foreach ($this->modules->extensions(ApprovalInboxSubject::class) as $class) {
                /** @var ApprovalInboxSubject $subject */
                $subject = app($class);
                $this->subjects[$subject->approvableClass()] = $subject;
            }
        }

        return $this->subjects;
    }
}
