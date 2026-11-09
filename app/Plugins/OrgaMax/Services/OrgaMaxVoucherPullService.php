<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use APIToolkit\Entities\ID;
use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Supplier\Supplier;
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\Support\PluginSettingsResolver;
use App\Services\Finance\Accounting\Vouchers\{LineAwareVoucherPuller, MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use Orgamax\API\Endpoints\{ExpensesEndpoint, InvoicesEndpoint};
use Orgamax\Entities\Expenses\Expense;
use Orgamax\Entities\Invoices\Invoice;
use Orgamax\Enums\{InvoiceState, InvoiceType};

/**
 * orgaMAX als Belegspiegel (Feature 077, MVP-1501): Rechnungen
 * (`GET /invoice`, `InvoiceListData`) und Ausgaben (`GET /expense`,
 * `ExpenseListData`) in `accounting_vouchers`, die Positionen einer Rechnung
 * im Einzelabruf (`GET /invoice/{id}`, `InvoiceDataObject.positions`). Jede
 * gelesene Rechnung geht in derselben Runde in den Plugin-Spiegel
 * `orgamax_invoices` (Wechsel, Belegverbund, PDF): eine Leseroute, zwei
 * Spiegel. Ohne Änderungscursor in der API liest der Abruf in Etappen
 * ({@see OrgaMaxPagedSweep}), `$pages` ist das Seitenbudget je Liste.
 *
 * - Rechnungsart: `depositInvoice` ist eine Anzahlung,
 *   `recurringInvoiceTemplate` eine Serienvorlage für die Planquelle (Rhythmus
 *   und nächste Ausführung führt die API nicht), alle übrigen sind Rechnungen.
 * - Zustand: `draft` Entwurf, `cancelled` storniert, `paid` bezahlt, `locked`
 *   und `partiallyPaid` offen mit `outstandingAmount` als Rest.
 * - Ausgaben sind eingehende Belege; offen, solange `outstandingAmount` über 0
 *   liegt. Der Lieferant kommt über `supplierId`, sonst über `payee`.
 * - orgaMAX rechnet in Euro; die API führt kein Währungsfeld.
 */
final class OrgaMaxVoucherPullService implements LineAwareVoucherPuller {
    /** Ausgaben-IDs mit Präfix, damit sie nicht mit Rechnungs-IDs kollidieren. */
    public const string EXPENSE_PREFIX = 'expense:';

    public function __construct(
        private readonly OrgaMaxClientFactory $clients,
        private readonly OrgaMaxInvoiceProjector $projector,
        private readonly OrgaMaxPagedSweep $sweep,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    /** Plugin eingeschaltet, Verbindung aktiv und Faktura, Zahlungen oder Ausgaben freigegeben. */
    public function isConfigured(int $organizationId): bool {
        $connection = $this->connection($organizationId);

        return $connection !== null
            && PluginSettingsResolver::for(OrgaMaxPlugin::ID, $organizationId)->enabled()
            && (self::readsInvoices($connection) || $connection->capabilityEnabled('expenses'));
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        $connection = $this->connection($organizationId);
        if ($connection === null || ! $this->isConfigured($organizationId)) {
            return $counters;
        }
        $client = $this->clients->for($connection);
        $pageSize = max(1, (int) config('plugins.orgamax.page_size', 100));

        if (self::readsInvoices($connection)) {
            $invoices = new InvoicesEndpoint($client);
            $this->sweep->run($connection, 'invoices', function (int $offset) use ($invoices, $connection, $organizationId, $pageSize, &$counters): array {
                $rows = $invoices->search(['offset' => $offset, 'limit' => $pageSize])?->getValues() ?? [];
                foreach ($rows as $invoice) {
                    $counters['read']++;
                    $this->projector->store($connection, $invoice);
                    $this->mirror->store($organizationId, $this->pluginId(), self::mapInvoice($invoice), $counters);
                }

                return ['read' => count($rows)];
            }, $pageSize, $pages);
        }

        if ($connection->capabilityEnabled('expenses')) {
            $expenses = new ExpensesEndpoint($client);
            $this->sweep->run($connection, 'expenses', function (int $offset) use ($expenses, $organizationId, $pageSize, &$counters): array {
                $rows = $expenses->search(['offset' => $offset, 'limit' => $pageSize])?->getValues() ?? [];
                foreach ($rows as $expense) {
                    $counters['read']++;
                    $this->mirror->store($organizationId, $this->pluginId(), self::mapExpense($expense), $counters);
                }

                return ['read' => count($rows)];
            }, $pageSize, $pages);
        }

        return $counters;
    }

    public function pullLines(int $organizationId, AccountingVoucher $voucher): array {
        $externalId = (string) $voucher->external_id;
        $connection = $this->connection($organizationId);
        if ($externalId === '' || str_starts_with($externalId, self::EXPENSE_PREFIX) || $connection === null) {
            return [];
        }

        $invoice = (new InvoicesEndpoint($this->clients->for($connection)))->get(new ID($externalId));
        $lines = [];
        $index = 0;
        foreach ($invoice?->getPositions()?->getValues() ?? [] as $position) {
            $index++;
            $article = $position->getMetaData();
            $lines[] = new MirroredVoucherLine(
                position: $index,
                name: mb_substr(trim((string) $position->getTitle()) ?: ('#' . $index), 0, 255),
                // `amount` ist die Menge der Position (wie in der Übergabe, OrgaMaxTarget).
                quantity: $position->getAmount() ?? 1.0,
                unitNet: VoucherMirror::decimal($position->getPriceNet(), 4) ?? '0.0000',
                totalNet: VoucherMirror::decimal($position->getTotalNet()) ?? '0.00',
                currency: 'EUR',
                externalArticleId: $article?->getType() === 'article' && $article->getId() !== null ? (string) $article->getId() : null,
                description: trim((string) $position->getDescription()) ?: null,
                unitName: trim((string) $position->getUnit()) ?: null,
                taxRate: VoucherMirror::decimal($position->getVatPercent()),
            );
        }

        return $lines;
    }

    private static function readsInvoices(OrgaMaxConnection $connection): bool {
        return $connection->capabilityEnabled('billing') || $connection->capabilityEnabled('payments');
    }

    private function connection(int $organizationId): ?OrgaMaxConnection {
        $connection = OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();

        return $connection instanceof OrgaMaxConnection && $connection->isActive() ? $connection : null;
    }

    private static function mapInvoice(Invoice $invoice): MirroredVoucher {
        $type = $invoice->getType();
        $template = $type === InvoiceType::RECURRING_INVOICE_TEMPLATE;
        $state = $invoice->getState();
        $customerId = $invoice->getCustomerId() !== null ? (string) $invoice->getCustomerId() : null;

        return new MirroredVoucher(
            externalId: $invoice->getId() !== null ? (string) $invoice->getId() : '',
            direction: $template ? DocumentDirection::Neutral : DocumentDirection::Outgoing,
            kind: match ($type) {
                InvoiceType::RECURRING_INVOICE_TEMPLATE => DocumentKind::RecurringTemplate,
                InvoiceType::DEPOSIT_INVOICE => DocumentKind::DownPayment,
                default => DocumentKind::Invoice,
            },
            rawType: $type?->value,
            rawStatus: $state?->value,
            state: $template ? AccountingVoucherState::Draft : match ($state) {
                InvoiceState::DRAFT => AccountingVoucherState::Draft,
                InvoiceState::CANCELLED => AccountingVoucherState::Cancelled,
                InvoiceState::PAID => AccountingVoucherState::Paid,
                default => AccountingVoucherState::Open,
            },
            number: trim((string) $invoice->getNumber()) ?: null,
            date: VoucherMirror::date($invoice->getDate()),
            dueDate: VoucherMirror::date($invoice->getDueToDate()),
            totalAmount: $template ? null : VoucherMirror::decimal($invoice->getTotalGross()),
            netAmount: $template ? null : VoucherMirror::decimal($invoice->getTotalNet()),
            openAmount: $template ? null : VoucherMirror::decimal($invoice->getOutstandingAmount()),
            isCancellation: false,
            contactExternalId: $customerId,
            contactParty: Customer::class,
            payload: [
                'source' => OrgaMaxPlugin::ID,
                'type' => $type?->value,
                'state' => $state?->value,
                'customer_id' => $customerId,
                'meta' => $invoice->getMetaData(),
            ] + ($template ? ['plan' => [
                // Eine stornierte Vorlage endet; einen Pausenzustand dokumentiert die API nicht.
                'status' => $state === InvoiceState::CANCELLED ? 'ENDED' : 'ACTIVE',
                'interval' => '',
                'starts_on' => VoucherMirror::date($invoice->getDate()),
                'ends_on' => null,
                'next_execution_on' => null,
                'last_execution_on' => null,
                'finalize' => false,
                'last_failed' => false,
                'last_error' => null,
                'permalink' => null,
            ]] : []),
        );
    }

    private static function mapExpense(Expense $expense): MirroredVoucher {
        $outstanding = $expense->getOutstandingAmount();
        $paid = $outstanding !== null ? $outstanding <= 0.0 : trim((string) $expense->getPayDate()) !== '';
        $supplierId = trim((string) $expense->getSupplierId());

        return new MirroredVoucher(
            externalId: $expense->getId() !== null ? self::EXPENSE_PREFIX . $expense->getId() : '',
            direction: DocumentDirection::Incoming,
            kind: DocumentKind::Expense,
            rawType: 'expense',
            rawStatus: $expense->getPayKind()?->value,
            state: $paid ? AccountingVoucherState::Paid : AccountingVoucherState::Open,
            number: trim((string) $expense->getReceiptNumber()) ?: null,
            date: VoucherMirror::date($expense->getDate()),
            paidDate: VoucherMirror::date($expense->getPayDate()),
            totalAmount: VoucherMirror::decimal($expense->getPriceTotal()),
            netAmount: VoucherMirror::decimal($expense->getPrice()),
            openAmount: VoucherMirror::decimal($outstanding),
            contactExternalId: $supplierId !== '' ? $supplierId : null,
            contactParty: Supplier::class,
            supplierName: trim((string) $expense->getPayee()) ?: null,
            payload: [
                'source' => OrgaMaxPlugin::ID,
                'payee' => $expense->getPayee(),
                'description' => $expense->getDescription(),
                'pay_kind' => $expense->getPayKind()?->value,
                'vat_percent' => $expense->getVatPercent(),
                'receipt_count' => $expense->getReceiptCount(),
            ],
        );
    }
}
