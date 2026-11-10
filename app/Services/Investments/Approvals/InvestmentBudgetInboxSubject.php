<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentBudgetInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments\Approvals;

use App\Enums\Investments\InvestmentBudgetRequestStatus;
use App\Models\Approval\Approval;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase};
use App\Models\Platform\User;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Services\Investments\InvestmentService;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Budgetanträge der Investitionsplanung im Genehmigungs-Eingang (MVP-1522).
 * Entschieden wird auf dem Weg der Akte: dasselbe Freigaberecht, dieselbe
 * Selbstfreigabe- und Vier-Augen-Sperre.
 */
final class InvestmentBudgetInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly InvestmentService $investments) {}

    public function approvableClass(): string {
        return InvestmentBudgetRequest::class;
    }

    public function eagerLoad(): array {
        return ['investmentCase'];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof InvestmentBudgetRequest && $approvable->status === InvestmentBudgetRequestStatus::InApproval;
    }

    public function requesterId(Model $approvable): ?int {
        return $approvable instanceof InvestmentBudgetRequest && $approvable->requested_by !== null ? (int) $approvable->requested_by : null;
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        $case = $approvable instanceof InvestmentBudgetRequest ? $approvable->investmentCase : null;
        if (! $case instanceof InvestmentCase) {
            return new ApprovalInboxEntry('—');
        }

        return new ApprovalInboxEntry(
            (string) __('investment.approval.inbox_title', ['title' => (string) $case->title]),
            $viewer->can('view', $case) ? route('investments.show', $case) : null,
            (string) __('investment.approval.inbox_reference', [
                'version' => $approvable->version,
                'amount' => DocumentNumber::money((string) $approvable->amount),
            ]),
        );
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $request = $approval->approvable;
        $case = $request instanceof InvestmentBudgetRequest ? $request->investmentCase : null;
        if (! $case instanceof InvestmentCase) {
            throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
        }
        Gate::forUser($actor)->authorize('approve', $case);
        // Ein Delegat ohne Freigaberecht könnte die Stufe nie entscheiden.
        if ($decision === 'delegated') {
            $delegate = $delegateUserId !== null ? User::query()->find($delegateUserId) : null;
            if (! $delegate instanceof User || Gate::forUser($delegate)->denies('approve', $case)) {
                throw new \InvalidArgumentException((string) __('investment.approval.delegate_without_right'));
            }
        }

        $this->investments->decide($approval, $actor, $decision, $reason, $delegateUserId);
    }
}
