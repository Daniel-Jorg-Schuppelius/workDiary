<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProposalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Enums\Investments\{InvestmentCaseStatus, InvestmentOrigin};
use App\Enums\Notification\NotificationEvent;
use App\Models\Investments\InvestmentCase;
use App\Models\Platform\{Organization, User};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Auth\OrganizationAccessToken;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;

/**
 * Investitionsvorschläge (MVP-936): von Mitarbeitenden ohne Investitionsrecht
 * oder über einen öffentlichen Link. Ein Vorschlag wird eine Akte im Status
 * „Idee“ mit Herkunft; geprüft und bewertet wird wie gewohnt in der Akte.
 */
class InvestmentProposalService extends OrganizationAccessToken {
    public const HASH_KEY = 'investment_proposal_token_hash';

    public const HINT_KEY = 'investment_proposal_token_hint';

    public const ISSUED_KEY = 'investment_proposal_token_issued_at';

    public const ENABLED_KEY = 'investment_proposal_enabled';

    public function __construct(private readonly NotificationDispatcher $notifier) {}

    /**
     * @param array{title: string, reason: string, category: string, urgency: string, estimated_amount?: ?string, currency?: ?string, submitter_name?: ?string, submitter_email?: ?string} $data
     */
    public function propose(Organization $organization, array $data, InvestmentOrigin $origin, ?User $submitter = null): InvestmentCase {
        $case = OrganizationContext::run($organization, fn (): InvestmentCase => DB::transaction(function () use ($organization, $data, $origin, $submitter): InvestmentCase {
            $case = InvestmentCase::query()->create([
                'organization_id' => $organization->id,
                'title' => $data['title'],
                'reason' => $data['reason'],
                'category' => $data['category'],
                'urgency' => $data['urgency'],
                'status' => InvestmentCaseStatus::Idea,
                'origin' => $origin->value,
                'submitter_user_id' => $submitter?->id,
                'submitter_name' => $submitter->name ?? ($data['submitter_name'] ?? null),
                'submitter_email' => $submitter->email ?? ($data['submitter_email'] ?? null),
                'estimated_amount' => $data['estimated_amount'] ?? null,
                'currency' => isset($data['estimated_amount']) ? ($data['currency'] ?? 'EUR') : null,
                'created_by' => $submitter?->id,
            ]);
            $case->audit('investment.proposed', ['origin' => $origin->value]);

            return $case;
        }));

        DB::afterCommit(fn () => $this->notifier->notify(NotificationEvent::InvestmentProposed, $case, null, [
            'title' => (string) __('investment.proposal.notification', ['title' => $case->title]),
            'title_key' => 'investment.proposal.notification',
            'title_params' => ['title' => $case->title],
            'message' => $origin->label() . ($case->submitter_name !== null ? ': ' . $case->submitter_name : ''),
            'url' => route('investments.show', $case),
        ]));

        return $case;
    }
}
