<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierScorecardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Claims\ClaimCase;
use App\Models\{PurchaseOrder, PurchaseOrderLine, Supplier, User};
use App\Services\Procurement\SupplierScorecardService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Lieferantenperformance-Scorecards (Bauturbo Welle D): Ranking-Übersicht
 * (Gesamt-Score + Ampeln je Kennzahl), Detail-Scorecard je Lieferant (mit
 * x-charts.* Verläufen) und signierte, kurzlebige Beleg-Drilldowns auf die
 * Quellbelege (Wareneingänge/Bestellungen, Reklamationen, Preishistorie) nach
 * dem A12-Muster. Berechnung ausschließlich über {@see SupplierScorecardService}.
 *
 * Recht: Einkaufs-/Bestandsleserecht des Lager-Moduls
 * ({@see Permission::InventoryViewAny}) bzw. Org-Admin. Der Drilldown zeigt nur,
 * was die Scorecard ohnehin aggregiert, unter demselben Recht (Whitebox-
 * Leitplanke Export-Authz).
 */
class SupplierScorecardController extends Controller {
    use ResolvesGlobalDateRange;

    private const PER_PAGE = 25;

    private const DRILL_PER_PAGE = 50;

    public function __construct(private readonly SupplierScorecardService $service) {
    }

    public function index(Request $request): View {
        $this->authorizeReport($request);

        [$from, $to] = $this->resolveRange($request);
        $rows = $this->service->ranking($from, $to);

        return view('reports.supplier-scorecards.index', [
            'rows' => $this->paginate($rows, self::PER_PAGE, $request),
            'scoreSeries' => $this->scoreSeries($rows),
            'from' => $from,
            'to' => $to,
            'label' => $this->globalDateRange()['label'],
            'weights' => $this->service->weights(),
            'metricVersion' => SupplierScorecardService::METRIC_VERSION,
        ]);
    }

