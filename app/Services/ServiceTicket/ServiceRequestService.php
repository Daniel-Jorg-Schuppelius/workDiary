<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketSource};
use App\Models\{Approval, DiaryEntry, Project, RequestItem, ServiceQueue, ServiceRequest, ServiceTicket, Task, User};
use Illuminate\Support\Facades\DB;

/**
 * Service-Requests (Feature 065, MVP-154): Einreichen friert Formular +
 * Katalogstand ein (Katalogänderung schreibt NIE um); Genehmigungskette
 * mit Selbstfreigabe-Sperre (Muster price_change_requests); Fulfillment
 * über schmale Adapter (task/project/diary/procedure), idempotent.
 */
class ServiceRequestService {
    /**
     * Sichtbarer Katalog (serverseitig gefiltert): org-Scope kommt aus den
     * Global Scopes; visibility.roles beschränkt zusätzlich auf Rollen
     * (Prüfung über User::hasRole — Rollen liegen relational).
     *
     * @return \Illuminate\Support\Collection<int, RequestItem>
     */
    public function visibleItems(User $user): \Illuminate\Support\Collection {
        return RequestItem::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->filter(function (RequestItem $item) use ($user): bool {
                $roles = (array) (($item->visibility ?? [])['roles'] ?? []);
                if ($roles === []) {
                    return true;
                }
                foreach ($roles as $role) {
                    if ($user->hasRole((string) $role)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Portal-Sicht auf den Katalog (Feature 065, MVP-154): nur aktive
     * Einträge mit visibility.portal; optional zusätzlich auf bestimmte
     * Kunden beschränkt (visibility.customer_ids). Serverseitig gefiltert —
     * das Portal bekommt nie mehr als seine Sicht.
     *
     * @return \Illuminate\Support\Collection<int, RequestItem>
     */
    public function visibleItemsForPortal(User $portalUser): \Illuminate\Support\Collection {
        return RequestItem::query()
            ->where('organization_id', (int) $portalUser->organization_id)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn(RequestItem $item): bool => $this->isPortalVisible($item, $portalUser))
            ->values();
    }

    /** Portal-Sichtbarkeitsregel für einen einzelnen Katalogeintrag. */
    public function isPortalVisible(RequestItem $item, User $portalUser): bool {
        if (! $item->active || (int) $item->organization_id !== (int) $portalUser->organization_id) {
            return false;
        }

        $visibility = (array) ($item->visibility ?? []);
        if (! (bool) ($visibility['portal'] ?? false)) {
            return false;
        }

        $customerIds = array_map('intval', (array) ($visibility['customer_ids'] ?? []));

        return $customerIds === [] || in_array((int) $portalUser->customer_id, $customerIds, true);
    }

    /**
     * Request einreichen: Ticket (kind=service_request) + Request mit
     * eingefrorenen Snapshots + Genehmigungsschritte (leer → direkt approved).
     *
     * Portal-Kontext (`viaPortal`, MVP-154): Ticket landet in der Portal-
     * Queue mit Source customer_portal und Kundenbezug; der Portal-User wird
     * als requester am Ticket verankert (Muster CustomerPortal/TicketController).
     *
     * @param array<string, mixed> $formAnswers
     */
    public function submit(RequestItem $item, User $requester, array $formAnswers = [], bool $viaPortal = false): ServiceRequest {
        if (! $item->active) {
            throw new \RuntimeException((string) __('Der Katalogeintrag ist nicht aktiv.'));
        }

        return DB::transaction(function () use ($item, $requester, $formAnswers, $viaPortal): ServiceRequest {
            $payload = [
                'title' => $item->name,
                'description' => $item->description,
                'kind' => ServiceTicketKind::ServiceRequest->value,
                'sla_contract_id' => $item->sla_contract_id,
            ];

            if ($viaPortal) {
                $payload['customer_id'] = $requester->customer_id;
                $payload['source'] = ServiceTicketSource::CustomerPortal->value;
                $portalQueueId = ServiceQueue::query()
                    ->where('organization_id', $item->organization_id)
                    ->where('visibility', 'portal')
                    ->value('id');
                if ($portalQueueId !== null) {
                    $payload['queue_id'] = (int) $portalQueueId;
                }
            }

            $ticket = app(ServiceTicketService::class)->create($item->organization()->firstOrFail(), $requester, $payload);

            if ($viaPortal) {
                $ticket->forceFill([
                    'requester_type' => $requester->getMorphClass(),
                    'requester_id' => $requester->id,
                ])->save();
            }

            $chain = array_values((array) ($item->approval_chain ?? []));
            $request = ServiceRequest::query()->create([
                'organization_id' => $item->organization_id,
                'service_ticket_id' => $ticket->id,
                'request_item_id' => $item->id,
                'form_snapshot' => [
                    'form_template_id' => $item->form_template_id,
                    'fields' => $item->formTemplate?->fields,
                    'answers' => $formAnswers,
                ],
                'catalog_snapshot' => [
                    'request_item_id' => $item->id,
                    'name' => $item->name,
                    'version' => $item->version,
                    'fulfillment' => $item->fulfillment,
                    'fulfillment_config' => $item->fulfillment_config,
                    'approval_chain' => $chain,
                ],
                'status' => $chain === [] ? ServiceRequest::STATUS_APPROVED : ServiceRequest::STATUS_PENDING,
            ]);

            app(ApprovalService::class)->createChain($request, $chain);

            $request->audit('service_request.submitted', ['item' => $item->id, 'version' => $item->version]);

            if ($request->status === ServiceRequest::STATUS_APPROVED) {
                $this->fulfill($request, $requester);
            }

            return $request->refresh();
        });
    }

    /**
     * Genehmigungsentscheidung mit Selbstfreigabe-Sperre; question hält den
     * Schritt offen (Rückfrage), rejected beendet den Request, delegated
     * erzeugt einen neuen offenen Schritt gleicher Nummer (MVP-154).
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason = null, ?int $delegateUserId = null): ServiceRequest {
        /** @var ServiceRequest $request */
        $request = $approval->approvable()->firstOrFail();
        $requesterId = (int) $request->ticket()->firstOrFail()->reported_by_user_id;

        return DB::transaction(function () use ($approval, $actor, $decision, $reason, $delegateUserId, $request, $requesterId): ServiceRequest {
            $outcome = app(ApprovalService::class)->decide($approval, $actor, $decision, $reason, $requesterId, $delegateUserId);

            if ($outcome === 'rejected') {
                $request->update(['status' => ServiceRequest::STATUS_REJECTED]);
            } elseif ($outcome === 'approved_all') {
                $request->update(['status' => ServiceRequest::STATUS_APPROVED]);
                $this->fulfill($request, $actor);
            }

            $request->audit('service_request.decided', ['step' => $approval->step, 'decision' => $decision]);

            return $request->refresh();
        });
    }

    /** Fulfillment — idempotent: ein zweiter Aufruf erzeugt nichts Neues. */
    public function fulfill(ServiceRequest $request, User $actor): ServiceRequest {
        if ($request->fulfilled_id !== null) {
            return $request; // idempotent
        }
        if (! in_array($request->status, [ServiceRequest::STATUS_APPROVED, ServiceRequest::STATUS_FULFILLING], true)) {
            throw new \RuntimeException((string) __('Nur genehmigte Requests können erfüllt werden.'));
        }

        $snapshot = $request->catalog_snapshot;
        $ticket = $request->ticket()->firstOrFail();
        $config = (array) ($snapshot['fulfillment_config'] ?? []);

        $subject = match ((string) ($snapshot['fulfillment'] ?? 'task')) {
            'project' => Project::query()->create([
                'organization_id' => $request->organization_id,
                'name' => (string) $snapshot['name'] . ' — ' . $ticket->ticket_no,
                'customer_id' => $ticket->customer_id,
                'status' => 'active',
                'created_by' => $actor->id,
            ]),
            'diary' => DiaryEntry::query()->create([
                'organization_id' => $request->organization_id,
                'user_id' => $actor->id,
                'customer_id' => $ticket->customer_id,
                'content' => (string) $snapshot['name'] . ' (' . $ticket->ticket_no . ')',
                'status' => 2,
                'start_at' => now(),
                'end_at' => now()->addHour(),
            ]),
            'procedure' => $this->startProcedure($request, $ticket, $config, $actor),
            default => Task::query()->create([
                'organization_id' => $request->organization_id,
                'title' => (string) $snapshot['name'] . ' (' . $ticket->ticket_no . ')',
                'status' => 'open',
                'created_by' => $actor->id,
            ]),
        };

        $request->update([
            'status' => ServiceRequest::STATUS_DONE,
            'fulfilled_type' => $subject->getMorphClass(),
            'fulfilled_id' => $subject->getKey(),
        ]);

        $request->audit('service_request.fulfilled', ['type' => $subject->getMorphClass(), 'id' => $subject->getKey()]);

        return $request->refresh();
    }

    /** @param array<string, mixed> $config */
    private function startProcedure(ServiceRequest $request, ServiceTicket $ticket, array $config, User $actor): \App\Models\ProcedureRun {
        $templateId = (int) ($config['procedure_template_id'] ?? 0);
        $template = \App\Models\ProcedureTemplate::query()
            ->where('organization_id', $request->organization_id)
            ->findOrFail($templateId);

        return app(\App\Services\Procedure\ProcedureExecutionService::class)
            ->start($template, $ticket, $actor);
    }
}
