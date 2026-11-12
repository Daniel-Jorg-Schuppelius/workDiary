<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceQueueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Team;
use App\Models\ServiceTicket\{ServiceQueue, SlaContract};
use App\Support\HolidayRegions;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Queue-Verwaltung (Feature 065, MVP-150): CRUD als Modal-Dialoge.
 * Genau EINE Default-Queue je Org (Wechsel atomar); Löschen nur, wenn
 * keine Tickets mehr zugeordnet sind (kein stilles Umhängen).
 */
class ServiceQueueController extends Controller {
    use ResolvesCurrentOrganization;
    public function index(): View {
        Gate::authorize('viewAny', ServiceQueue::class);

        return view('helpdesk.queues.index', [
            'queues' => ServiceQueue::query()
                ->withCount('tickets')
                ->with('team:id,name')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->where('is_ola', false)->orderBy('label')->get(['id', 'label']),
            'canManage' => Gate::allows('create', ServiceQueue::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ServiceQueue::class);

        return view('helpdesk.queues._form_dialog', [
            'queue' => new ServiceQueue(['visibility' => 'internal']),
            'isEdit' => false,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->where('is_ola', false)->orderBy('label')->get(['id', 'label']),
            'mailboxes' => $this->mailboxes(),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, EmailConnection> */
    private function mailboxes(): \Illuminate\Database\Eloquent\Collection {
        return EmailConnection::query()->orderBy('name')->get(['id', 'name', 'mailbox_address']);
    }

    public function edit(ServiceQueue $queue): View {
        Gate::authorize('update', $queue);

        return view('helpdesk.queues._form_dialog', [
            'queue' => $queue,
            'isEdit' => true,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->where('is_ola', false)->orderBy('label')->get(['id', 'label']),
            'mailboxes' => $this->mailboxes(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ServiceQueue::class);

        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $queue = ServiceQueue::query()->create($data);
            if ($data['is_default']) {
                $this->makeDefault($queue);
            }
        });

        return redirect()->toList('helpdesk.queues.index')
            ->with('success', __('Queue angelegt.'));
    }

    public function update(Request $request, ServiceQueue $queue): RedirectResponse {
        Gate::authorize('update', $queue);

        $data = $this->validated($request, $queue);

        DB::transaction(function () use ($queue, $data): void {
            $queue->update($data);
            if ($data['is_default']) {
                $this->makeDefault($queue);
            }
        });

        return redirect()->toList('helpdesk.queues.index')
            ->with('success', __('Queue gespeichert.'));
    }

    public function destroy(ServiceQueue $queue): RedirectResponse {
        Gate::authorize('delete', $queue);

        if ($queue->tickets()->exists()) {
            return back()->with('error', __('Queue enthält Tickets und kann nicht gelöscht werden.'));
        }
        if ($queue->is_default) {
            return back()->with('error', __('Die Standard-Queue kann nicht gelöscht werden.'));
        }

        $queue->delete();

        return redirect()->toList('helpdesk.queues.index')
            ->with('success', __('Queue gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceQueue $queue = null): array {
        foreach (['team_id' => Team::class, 'default_sla_contract_id' => SlaContract::class, 'email_connection_id' => EmailConnection::class] as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => \App\Support\Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'team_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('teams')],
            'default_sla_contract_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('sla_contracts')],
            'visibility' => ['required', 'in:internal,portal'],
            'is_default' => ['nullable', 'boolean'],
            'email_connection_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('email_connections')],
            'supported_kinds' => ['nullable', 'array'],
            'supported_kinds.*' => [Rule::enum(ServiceTicketKind::class)],
            'holiday_region' => ['nullable', 'string', Rule::in(HolidayRegions::providers())],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'sender_email' => ['nullable', 'email', 'max:190'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.from' => ['nullable', 'date_format:H:i'],
            'business_hours.*.to' => ['nullable', 'date_format:H:i'],
        ]);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['team_id'] = $data['team_id'] ?? null;
        $data['default_sla_contract_id'] = $data['default_sla_contract_id'] ?? null;
        // Ein OLA ist ein internes Ziel und kein Kundenversprechen der Queue.
        if ($data['default_sla_contract_id'] !== null && SlaContract::query()->whereKey($data['default_sla_contract_id'])->where('is_ola', true)->exists()) {
            throw ValidationException::withMessages(['default_sla_contract_id' => __('helpdesk.queue.olaNotDefault')]);
        }
        // Ein Postfach speist genau eine Queue: der Mail-Eingang sucht die Queue über die Verbindung.
        $data['email_connection_id'] = $data['email_connection_id'] ?? null;
        if ($data['email_connection_id'] !== null && ServiceQueue::query()
            ->where('email_connection_id', $data['email_connection_id'])
            ->whereKeyNot($queue->id ?? 0)
            ->exists()) {
            throw ValidationException::withMessages(['email_connection_id' => __('helpdesk.queue.mailboxTaken')]);
        }
        $data['supported_kinds'] = array_values(array_unique((array) ($data['supported_kinds'] ?? []))) ?: null;
        $data['holiday_region'] = StringHelper::trimToNull($data['holiday_region'] ?? null);
        $sender = array_filter([
            'name' => StringHelper::trimToNull($data['sender_name'] ?? null),
            'email' => StringHelper::trimToNull($data['sender_email'] ?? null),
        ]);
        $data['sender_identity'] = $sender !== [] ? $sender : null;
        $data['business_hours'] = $this->businessHours((array) ($data['business_hours'] ?? []));
        unset($data['sender_name'], $data['sender_email']);
        if ($queue === null) {
            $data['organization_id'] = (int) $this->currentOrganization()->id;
        }

        return $data;
    }

    /**
     * Ein Fenster je Wochentag in der Form, die der SlaTimer liest (`weekday`, `from`, `to`).
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{weekday: int, from: string, to: string}>|null
     */
    private function businessHours(array $rows): ?array {
        $windows = [];
        foreach ($rows as $weekday => $row) {
            $from = StringHelper::trimToNull(is_array($row) ? ($row['from'] ?? null) : null);
            $to = StringHelper::trimToNull(is_array($row) ? ($row['to'] ?? null) : null);
            if ($from === null && $to === null) {
                continue;
            }
            if ((int) $weekday < 1 || (int) $weekday > 7 || $from === null || $to === null || $to <= $from) {
                throw ValidationException::withMessages(['business_hours' => __('helpdesk.queue.hoursInvalid')]);
            }
            $windows[] = ['weekday' => (int) $weekday, 'from' => $from, 'to' => $to];
        }

        return $windows !== [] ? $windows : null;
    }

    /** Genau eine Default-Queue je Org. */
    private function makeDefault(ServiceQueue $queue): void {
        ServiceQueue::query()
            ->whereKeyNot($queue->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
        if (! $queue->is_default) {
            $queue->update(['is_default' => true]);
        }
    }
}
