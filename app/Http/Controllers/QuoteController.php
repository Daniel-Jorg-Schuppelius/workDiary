<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Customer, Quote, QuoteItem, User};
use App\Services\Invoicing\QuoteService;
use App\Support\SortableQuery;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Angebots-UI (Feature 066, MVP-170, Restpaket): Lifecycle-Oberfläche über
 * dem bestehenden {@see QuoteService} — Entwurf → Freigabe → Versand
 * (Portal-Token) → Annahme/Teilannahme/Ablehnung → Überführung in eine
 * Entwurfsrechnung. Nach Versand wird versioniert, nie geändert.
 */
class QuoteController extends Controller {
    public function __construct(private readonly QuoteService $quotes) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Quote::class);

        $rawCustomer = (string) $request->query('customer', '');
        $customerId = \App\Support\Sqid::decodeOrNumeric(Customer::class, $rawCustomer);
        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, Quote::STATUSES, true) ? $status : '';

        $query = Quote::query()->with('customer')
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter));

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'number' => 'number',
            'status' => 'status',
            'total' => 'total',
            'valid_until' => 'valid_until',
        ], 'number', 'desc');

        return view('quotes.index', [
            'quotes' => $query->paginate(25)->withQueryString(),
            'statuses' => Quote::STATUSES,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['customer' => $customerId, 'status' => $statusFilter],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Quote::class);

        return view('quotes._form_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => \App\Models\Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Quote::class);

        $request->merge([
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'project_id' => \App\Support\Sqid::decodeOrNumeric(\App\Models\Project::class, $request->input('project_id')),
        ]);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'valid_until' => ['nullable', 'date', 'after:today'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $quote = $this->quotes->create([
            'customer_id' => (int) $data['customer_id'],
            'project_id' => $data['project_id'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'terms' => $data['terms'] ?? null,
        ], [], $actor);

        return redirect()->route('quotes.show', $quote)->with('status', __('Angebot :nr angelegt — Positionen hinzufügen und freigeben.', ['nr' => $quote->number]));
    }

    public function show(Quote $quote): View {
        Gate::authorize('view', $quote);
        $quote->load(['items', 'customer']);

        return view('quotes.show', [
            'quote' => $quote,
            'previousVersion' => $quote->previous_version_id !== null ? Quote::query()->find($quote->previous_version_id) : null,
            'newerVersions' => Quote::query()->where('previous_version_id', $quote->id)->orderBy('version')->get(),
            'invoices' => \App\Models\Invoice::query()->where('quote_id', $quote->id)->orderBy('id')->get(['id', 'number', 'status']),
        ]);
    }

    public function destroy(Quote $quote): RedirectResponse {
        Gate::authorize('delete', $quote);
        $quote->items()->delete();
        $quote->delete();

        return redirect()->route('quotes.index')->with('status', __('Angebots-Entwurf gelöscht.'));
    }

    // ── Positionen (nur Entwurf) ─────────────────────────────────────────

    public function itemForm(Quote $quote, ?QuoteItem $item = null): View {
        Gate::authorize('update', $quote);
        $item ??= new QuoteItem();

        return view('quotes._item_form_dialog', ['quote' => $quote, 'item' => $item]);
    }

    public function addItem(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('update', $quote);
        $data = $this->validateItem($request);

        $quote->items()->create([
            'organization_id' => $quote->organization_id,
            'position' => (int) $quote->items()->max('position') + 1,
            ...$data,
        ]);
        $this->refreshTotals($quote);

        return redirect()->route('quotes.show', $quote)->with('status', __('Position hinzugefügt.'));
    }

    public function updateItem(Request $request, Quote $quote, QuoteItem $item): RedirectResponse {
        Gate::authorize('update', $quote);
        abort_unless($item->quote_id === $quote->id, 404);

        $item->update($this->validateItem($request));
        $this->refreshTotals($quote);

        return redirect()->route('quotes.show', $quote)->with('status', __('Position aktualisiert.'));
    }

    public function removeItem(Quote $quote, QuoteItem $item): RedirectResponse {
        Gate::authorize('update', $quote);
        abort_unless($item->quote_id === $quote->id, 404);

        $item->delete();
        $this->refreshTotals($quote);

        return redirect()->route('quotes.show', $quote)->with('status', __('Position entfernt.'));
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function approve(Quote $quote): RedirectResponse {
        Gate::authorize('approve', $quote);
        try {
            $this->quotes->approve($quote, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.show', $quote)->with('status', __('Angebot freigegeben.'));
    }

    /**
     * Versand: erzeugt das Portal-Annahme-Token. Der Klartext-Link wird
     * GENAU EINMAL angezeigt (nur der Hash ist gespeichert) — der Versand
     * der Nachricht selbst bleibt beim Bearbeiter (Mail/Brief).
     */
    public function send(Quote $quote): RedirectResponse {
        Gate::authorize('send', $quote);
        try {
            ['acceptance_token' => $token] = $this->quotes->send($quote, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.show', $quote)
            ->with('status', __('Angebot als versendet markiert.'))
            ->with('acceptance_url', route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token]));
    }

    /** Interne Entscheidung dokumentieren (Annahme/Teilannahme/Ablehnung). */
    public function decide(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('decide', $quote);
        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if ($data['decision'] === 'accept') {
                $itemIds = null;
                if (! empty($data['item_ids'])) {
                    // Nur eigene Positionen dieses Angebots zulassen.
                    $itemIds = $quote->items()->whereIn('id', array_map('intval', $data['item_ids']))->pluck('id')->map(fn($id): int => (int) $id)->all();
                }
                $this->quotes->accept($quote, $itemIds);
            } else {
                $this->quotes->reject($quote, $data['reason'] ?? null);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.show', $quote)->with('status', __('Entscheidung dokumentiert.'));
    }

    public function newVersion(Quote $quote): RedirectResponse {
        Gate::authorize('decide', $quote);
        try {
            $next = $this->quotes->newVersion($quote, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.show', $next)->with('status', __('Version :v angelegt.', ['v' => $next->version]));
    }

    public function convert(Quote $quote): RedirectResponse {
        Gate::authorize('convert', $quote);
        try {
            $invoice = $this->quotes->convertToInvoice($quote, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('Entwurfsrechnung :nr aus Angebot :quote erstellt.', ['nr' => $invoice->number, 'quote' => $quote->number]));
    }

    // ── Kundenportal-Annahme (Token, ohne Login) ─────────────────────────

    /** Öffentliche Angebotsansicht über das Annahme-Token (MVP-170). */
    public function portalShow(Request $request, Quote $quote): View {
        $this->assertPortalToken($quote, (string) $request->query('token', ''));
        $quote->load(['items', 'customer']);

        return view('quotes.portal', [
            'quote' => $quote,
            'token' => (string) $request->query('token'),
            'decided' => in_array($quote->status, ['accepted', 'partially_accepted', 'rejected'], true),
        ]);
    }

    /** Annahme/Teilannahme/Ablehnung durch den Kunden (Token-geprüft). */
    public function portalDecide(Request $request, Quote $quote): RedirectResponse {
        $token = (string) $request->input('token', '');
        $this->assertPortalToken($quote, $token);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        try {
            if ($data['decision'] === 'accept') {
                $itemIds = null;
                if (! empty($data['item_ids'])) {
                    $itemIds = $quote->items()->whereIn('id', array_map('intval', $data['item_ids']))->pluck('id')->map(fn($id): int => (int) $id)->all();
                }
                $this->quotes->accept($quote, $itemIds, $token);
            } else {
                $this->quotes->reject($quote);
            }
        } catch (\RuntimeException $e) {
            return redirect()->route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token])
            ->with('status', __('Vielen Dank — Ihre Entscheidung wurde dokumentiert.'));
    }

    /** Token gegen den gespeicherten Hash prüfen (kein Login, kein Sqid-Raten). */
    private function assertPortalToken(Quote $quote, string $token): void {
        abort_if($quote->acceptance_token_hash === null || $token === '', 404);
        abort_unless(hash_equals((string) $quote->acceptance_token_hash, CryptoHelper::hash($token)), 404);
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request): array {
        return $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:-9999999', 'max:9999999'],
            // MVP-416: Positionsrabatt — Prozent XOR fester Betrag.
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'optional' => ['nullable', 'boolean'],
        ]);
    }

    private function refreshTotals(Quote $quote): void {
        $quote->load('items');
        $quote->recalculate();
        $quote->save();
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