    /**
     * Gesamt-Score je Lieferant (Top 15) für das Ranking-Übersichtsdiagramm —
     * „keine Daten"-Lieferanten (overall null) bleiben außen vor. Drilldown auf
     * die Detail-Scorecard.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{x: string, y: int, url: string}>
     */
    private function scoreSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['overall'] !== null)
            ->take(15)
            ->map(static fn(array $row): array => [
                'x' => (string) $row['supplier_name'],
                'y' => (int) $row['overall'],
                'url' => route('supplier-scorecards.show', $row['supplier']),
            ])
            ->all());
    }

    public function show(Request $request, Supplier $supplier): View {
        $this->authorizeReport($request);

        [$from, $to] = $this->resolveRange($request);
        $card = $this->service->scorecard($supplier, $from, $to);

        return view('reports.supplier-scorecards.show', [
            'supplier' => $supplier,
            'card' => $card,
            'from' => $from,
            'to' => $to,
            'label' => $this->globalDateRange()['label'],
        ]);
    }

    /**
     * Beleg-Drilldown je Kennzahl — Zugriff nur über signierten, kurzlebigen
     * Link (temporarySignedRoute) PLUS Report-/Einkaufsrecht. Seitenwechsel
     * bleibt signiert, weil `page` von der Signaturprüfung ausgenommen ist.
     */
    public function drilldown(Request $request, Supplier $supplier): View {
        abort_unless($request->hasValidSignatureWhileIgnoring(['page']), 403);
        $this->authorizeReport($request);

        $kind = (string) $request->query('kind');
        abort_unless(in_array($kind, ['deliveries', 'claims', 'prices'], true), 404);

        $from = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();
        $to = CarbonImmutable::parse((string) $request->query('to'))->endOfDay();

        [$title, $rows] = match ($kind) {
            'deliveries' => [__('scorecard.drill_deliveries'), $this->deliveriesRows($supplier, $from, $to)],
            'claims' => [__('scorecard.drill_claims'), $this->claimsRows($supplier, $from, $to)],
            default => [__('scorecard.drill_prices'), $this->priceRows($supplier, $from, $to)],
        };

        return view('reports.supplier-scorecards.drilldown', [
            'supplier' => $supplier,
            'kind' => $kind,
            'title' => $title,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Report-Recht: Org-Admin ODER Einkaufs-/Bestandsleserecht des Lager-Moduls. */
    private function authorizeReport(Request $request): void {
        $user = $request->user();
        $allowed = $user instanceof User
            && ($user->isAdmin() || $user->can(Permission::InventoryViewAny->value));
        abort_unless($allowed, 403);
    }

    /**
     * Bestellungen des Lieferanten im Zeitraum mit SOLL-/IST-Lieferdatum und
     * Pünktlichkeitsmarkierung (Termintreue-Quelle).
     *
     * @return LengthAwarePaginator<int, array{number: string, expected_at: string, delivered_at: string|null, status: PurchaseOrderStatus, on_time: bool|null}>
     */
    private function deliveriesRows(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): LengthAwarePaginator {
        $delivered = $this->service->deliveredAtByOrder($supplier);

        $paginator = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', [
                PurchaseOrderStatus::Ordered->value,
                PurchaseOrderStatus::PartiallyReceived->value,
                PurchaseOrderStatus::Received->value,
            ])
            ->whereNotNull('expected_at')
            ->whereBetween('expected_at', [$from, $to])
            ->orderByDesc('expected_at')
            ->paginate(self::DRILL_PER_PAGE);

        $rows = $paginator->getCollection()->map(function (PurchaseOrder $order) use ($delivered): array {
            $deliveredAt = $delivered[(int) $order->id] ?? null;
            $expected = CarbonImmutable::parse((string) $order->expected_at);

            return [
                'number' => $order->number,
                'expected_at' => $expected->toDateString(),
                'delivered_at' => $deliveredAt?->toDateString(),
                'status' => $order->status,
                'on_time' => $deliveredAt?->startOfDay()->lte($expected->endOfDay()),
            ];
        });

        return new Paginator($rows, $paginator->total(), $paginator->perPage(), $paginator->currentPage(), [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Reklamationen mit Lieferantenbezug im Zeitraum (Reklamationsquoten-Quelle).
     *
     * @return LengthAwarePaginator<int, ClaimCase>
     */
    private function claimsRows(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): LengthAwarePaginator {
        return ClaimCase::query()
            ->where('supplier_id', $supplier->id)
            ->whereBetween('reported_at', [$from, $to])
            ->orderByDesc('reported_at')
            ->paginate(self::DRILL_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Bestellpositionen mit Einkaufspreis im Zeitraum (Preisentwicklungs-Quelle).
     *
     * @return LengthAwarePaginator<int, PurchaseOrderLine>
     */
    private function priceRows(Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): LengthAwarePaginator {
        return PurchaseOrderLine::query()
            ->whereNotNull('unit_price')
            ->whereHas('purchaseOrder', function ($q) use ($supplier, $from, $to): void {
                $q->where('supplier_id', $supplier->id)
                    ->whereIn('status', [
                        PurchaseOrderStatus::Ordered->value,
                        PurchaseOrderStatus::PartiallyReceived->value,
                        PurchaseOrderStatus::Received->value,
                    ])
                    ->whereNotNull('ordered_at')
                    ->whereBetween('ordered_at', [$from, $to]);
            })
            ->with(['article:id,name', 'purchaseOrder:id,number,ordered_at'])
            ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->orderBy('purchase_orders.ordered_at')
            ->select('purchase_order_lines.*')
            ->paginate(self::DRILL_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Baut aus dem sortierten Ranking-Array einen Paginator (die Sortierung
     * nach Gesamt-Score erfordert die Berechnung aller Lieferanten vorab).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate(array $rows, int $perPage, Request $request): LengthAwarePaginator {
        $page = max(1, (int) $request->integer('page', 1));
        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($rows);
        $slice = $collection->forPage($page, $perPage)->values();

        return new Paginator(
            $slice,
            $collection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
