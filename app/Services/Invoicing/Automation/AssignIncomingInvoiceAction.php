<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignIncomingInvoiceAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Automation;

use App\Automation\Actions\RuleAction;
use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\IncomingInvoiceMatchKind;
use App\Models\Customer\Customer;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\IncomingInvoiceMatcher;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;

/**
 * Aktion „Zuordnen“ (Phase 150, MVP-1210): Partei `supplier:<sqid>` bzw.
 * `customer:<sqid>` — Eingangsbelege zu Lieferanten, Ausgangsbelege zu Kunden.
 */
final class AssignIncomingInvoiceAction implements RuleAction {
    public const TYPE = 'incoming_invoice.assign';

    public function __construct(private readonly IncomingInvoiceMatcher $matcher) {}

    public function type(): string {
        return self::TYPE;
    }

    public function label(): string {
        return (string) __('automation.action.incoming_invoice_assign');
    }

    /** @return list<string> */
    public function triggers(): array {
        return [IncomingInvoiceReceivedTrigger::KEY];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(Model $subject, array $params): array {
        if (! $subject instanceof IncomingEInvoice) {
            return ['skipped' => 'wrong_subject'];
        }
        if ($subject->transferred_at !== null || $subject->supplier_id !== null || $subject->customer_id !== null) {
            return ['skipped' => 'already_assigned'];
        }
        $party = $this->party((int) $subject->organization_id, (string) ($params['party'] ?? ''));
        if ($party === null) {
            return ['skipped' => 'party_missing'];
        }
        $isSupplier = $party instanceof Supplier;
        if ($isSupplier !== ($subject->direction !== DocumentDirection::Outgoing)) {
            return ['skipped' => 'direction_mismatch'];
        }
        $this->matcher->assign($subject, $party, IncomingInvoiceMatchKind::Rule, null);

        return ['assigned' => $isSupplier ? 'supplier:' . $party->id : 'customer:' . $party->id];
    }

    /** Partei aus `supplier:<sqid>` bzw. `customer:<sqid>`, nur der eigenen Organisation. */
    public function party(int $organizationId, string $value): Supplier|Customer|null {
        [$kind, $sqid] = array_pad(explode(':', $value, 2), 2, '');
        $class = match ($kind) { 'supplier' => Supplier::class, 'customer' => Customer::class, default => null };
        if ($class === null || $sqid === '') {
            return null;
        }
        $id = Sqid::decode($class, $sqid);

        return $id === null ? null : $class::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('archived_at')->find($id);
    }

    public function partyLabel(int $organizationId, string $value): string {
        $party = $this->party($organizationId, $value);

        return $party !== null ? (string) $party->name : $value;
    }
}
