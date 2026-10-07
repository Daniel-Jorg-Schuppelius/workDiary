<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketIntakeTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Intake;

use App\Enums\Customer\IntakeKind;
use App\Enums\ServiceTicket\{ServiceTicketSource, ServiceTicketStatus};
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\{Organization, User};
use App\Models\Procurement\RequestItem;
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket};
use App\Services\Attachments\FileAttacher;
use App\Services\Customer\Contracts\IntakeHandoverTarget;
use App\Services\Customer\Dto\IntakeStage;
use App\Services\Fields\FieldDocument;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\ServiceTicket\{ServiceRequestService, ServiceTicketService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * IT-Ziel der Auftragsübernahme (MVP-1077): erst nach angenommenem Angebot
 * entsteht das Ticket — mit Katalogeintrag über den Service-Request
 * (Genehmigung und Fulfillment wie bei der Bestellung), sonst als
 * Portal-Ticket. Kundensichtbare Eingangsdateien wandern als Ticket-Anhänge
 * mit; die Kundensicht folgt dem Ticketstatus, nicht „erfüllt" des Requests.
 */
class ServiceTicketIntakeTarget implements IntakeHandoverTarget {
    private const HELPDESK = 'module.helpdesk';

    private const SERVICE_DESK = 'module.service_desk';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly ServiceRequestService $requests,
        private readonly ServiceTicketService $tickets,
        private readonly FileAttacher $attacher,
    ) {}

    public function kind(): IntakeKind {
        return IntakeKind::It;
    }

    public function isAvailable(Organization $organization): bool {
        return $this->features->isEnabled(self::HELPDESK);
    }

    public function canCreate(User $actor): bool {
        return Gate::forUser($actor)->allows('create', ServiceTicket::class);
    }

    public function formView(): ?string {
        return null;
    }

    public function formData(CustomerIntake $intake): array {
        return [];
    }

    public function rules(): array {
        return [];
    }

    public function handOver(CustomerIntake $intake, User $actor, array $input): Model {
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($intake->organization_id);
        $requester = $intake->submitter;
        $item = $intake->request_item_id !== null && $this->features->isEnabled(self::SERVICE_DESK)
            ? RequestItem::query()->withoutGlobalScopes()->where('organization_id', $intake->organization_id)->find($intake->request_item_id)
            : null;

        if ($item !== null) {
            // Angebot ist angenommen: jetzt darf der Request seine Kette bzw. sein Fulfillment durchlaufen.
            $request = $this->requests->submit($item, $requester ?? $actor, $intake->catalog_form?->values->toArray() ?? [], viaPortal: $requester !== null);
            $ticket = $request->ticket()->firstOrFail();
            $ticket->forceFill(['description' => $this->description($intake)])->save();
        } else {
            $ticket = $this->tickets->create($organization, null, [
                'title' => $intake->subject,
                'description' => $this->description($intake),
                'customer_id' => $intake->customer_id,
                'asset_id' => $intake->asset_id,
                'queue_id' => ServiceQueue::query()->where('organization_id', $intake->organization_id)->where('visibility', 'portal')->value('id'),
                'source' => ServiceTicketSource::CustomerPortal->value,
            ]);
            if ($requester !== null) {
                $ticket->forceFill(['requester_type' => $requester->getMorphClass(), 'requester_id' => $requester->id])->save();
            }
        }

        foreach ($intake->attachments()->where('customer_visible', true)->get() as $attachment) {
            $this->attacher->copy($attachment, $ticket, $attachment->user_id, [
                'organization_id' => $ticket->organization_id,
                'customer_visible' => true,
            ]);
        }
        $ticket->audit('service_ticket.opened_from_intake', ['customer_intake' => $intake->number, 'quote_id' => $intake->quote_id]);

        return $ticket;
    }

    public function customerStage(Model $target): IntakeStage {
        /** @var ServiceTicket $target */
        $stage = fn (string $key, string $tone, ?string $next = null): IntakeStage => new IntakeStage(
            (string) __('customer_intake.it_target.stage.' . $key),
            $tone,
            $next !== null ? (string) __('customer_intake.it_target.next_step.' . $next) : null,
            $next !== null,
        );

        return match ($target->status) {
            ServiceTicketStatus::Reported, ServiceTicketStatus::Triaged, ServiceTicketStatus::Scheduled => $stage('scheduled', 'info'),
            ServiceTicketStatus::InProgress, ServiceTicketStatus::WaitingExternal, ServiceTicketStatus::Paused => $stage('in_progress', 'primary'),
            ServiceTicketStatus::WaitingCustomer => $stage('waiting_customer', 'warning', 'reply'),
            ServiceTicketStatus::Done => $stage('done', 'success', 'confirm'),
            ServiceTicketStatus::Accepted, ServiceTicketStatus::Closed => $stage('closed', 'success'),
            ServiceTicketStatus::Rejected => $stage('rejected', 'error'),
        };
    }

    public function targetLabel(Model $target): string {
        /** @var ServiceTicket $target */
        return (string) __('customer_intake.it_target.label', ['number' => $target->ticket_no]);
    }

    public function internalUrl(Model $target, User $viewer): ?string {
        return Gate::forUser($viewer)->allows('view', $target) ? route('service-tickets.show', $target) : null;
    }

    public function internalPanelView(): ?string {
        return null;
    }

    public function portalPanelView(): ?string {
        return 'customer.tickets._intake_panel';
    }

    /** Ticketbeschreibung aus Eingang: Freitext und eingefrorene Angaben, ohne Abtippen. */
    private function description(CustomerIntake $intake): string {
        $lines = [(string) __('customer_intake.it_target.origin', ['number' => $intake->number])];
        if ($intake->description !== null) {
            $lines[] = '';
            $lines[] = $intake->description;
        }
        foreach ([$intake->form, $intake->catalog_form] as $document) {
            if (! $document instanceof FieldDocument) {
                continue;
            }
            foreach ($document->schema->visibleFor($document->values->toArray()) as $field) {
                if ($field->type->hasValue() && ! $document->values->isEmpty($field)) {
                    $lines[] = $field->label . ': ' . $document->values->display($field);
                }
            }
        }

        return implode("\n", $lines);
    }
}
