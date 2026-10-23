<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierDetailAssembler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\User\Permission;
use App\Models\Article\ArticleSupply;
use App\Models\Invoicing\SelfBillingCredit;
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\{Supplier, SupplierQuestionnaireRequest};
use App\Services\Billing\PartyDocumentSources;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\SupplierAnalysisReportBuilder;
use App\Services\Stammdaten\IdentifierIssueDetector;
use Carbon\CarbonImmutable;

/**
 * Daten der Lieferantenakte (Feature 182, nach dem Muster der Kundenakte):
 * Beschaffungskennzahlen, Ausgabenverlauf, Selbstauskunft, Gutschriften,
 * Belege der Buchhaltungsprogramme, Anhänge und Änderungsverlauf. Panels
 * anderer Module kommen über `CockpitPanel`.
 */
class SupplierDetailAssembler {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly IdentifierIssueDetector $identifierIssues,
        private readonly PartyDocumentSources $partyDocuments,
        private readonly SupplierAnalysisReportBuilder $spend,
    ) {}

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, label: string}  $voucherRange
     * @return array<string, mixed> View-Daten für suppliers.show (ohne die Zeitachsen-Beschriftung des Controllers)
     */
    public function assemble(Supplier $supplier, User $user, array $voucherRange, string $unit): array {
        // Beschaffungszahlen nur mit aktivem Lager-Modul (purchase-orders.* sind darauf gegated).
        $procurementStats = null;
        if ($this->features->isEnabled('module.lager')) {
            $orders = PurchaseOrder::query()->where('supplier_id', $supplier->id);
            $procurementStats = [
                'articles' => ArticleSupply::query()->where('supplier_id', $supplier->id)->count(),
                'orders' => (clone $orders)->count(),
                'open_orders' => (clone $orders)->whereIn('status', [
                    PurchaseOrderStatus::Draft,
                    PurchaseOrderStatus::Ordered,
                    PurchaseOrderStatus::PartiallyReceived,
                ])->count(),
            ];
        }

        // Ausgabenverlauf — Finanzsicht, nur report.view/Admin.
        $seesSpend = $user->isAdmin() || $user->can(Permission::ReportView->value);

        return [
            'identifierIssues' => $this->identifierIssues->forContact($supplier),
            'supplier' => $supplier,
            'procurementStats' => $procurementStats,
            'spendSeries' => $seesSpend ? $this->spend->supplierMonthlySpendSeries((int) $supplier->id, $voucherRange['from'], $voucherRange['to'], $unit) : null,
            'voucherCountSeries' => $seesSpend ? $this->spend->supplierMonthlyVoucherCountSeries((int) $supplier->id, $voucherRange['from'], $voucherRange['to'], $unit) : null,
            // Selbstauskunft (MVP-937).
            'questionnaireRequests' => SupplierQuestionnaireRequest::query()->where('supplier_id', $supplier->id)->with('questionnaire')->orderByDesc('id')->limit(10)->get(),
            'selfBillingCredits' => SelfBillingCredit::query()->where('supplier_id', $supplier->id)->orderByDesc('id')->limit(10)->get(),
            'voucherRange' => $voucherRange,
            'externalDocuments' => $this->partyDocuments->forParty($supplier, $voucherRange['from'], $voucherRange['to']),
            'attachments' => $supplier->attachments()->get(),
            'tags' => $supplier->tags()->get(),
        ];
    }
}
