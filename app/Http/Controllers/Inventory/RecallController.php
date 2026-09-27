<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\Inventory\{RecallItemStatus, RecallKind, RecallMeasure, RecallRiskLevel, RecallStatus};
use App\Http\Controllers\Controller;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{Recall, RecallItem, StockDelivery};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Inventory\{RecallAuthorityReportPdfRenderer, RecallService};
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Rückrufaktionen (MVP-921): Liste, Entwurf, Aktivierung, Stand je Auslieferung. */
class RecallController extends Controller {
    public function __construct(private readonly RecallService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Recall::class);
        $status = RecallStatus::tryFrom($request->string('status')->toString());

        return view('inventory.recalls.index', [
            'recalls' => Recall::query()
                ->with('variant.article')
                ->withCount(['items', 'items as open_items_count' => fn ($q) => $q->whereIn('status', [RecallItemStatus::Open->value, RecallItemStatus::Notified->value])])
                ->when($status, fn ($q, RecallStatus $s) => $q->where('status', $s->value))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'activeCount' => Recall::query()->where('status', RecallStatus::Active->value)->count(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Recall::class);

        return view('inventory.recalls._form_dialog', ['recall' => null, 'variants' => $this->variants()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Recall::class);
        $request->merge(['article_variant_id' => Sqid::decodeOrNumeric(ArticleVariant::class, $request->string('article_variant_id')->toString())]);
        $request->validate(['article_variant_id' => ['required', 'integer', new ExistsInCurrentOrganization('article_variants')]]);
        $variant = ArticleVariant::query()->findOrFail((int) $request->input('article_variant_id'));

        $recall = $this->service->create($variant, $this->validated($request), $request->user() ?? abort(401));

        return redirect()->route('recalls.show', $recall)->with('success', __('recall.flash.created', ['number' => (string) $recall->number]));
    }

    public function show(Recall $recall): View {
        Gate::authorize('view', $recall);
        $recall->load(['variant.article', 'attachments']);
        $isDraft = $recall->status === RecallStatus::Draft;

        return view('inventory.recalls.show', [
            'recall' => $recall,
            'items' => $items = $recall->items()->with(['customer', 'delivery', 'serial', 'claimCase'])->orderBy('customer_id')->orderBy('id')->get(),
            // Auswertung (MVP-922): Stand je Status und Rücklaufquote.
            'stats' => $items->countBy(fn (RecallItem $i): string => $i->status->value)->all(),
            'dispatches' => $recall->dispatches()->limit(50)->get(),
            // Entwurf: Vorschau der Eingrenzung, bevor etwas festgeschrieben wird.
            'previewDeliveries' => $isDraft ? $this->service->affectedDeliveries($recall)->with('customer')->limit(200)->get() : collect(),
            'previewStock' => $isDraft ? $this->service->stockSerials($recall)->count() : null,
        ]);
    }

    public function edit(Recall $recall): View {
        Gate::authorize('update', $recall);
        abort_unless($recall->status === RecallStatus::Draft, 404);

        return view('inventory.recalls._form_dialog', ['recall' => $recall, 'variants' => $this->variants()]);
    }

    public function update(Request $request, Recall $recall): RedirectResponse {
        Gate::authorize('update', $recall);

        try {
            $this->service->update($recall, $this->validated($request), $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('recalls.show', $recall)->with('success', __('recall.flash.saved'));
    }

    /** Behördenmeldung (MVP-945): Angaben pflegen. */
    public function authorityForm(Recall $recall): View {
        Gate::authorize('update', $recall);

        return view('inventory.recalls._authority_dialog', ['recall' => $recall]);
    }

    public function updateAuthority(Request $request, Recall $recall): RedirectResponse {
        Gate::authorize('update', $recall);
        $data = $request->validate([
            'hazard_kind' => ['nullable', 'string', 'max:40'],
            'hazard_description' => ['nullable', 'string', 'max:4000'],
            'risk_level' => ['nullable', Rule::enum(RecallRiskLevel::class)],
            'measure' => ['nullable', Rule::enum(RecallMeasure::class)],
            'countries' => ['nullable', 'string', 'max:500'],
            'authority_name' => ['nullable', 'string', 'max:200'],
            'authority_reference' => ['nullable', 'string', 'max:100'],
            'authority_reported_on' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);
        $data['countries'] = array_values(array_filter(array_map(static fn (string $c): string => mb_strtoupper(trim($c)), explode(',', (string) ($data['countries'] ?? '')))));
        $recall->update($data + ['updated_by' => $request->user()?->id]);

        return back()->with('success', __('recall.authority.flash.saved'));
    }

    /** Meldebogen als PDF (MVP-945). */
    public function authorityPdf(Recall $recall, RecallAuthorityReportPdfRenderer $renderer): Response {
        Gate::authorize('view', $recall);

        return response($renderer->render($recall), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($recall) . '"',
        ]);
    }

    public function transition(Request $request, Recall $recall): RedirectResponse {
        Gate::authorize('update', $recall);
        $data = $request->validate(['status' => ['required', Rule::in([RecallStatus::Active->value, RecallStatus::Completed->value, RecallStatus::Cancelled->value])]]);
        $actor = $request->user() ?? abort(401);

        try {
            match (RecallStatus::from((string) $data['status'])) {
                RecallStatus::Active => $this->service->activate($recall, $actor),
                RecallStatus::Completed => $this->service->complete($recall, $actor),
                default => $this->service->cancel($recall, $actor),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('recall.flash.status', ['status' => $recall->status->label()]));
    }

    public function itemStatus(Request $request, Recall $recall, RecallItem $item): RedirectResponse {
        Gate::authorize('update', $recall);
        abort_unless($item->recall_id === $recall->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::enum(RecallItemStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->setItemStatus($item, RecallItemStatus::from((string) $data['status']), $request->user() ?? abort(401), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('recall.flash.item'));
    }

    /** Kunden mit offenen Positionen anschreiben (MVP-922). */
    public function notify(Request $request, Recall $recall): RedirectResponse {
        Gate::authorize('update', $recall);

        try {
            $result = $this->service->sendNotices($recall, $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }
        $redirect = back()->with('success', __('recall.flash.notified', ['count' => $result['sent']]));

        return $result['without_email'] === [] ? $redirect : $redirect->with('warning', __('recall.flash.without_email', ['customers' => implode(', ', $result['without_email'])]));
    }

    /** Rücklauf über eine Reklamation mit RMA (MVP-922). */
    public function claim(Request $request, Recall $recall, RecallItem $item): RedirectResponse {
        Gate::authorize('update', $recall);
        abort_unless($item->recall_id === $recall->id, 404);

        try {
            $claim = $this->service->openClaim($item, $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('claims.show', $claim)->with('success', __('recall.flash.claim', ['number' => (string) $claim->number]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(RecallKind::class)],
            'title' => ['required', 'string', 'max:200'],
            'reason' => ['required', 'string', 'max:10000'],
            'customer_message' => ['nullable', 'string', 'max:10000'],
            'manufacturing_orders' => ['nullable', 'string', 'max:2000'],
            'delivered_from' => ['nullable', 'date'],
            'delivered_until' => ['nullable', 'date', 'after_or_equal:delivered_from'],
            'serial_numbers' => ['nullable', 'string', 'max:20000'],
            'is_blocking_stock' => ['nullable', 'boolean'],
        ]);
        // Fertigungsaufträge als Nummern, Seriennummern je Zeile oder durch Komma getrennt.
        $numbers = $this->tokens((string) ($data['manufacturing_orders'] ?? ''));
        $data['manufacturing_order_ids'] = $numbers === [] ? null : ManufacturingOrder::query()->whereIn('number', $numbers)->pluck('id')->all();
        $serials = $this->tokens((string) ($data['serial_numbers'] ?? ''));
        $data['serial_numbers'] = $serials === [] ? null : $serials;
        $data['is_blocking_stock'] = $request->boolean('is_blocking_stock');
        unset($data['manufacturing_orders']);

        return $data;
    }

    /** @return list<string> */
    private function tokens(string $raw): array {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/u', $raw) ?: []), static fn (string $t): bool => $t !== '')));
    }

    /** @return \Illuminate\Support\Collection<int, ArticleVariant> */
    private function variants(): \Illuminate\Support\Collection {
        return ArticleVariant::query()
            ->with('article')
            ->whereIn('id', StockDelivery::query()->select('article_variant_id'))
            ->get()
            ->sortBy(fn (ArticleVariant $v): string => ($v->article->name ?? '') . ' ' . ($v->sku ?? ''))
            ->values();
    }
}
