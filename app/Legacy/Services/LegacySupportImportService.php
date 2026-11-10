<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Services;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus, TicketMessageKind};
use App\Legacy\Models\{LegacySupportArchivedOrder, LegacySupportCustomer, LegacySupportOrder};
use App\Legacy\Support\{LegacyClock, LegacyConnectivity};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, ServiceTicketMessage};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt das Support-Portal des Altsystems in eine Organisation: Kunden-
 * zugänge als Kunden, Aufträge und Archiv als Servicetickets.
 *
 * Geschrieben wird direkt statt über den ServiceTicketService: Altaufträge
 * lösen weder SLA-Fristen noch Routing oder Lifecycle-Webhooks aus. Idempotent
 * über `external_references`; ein erneuter Lauf zieht Status, Termin, Antwort
 * und Notiz nach. Passwörter werden nicht übernommen — den Portalzugang im
 * neuen System erteilt die Einladung des Kundenportals.
 */
class LegacySupportImportService {
    public const string ORIGIN = 'legacy-import';

    public const string TYPE_CUSTOMER = 'support_customer';

    public const string TYPE_ORDER = 'support_order';

    /** Nachrichtenkanal der übernommenen Antworten und Notizen; ein Lauf ersetzt nur diese. */
    public const string MESSAGE_CHANNEL = 'legacy';

    /** Die laufende Nummer des Altportals bleibt als Ticketnummer erhalten. */
    public const string TICKET_PREFIX = 'KP-';

    /** @var array<int, ServiceTicketStatus> */
    private const array STATUS_MAP = [
        LegacySupportOrder::STATUS_NEW => ServiceTicketStatus::Reported,
        LegacySupportOrder::STATUS_SEEN => ServiceTicketStatus::Triaged,
        LegacySupportOrder::STATUS_SCHEDULED => ServiceTicketStatus::Scheduled,
        LegacySupportOrder::STATUS_DONE => ServiceTicketStatus::Done,
        LegacySupportOrder::STATUS_ARCHIVED => ServiceTicketStatus::Closed,
    ];

    private string $encoding = 'latin1';

    private string $locale = 'de';

    /** @var array<int, int> kp_user.id → customers.id */
    private array $customerMap = [];

    /** @var array<string, int> Legacy-Anmeldename (klein) → users.id der Organisation */
    private array $mechanicMap = [];

    private ?int $queueId = null;

    /**
     * @return array{customers_created: int, customers_matched: int, tickets_created: int, tickets_updated: int, tickets_skipped: int}
     */
    public function import(Organization $organization): array {
        $this->encoding = (string) config('database.connections.' . LegacyConnectivity::CONNECTION . '.charset', 'latin1');
        $this->locale = (string) config('app.locale');

        $result = ['customers_created' => 0, 'customers_matched' => 0, 'tickets_created' => 0, 'tickets_updated' => 0, 'tickets_skipped' => 0];

        $this->importCustomers($organization, $result);
        $this->loadMechanics($organization);
        $this->queueId = ServiceQueue::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->value('id');

        // Archiv nach den laufenden Aufträgen: es trägt den späteren Stand.
        foreach ([LegacySupportOrder::class, LegacySupportArchivedOrder::class] as $orderClass) {
            $orderClass::query()->orderBy('id')->chunk(200, function ($orders) use ($organization, &$result): void {
                foreach ($orders as $order) {
                    $this->importOrder($organization, $order, $result);
                }
            });
        }

        return [
            'customers_created' => $result['customers_created'],
            'customers_matched' => $result['customers_matched'],
            'tickets_created' => $result['tickets_created'],
            'tickets_updated' => $result['tickets_updated'],
            'tickets_skipped' => $result['tickets_skipped'],
        ];
    }

    /** Übernommene Aufträge über alle Organisationen (Migrations-Dashboard). */
    public static function importedOrderCount(): int {
        return ExternalReference::query()->withoutGlobalScopes()
            ->where('plugin_id', self::ORIGIN)
            ->where('external_type', self::TYPE_ORDER)
            ->count();
    }

