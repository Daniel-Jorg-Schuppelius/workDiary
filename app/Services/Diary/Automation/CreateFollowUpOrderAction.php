<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateFollowUpOrderAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diary\Automation;

use App\Automation\Actions\RuleAction;
use App\Exceptions\ClassificationRequirementException;
use App\Models\Diary\OpenIssue;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureDeviation;
use App\Services\Diary\FollowUpOrderService;
use App\Services\OpenIssue\Automation\OpenIssueCreatedTrigger;
use App\Services\Procedure\Automation\DeviationRecordedTrigger;
use Illuminate\Database\Eloquent\Model;

/**
 * Folgeauftrag per Automationsregel (MVP-880/881). Der Auftrag gehört dem
 * Zuständigen, sonst dem Ersteller; Pflichtklassifikationen blockieren wie
 * im Dialog, der Grund steht dann im Regelprotokoll.
 */
final class CreateFollowUpOrderAction implements RuleAction {
    public const TYPE = 'diary.follow_up';

    public function __construct(private readonly FollowUpOrderService $followUps) {}

    public function type(): string {
        return self::TYPE;
    }

    public function label(): string {
        return (string) __('automation.action.diary_follow_up');
    }

    /** @return list<string> */
    public function triggers(): array {
        return [OpenIssueCreatedTrigger::KEY, DeviationRecordedTrigger::KEY];
    }

    /** @param array<string, mixed> $params */
    public function execute(Model $subject, array $params): array {
        try {
            if ($subject instanceof OpenIssue) {
                $creator = $subject->creator;
                $owner = $subject->assignee ?? $creator;
                if (! $owner instanceof User || ! $creator instanceof User) {
                    return ['skipped' => 'no_owner'];
                }
                $entry = $this->followUps->createForOpenIssue($subject, $owner, $creator, automated: true);

                return ['diary_entry_id' => (int) $entry->id, 'owner_user_id' => (int) $owner->id];
            }

            if ($subject instanceof ProcedureDeviation) {
                $owner = $subject->stepRun->run->assignee ?? $subject->createdBy;
                if (! $owner instanceof User) {
                    return ['skipped' => 'no_owner'];
                }
                $entry = $this->followUps->createForDeviation($subject, $owner);

                return ['diary_entry_id' => (int) $entry->id, 'owner_user_id' => (int) $owner->id];
            }
        } catch (ClassificationRequirementException $e) {
            return ['skipped' => 'classification_required', 'message' => $e->getMessage()];
        }

        return ['skipped' => 'unsupported_subject'];
    }
}
