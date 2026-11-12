<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Diary;

use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Diary\Contracts\IssueTicketOpener;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Support\Facades\Gate;

/** Offener Punkt → Ticket mit Quelle „Offener Punkt“; der Bezug des Punkts wandert mit. */
final class OpenIssueTicketOpener implements IssueTicketOpener {
    private const string REFERENCE_PREFIX = 'open_issue:';

    public function __construct(
        private readonly ServiceTicketService $tickets,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function available(User $actor): bool {
        return $this->features->routeEnabled('service-tickets.show') && Gate::forUser($actor)->allows('create', ServiceTicket::class);
    }

    public function open(OpenIssue $issue, User $actor): ?array {
        if (! $this->available($actor)) {
            return null;
        }
        $existing = $this->query([(int) $issue->id], (int) $issue->organization_id)->first();
        if ($existing !== null) {
            return [...$this->link($existing, $actor), 'created' => false];
        }

        $subject = $issue->subject;
        $ticket = $this->tickets->create(Organization::query()->findOrFail($issue->organization_id), $actor, [
            'title' => $issue->title,
            'description' => $issue->description,
            'priority' => match ($issue->severity) {
                OpenIssueSeverity::Low => ServiceTicketPriority::Low->value,
                OpenIssueSeverity::Medium => ServiceTicketPriority::Normal->value,
                OpenIssueSeverity::High => ServiceTicketPriority::High->value,
                OpenIssueSeverity::Critical => ServiceTicketPriority::Urgent->value,
            },
            'source' => ServiceTicketSource::OpenIssue->value,
            'source_reference' => self::REFERENCE_PREFIX . $issue->id,
            'diary_entry_id' => $subject instanceof DiaryEntry ? $subject->id : null,
            'asset_id' => $subject instanceof Asset ? $subject->id : null,
            'project_id' => $subject instanceof Project ? $subject->id : null,
            'customer_id' => match (true) {
                $subject instanceof Customer => $subject->id,
                $subject instanceof Asset, $subject instanceof Project => $subject->customer_id,
                default => null,
            },
        ]);

        return [...$this->link($ticket, $actor), 'created' => true];
    }

    public function linksFor(array $issueIds, User $viewer): array {
        if ($issueIds === [] || $viewer->organization_id === null || ! $this->features->routeEnabled('service-tickets.show')) {
            return [];
        }
        $links = [];
        foreach ($this->query($issueIds, (int) $viewer->organization_id)->get() as $ticket) {
            $links[(int) substr((string) $ticket->source_reference, strlen(self::REFERENCE_PREFIX))] = $this->link($ticket, $viewer);
        }

        return $links;
    }

    /**
     * @param  list<int>  $issueIds
     * @return \Illuminate\Database\Eloquent\Builder<ServiceTicket>
     */
    private function query(array $issueIds, int $organizationId): \Illuminate\Database\Eloquent\Builder {
        return ServiceTicket::query()
            ->where('organization_id', $organizationId)
            ->where('source', ServiceTicketSource::OpenIssue->value)
            ->whereIn('source_reference', array_map(static fn (int $id): string => self::REFERENCE_PREFIX . $id, $issueIds))
            ->orderBy('id');
    }

    /** @return array{label: string, url: string|null} */
    private function link(ServiceTicket $ticket, User $viewer): array {
        return [
            'label' => $ticket->ticket_no,
            'url' => Gate::forUser($viewer)->allows('view', $ticket) ? route('service-tickets.show', $ticket) : null,
        ];
    }
}