    /** @param array<string, int> $result */
    private function importCustomers(Organization $organization, array &$result): void {
        $references = ExternalReference::query()->forPlugin($organization, self::ORIGIN, self::TYPE_CUSTOMER)
            ->pluck('referenceable_id', 'external_id')
            ->all();

        $byName = [];
        foreach (Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get(['id', 'name']) as $customer) {
            $byName[$this->nameKey((string) $customer->name)][] = (int) $customer->id;
        }
        $alreadyLinked = array_flip(array_map('intval', array_values($references)));

        foreach (LegacySupportCustomer::query()->customers()->orderBy('id')->get(['id', 'kunde', 'usern']) as $legacy) {
            $legacyId = (int) $legacy->id;
            if (isset($references[(string) $legacyId])) {
                $this->customerMap[$legacyId] = (int) $references[(string) $legacyId];

                continue;
            }

            $name = $this->text((string) $legacy->kunde);
            $candidates = $byName[$this->nameKey($name)] ?? [];
            // Nur ein eindeutiger, noch nicht verknüpfter Treffer gilt als derselbe Kunde.
            $matchId = count($candidates) === 1 && ! isset($alreadyLinked[$candidates[0]]) ? $candidates[0] : null;

            if ($matchId !== null) {
                $customerId = $matchId;
                $result['customers_matched']++;
            } else {
                $customerId = (int) Customer::query()->create([
                    'organization_id' => $organization->id,
                    'name' => $name !== '' ? $name : __('legacy_support.import.customer_fallback', ['id' => $legacyId], $this->locale),
                ])->id;
                $result['customers_created']++;
            }

            ExternalReference::query()->create([
                'organization_id' => $organization->id,
                'plugin_id' => self::ORIGIN,
                'external_type' => self::TYPE_CUSTOMER,
                'referenceable_type' => (new Customer)->getMorphClass(),
                'referenceable_id' => $customerId,
                'external_id' => (string) $legacyId,
                'payload' => ['username' => $this->text((string) $legacy->usern)],
                'synced_at' => now(),
            ]);
            $alreadyLinked[$customerId] = true;
            $this->customerMap[$legacyId] = $customerId;
        }
    }

    private function loadMechanics(Organization $organization): void {
        $usersByLegacyId = User::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('legacy_user_id')
            ->pluck('id', 'legacy_user_id')
            ->all();

        foreach (DB::connection(LegacyConnectivity::CONNECTION)->table('user')->get(['id', 'uname']) as $legacyUser) {
            $userId = $usersByLegacyId[(int) $legacyUser->id] ?? null;
            if ($userId !== null) {
                $this->mechanicMap[mb_strtolower($this->text((string) $legacyUser->uname))] = (int) $userId;
            }
        }
    }

    /** @param array<string, int> $result */
    private function importOrder(Organization $organization, LegacySupportOrder $order, array &$result): void {
        $reference = ExternalReference::query()->forPlugin($organization, self::ORIGIN, self::TYPE_ORDER)
            ->forExternalId((string) $order->id)
            ->first();

        $ticket = $reference !== null
            ? ServiceTicket::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($reference->referenceable_id)
            : null;

        if ($ticket === null) {
            $ticketNo = self::TICKET_PREFIX . $order->id;
            $taken = ServiceTicket::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('ticket_no', $ticketNo)
                ->exists();
            if ($taken) {
                $result['tickets_skipped']++;

                return;
            }

            $ticket = new ServiceTicket([
                'organization_id' => $organization->id,
                'queue_id' => $this->queueId,
                'kind' => ServiceTicketKind::ServiceRequest->value,
                'ticket_no' => $ticketNo,
                'priority' => ServiceTicketPriority::Normal->value,
            ]);
        }

        $reportedAt = $this->utc($order->datum?->format('Y-m-d H:i:s')) ?? now()->format('Y-m-d H:i:s');
        $appointment = $order->appointmentAt();
        $scheduledFor = $appointment !== null ? LegacyClock::toUtc($appointment->format('Y-m-d H:i:s')) : null;
        $status = self::STATUS_MAP[(int) $order->status] ?? ServiceTicketStatus::Reported;
        $finishedAt = in_array($status, [ServiceTicketStatus::Done, ServiceTicketStatus::Closed], true) ? ($scheduledFor ?? $reportedAt) : null;

        $ticket->fill([
            'customer_id' => $this->customerMap[(int) $order->kunde] ?? null,
            'title' => StringHelper::truncate($this->title($order), 200, '…'),
            'description' => $this->description($order),
            'status' => $status->value,
            'source' => $order->isOfficeOrder() ? ServiceTicketSource::Manual->value : ServiceTicketSource::CustomerPortal->value,
            'source_reference' => self::TICKET_PREFIX . $order->id,
            'assigned_to_user_id' => $this->mechanicMap[mb_strtolower($this->text((string) $order->wer))] ?? null,
            'reported_at' => $reportedAt,
            'scheduled_for' => $scheduledFor,
            'resolved_at' => $finishedAt,
            'closed_at' => $status === ServiceTicketStatus::Closed ? $finishedAt : null,
        ]);

        $created = ! $ticket->exists;
        if ($created) {
            $ticket->created_at = Carbon::parse($reportedAt);
        }
        $ticket->save();

        if ($reference === null) {
            ExternalReference::query()->create([
                'organization_id' => $organization->id,
                'plugin_id' => self::ORIGIN,
                'external_type' => self::TYPE_ORDER,
                'referenceable_type' => $ticket->getMorphClass(),
                'referenceable_id' => $ticket->id,
                'external_id' => (string) $order->id,
                'synced_at' => now(),
            ]);
        } else {
            $reference->forceFill(['referenceable_id' => $ticket->id, 'synced_at' => now()])->save();
        }

        $this->syncMessage($ticket, TicketMessageKind::PublicReply, $this->text((string) $order->antwort), $reportedAt);
        $this->syncMessage($ticket, TicketMessageKind::InternalNote, $this->internalNote($order), $reportedAt);

        $result[$created ? 'tickets_created' : 'tickets_updated']++;
    }

