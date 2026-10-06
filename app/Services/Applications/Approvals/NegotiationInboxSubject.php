<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NegotiationInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications\Approvals;

use App\Models\Applications\{ApplicationContractNegotiation, JobApplication};
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Services\Applications\ContractNegotiationService;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Support\EntityType;
use Illuminate\Database\Eloquent\Model;

/**
 * Freigabestufen der Vertragsverhandlungen im Genehmigungs-Eingang
 * (Entscheidung 2026-10-06); die Freigabe an der Akte bleibt.
 */
final class NegotiationInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly ContractNegotiationService $negotiations) {}

    public function approvableClass(): string {
        return ApplicationContractNegotiation::class;
    }

    public function eagerLoad(): array {
        return ['negotiable'];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof ApplicationContractNegotiation && ! $approvable->isDecided();
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        if (! $approvable instanceof ApplicationContractNegotiation) {
            return new ApprovalInboxEntry('—');
        }
        $parent = $approvable->negotiable;
        $url = null;
        if ($parent !== null && $viewer->can('view', $approvable)) {
            $url = $parent instanceof JobApplication
                ? route('recruiting.applications.show', $parent)
                : route('tenders.show', $parent);
        }

        return new ApprovalInboxEntry(
            (string) __('Vertragsverhandlung: :title', ['title' => $approvable->title]),
            $url,
            type: EntityType::label($approvable->negotiable_type),
        );
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $this->negotiations->decide($approval, $actor, $decision, $reason, $delegateUserId);
    }
}
