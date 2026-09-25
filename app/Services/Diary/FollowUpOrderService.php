<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FollowUpOrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diary;

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Diary\Status;
use App\Exceptions\ClassificationRequirementException;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureDeviation;
use App\Models\Project\Project;
use App\Services\Classification\ClassificationRequirementValidator;
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Folgeauftrag aus offenem Punkt oder Prozedur-Abweichung (Feature 139,
 * MVP-880/881): eine Stelle für Vorbelegung und Anlage, damit Dialog,
 * Automationsregel und Abweichung denselben Auftrag erzeugen.
 */
final class FollowUpOrderService {
    public function __construct(
        private readonly ClassificationRequirementValidator $requirements,
        private readonly OpenIssueService $openIssues,
    ) {}

    /** @return array{customerId: ?int, projectId: ?int, title: string, content: string} */
    public function prefillForOpenIssue(OpenIssue $issue): array {
        [$customerId, $projectId] = $this->anchor($issue->subject);
        $intro = (string) __('open-issue.follow_up.content_intro', ['id' => $issue->id, 'title' => $issue->title]);

        return [
            'customerId' => $customerId,
            'projectId' => $projectId,
            'title' => $issue->title,
            'content' => trim($intro . "\n\n" . (string) $issue->description),
        ];
    }

    /** @return array{customerId: ?int, projectId: ?int, title: string, content: string} */
    public function prefillForDeviation(ProcedureDeviation $deviation): array {
        $run = $deviation->stepRun?->run;
        [$customerId, $projectId] = $this->anchor($run?->subject);
        $step = (string) ($deviation->stepRun->stepDef->label ?? '');
        $title = (string) __('procedure.follow_up_order.title', ['step' => $step]);

        return [
            'customerId' => $customerId,
            'projectId' => $projectId,
            'title' => mb_substr($title, 0, 200),
            'content' => trim((string) __('procedure.follow_up_order.content_intro', ['id' => $deviation->id, 'step' => $step]) . "\n\n" . (string) $deviation->reason_text),
        ];
    }

    /**
     * Genau ein Folgeauftrag je Punkt: ein bereits verknüpfter Auftrag wird
     * zurückgegeben statt verdoppelt.
     *
     * @throws ClassificationRequirementException
     */
    public function createForOpenIssue(OpenIssue $issue, User $owner, User $actor, bool $automated = false): DiaryEntry {
        $existing = $issue->follow_up_diary_entry_id !== null ? DiaryEntry::query()->find($issue->follow_up_diary_entry_id) : null;
        if ($existing instanceof DiaryEntry) {
            return $existing;
        }

        return DB::transaction(function () use ($issue, $owner, $actor, $automated): DiaryEntry {
            $entry = $this->create($owner, $this->prefillForOpenIssue($issue));
            $this->openIssues->linkFollowUp($issue, $entry, $actor, $automated);

            return $entry;
        });
    }

    /** @throws ClassificationRequirementException */
    public function createForDeviation(ProcedureDeviation $deviation, User $owner): DiaryEntry {
        $existing = $deviation->follow_up_diary_entry_id !== null ? DiaryEntry::query()->find($deviation->follow_up_diary_entry_id) : null;
        if ($existing instanceof DiaryEntry) {
            return $existing;
        }

        $entry = $this->create($owner, $this->prefillForDeviation($deviation));
        $deviation->forceFill(['follow_up_diary_entry_id' => $entry->id])->save();

        return $entry;
    }

    /**
     * Pflichtklassifikationen blockieren wie im Dialog vor dem Speichern.
     *
     * @param array{customerId: ?int, projectId: ?int, title: string, content: string} $prefill
     * @throws ClassificationRequirementException
     */
    private function create(User $owner, array $prefill): DiaryEntry {
        if ($owner->organization_id === null) {
            throw new \DomainException('follow-up owner without organization');
        }

        $data = [
            'customer_id' => $prefill['customerId'],
            'project_id' => $prefill['projectId'],
            'title' => $prefill['title'],
            'content' => $prefill['content'],
            'status' => Status::Planned->value,
        ];
        $candidate = new DiaryEntry($data);
        $candidate->organization_id = $owner->organization_id;
        $this->requirements->assertSatisfied($candidate, ClassificationRequirementPhase::OnCreate);

        /** @var DiaryEntry */
        return $owner->diaryEntries()->create($data);
    }

    /** @return array{0: ?int, 1: ?int} Kunde und Projekt aus dem Bezug */
    private function anchor(?Model $subject): array {
        [$customerId, $projectId] = match (true) {
            $subject instanceof DiaryEntry => [$subject->customer_id, $subject->project_id],
            $subject instanceof Project => [$subject->customer_id, $subject->id],
            $subject instanceof Customer => [$subject->id, null],
            $subject instanceof Asset => [$subject->customer_id, null],
            default => [null, null],
        };

        return [
            $customerId !== null ? (int) $customerId : null,
            $projectId !== null ? (int) $projectId : null,
        ];
    }
}