    private function syncMessage(ServiceTicket $ticket, TicketMessageKind $kind, string $body, string $at): void {
        $existing = ServiceTicketMessage::query()->withoutGlobalScopes()
            ->where('service_ticket_id', $ticket->id)
            ->where('channel', self::MESSAGE_CHANNEL)
            ->where('kind', $kind->value)
            ->first();

        if ($body === '') {
            $existing?->delete();

            return;
        }

        if ($existing !== null) {
            if ($existing->body !== $body) {
                $existing->update(['body' => $body]);
            }

            return;
        }

        $message = new ServiceTicketMessage([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => $kind->value,
            'body' => $body,
            'channel' => self::MESSAGE_CHANNEL,
        ]);
        $message->created_at = Carbon::parse($at);
        $message->save();
    }

    private function title(LegacySupportOrder $order): string {
        $objekt = trim($this->text((string) $order->objekt));
        if ($objekt !== '') {
            return $objekt;
        }

        $firstLine = trim((string) strtok($this->text((string) $order->auftrag), "\n"));

        return $firstLine !== '' ? $firstLine : __('legacy_support.import.untitled', ['number' => $order->id], $this->locale);
    }

    private function description(LegacySupportOrder $order): string {
        $lines = [trim($this->text((string) $order->auftrag))];
        $contact = array_filter([
            __('legacy_support.field.orderer', [], $this->locale) => $this->text((string) $order->kname),
            __('legacy_support.field.phone', [], $this->locale) => $this->text((string) $order->telefon),
        ], fn(string $value): bool => trim($value) !== '');
        if ($contact !== []) {
            $lines[] = '';
            foreach ($contact as $label => $value) {
                $lines[] = $label . ': ' . trim($value);
            }
        }

        return trim(implode("\n", $lines));
    }

    private function internalNote(LegacySupportOrder $order): string {
        $mechanic = trim($this->text((string) $order->wer));
        $customerNumber = (int) $order->kunde;
        $parts = array_filter([
            trim($this->text((string) $order->intern)),
            trim($this->text((string) $order->dauer)) !== ''
                ? __('legacy_support.field.duration', [], $this->locale) . ': ' . trim($this->text((string) $order->dauer))
                : '',
            // Ohne Konto in der Zielorganisation bliebe sonst nirgends, wer den Auftrag erledigt hat.
            $mechanic !== '' && ! isset($this->mechanicMap[mb_strtolower($mechanic)])
                ? __('legacy_support.field.mechanic', [], $this->locale) . ': ' . $mechanic
                : '',
            $customerNumber > 0 && ! isset($this->customerMap[$customerNumber])
                ? __('legacy_support.field.legacy_customer_number', [], $this->locale) . ': ' . $customerNumber
                : '',
        ], fn(string $part): bool => $part !== '');

        return implode("\n\n", $parts);
    }

    private function utc(?string $wallClock): ?string {
        if ($wallClock === null || $wallClock < LegacySupportOrder::VALID_APPOINTMENT_FROM) {
            return null;
        }

        return LegacyClock::toUtc($wallClock);
    }

    private function text(string $value): string {
        return StringHelper::isNullOrEmpty($value) ? '' : StringHelper::convertToUtf8($value, $this->encoding);
    }

    private function nameKey(string $name): string {
        return mb_strtolower(StringHelper::normalizeWhitespace($name, true));
    }
}
