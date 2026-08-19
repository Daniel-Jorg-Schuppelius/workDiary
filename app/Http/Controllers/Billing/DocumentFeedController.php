<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Customer, Document, Expense, Invoice, Quote, User};
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Route};
use Illuminate\View\View;

/**
 * Belegfluss — eine Liste über Angebote, Rechnungen, Buchhaltungsbelege,
 * Eingangsrechnungen und Auslagen (Feature 105, MVP-543/545/546/547).
 *
 * Die Tabs sind benannte Filterzustände derselben Liste, keine eigenen Seiten:
 * die frühere Trennung „Angebote | Rechnungen | Belege" folgte der technischen
 * Herkunft, nicht der fachlichen Frage. Herkunft ist jetzt ein Filter.
 */
class DocumentFeedController extends Controller {
    use ResolvesGlobalDateRange;

    /**
     * Tabs als Filter-Voreinstellungen. `kinds`/`directions` sind die
     * Achsenwerte, `sources` dient nur der Sichtbarkeit des Tabs selbst.
     *
     * @var array<string, array{kinds: list<string>, directions: list<string>, icon: string, source: ?string}>
     */
    public const TABS = [
        'all' => ['kinds' => [], 'directions' => [], 'icon' => 'receipt_long', 'source' => null],
        'quotes' => ['kinds' => ['quote'], 'directions' => [], 'icon' => 'request_quote', 'source' => 'quote'],
        'outgoing' => ['kinds' => ['invoice', 'down_payment'], 'directions' => ['outgoing'], 'icon' => 'north_east', 'source' => null],
        'incoming' => ['kinds' => ['invoice'], 'directions' => ['incoming'], 'icon' => 'south_west', 'source' => null],
        'credits' => ['kinds' => ['credit_note', 'cancellation'], 'directions' => [], 'icon' => 'undo', 'source' => null],
        'expenses' => ['kinds' => ['expense'], 'directions' => [], 'icon' => 'account_balance_wallet', 'source' => 'expense'],
        'other' => ['kinds' => ['order_confirmation', 'delivery_note', 'other'], 'directions' => [], 'icon' => 'more_horiz', 'source' => null],
    ];

    public function index(Request $request): View {
        $user = $this->currentUser();
        $sources = $this->allowedSources($user);
        abort_unless(in_array(true, $sources, true), 403);

        $tab = $request->string('tab')->toString();
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'all';
        $preset = self::TABS[$tab];

        $range = $this->globalDateRange();
        $scopeAll = $tab === 'expenses' && $request->string('scope')->toString() === 'all' && $this->maySeeAllExpenses($user);

        $filters = new DocumentFeedFilters(
            organizationId: (int) $user->organization_id,
            userId: (int) $user->id,
            from: $range['from']->startOfDay()->toImmutable(),
            to: $range['to']->endOfDay()->toImmutable(),
            kinds: $this->kinds($preset['kinds']),
            directions: $this->directions($preset['directions'], $request),
            origin: DocumentOrigin::tryFrom($request->string('origin')->toString()),
            contactType: in_array($request->string('contact')->toString(), ['customer', 'supplier'], true)
                ? $request->string('contact')->toString()
                : null,
            // Sqid bevorzugt, numerische Alt-Links bleiben gültig.
            customerId: Sqid::decodeOrNumeric(Customer::class, (string) $request->query('customer', '')),
            state: in_array($request->string('state')->toString(), ['draft', 'open', 'paid', 'cancelled'], true)
                ? $request->string('state')->toString()
                : null,
            search: trim((string) $request->input('q', '')),
            onlyOverdue: $request->boolean('overdue'),
            includeArchived: $request->boolean('archived'),
            allExpenses: $scopeAll,
            onlyUnlinkedExpenses: $tab === 'expenses' && $request->boolean('unlinked'),
            sources: $sources,
        );

        $feed = new DocumentFeedQuery($filters);

        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, DocumentFeedQuery::SORTS) ? $sort : 'date';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        return view('billing.feed', [
            'rows' => $feed->paginate(30, $sort, $dir),
            'totals' => $feed->totals(),
            'counts' => $feed->tabCounts(),
            'tab' => $tab,
            'sort' => $sort,
            'dir' => $dir,
            'rangeLabel' => $range['label'],
            'sources' => $sources,
            'scopeAll' => $scopeAll,
            'mayScopeAll' => $this->maySeeAllExpenses($user),
            // Mahnung aus der Zeile heraus (MVP-547) — beim Buchhaltungsbeleg
            // legt sie das externe System an, deshalb dessen Sync-Recht.
            'canDun' => $user->can(Permission::VoucherLexofficeSync->value),
            'canDunLocal' => $user->canManageBilling(),
            // orgaMAX-Belege haben keine eigene Detailseite; das PDF liegt
            // hinter der Admin-Route des Plugins (MVP-670). Ohne aktives
            // Plugin existiert die Route nicht.
            'canOpenOrgaMax' => $user->isAdmin() && Route::has('admin.orgamax.invoices.pdf'),
            'filters' => [
                'q' => $filters->search,
                'origin' => $filters->origin->value ?? '',
                'contact' => $filters->contactType ?? '',
                'state' => $filters->state ?? '',
                'overdue' => $filters->onlyOverdue,
                'archived' => $filters->includeArchived,
                'unlinked' => $filters->onlyUnlinkedExpenses,
            ],
        ]);
    }

    /**
     * Bestandsrouten (MVP-549). Angebote, Rechnungen und Belege waren eigene
     * Seiten; sie sind jetzt Filterzustände desselben Feeds. Die alten Namen
     * bleiben gültig, damit Lesezeichen, Deep-Links und die vielen
     * `redirect()->route('invoices.index')` nach Aktionen weiter funktionieren.
     */
    public function fromInvoices(): RedirectResponse {
        return redirect()->route('billing.feed', ['tab' => 'outgoing']);
    }

    public function fromQuotes(): RedirectResponse {
        return redirect()->route('billing.feed', ['tab' => 'quotes']);
    }

    public function fromVouchers(Request $request): RedirectResponse {
        return redirect()->route('billing.feed', array_filter([
            'origin' => DocumentOrigin::Lexoffice->value,
            'q' => trim((string) $request->query('q', '')) ?: null,
        ]));
    }

    /**
     * Sichtbarkeit je Quelle. Der Feed zeigt ausschließlich, was die jeweilige
     * Policy ohnehin erlaubt — er ist eine Projektion, kein Rechte-Umgehungsweg.
     *
     * @return array<string, bool>
     */
    private function allowedSources(User $user): array {
        return [
            'invoice' => Gate::allows('viewAny', Invoice::class),
            'quote' => Gate::allows('viewAny', Quote::class),
            'voucher' => $user->can(Permission::VoucherViewAny->value),
            'incoming_einvoice' => Gate::allows('viewAny', Document::class),
            'expense' => Gate::allows('viewAny', Expense::class),
        ];
    }

    /**
     * „Alle Auslagen" ist Adminrecht: die ExpensePolicy kennt nur Eigentümer
     * (view) und Admin (decide/reimburse), kein Zwischenrecht.
     */
    private function maySeeAllExpenses(User $user): bool {
        return $user->isAdmin();
    }

    /**
     * @param  list<string>  $values
     * @return list<DocumentKind>
     */
    private function kinds(array $values): array {
        return array_values(array_filter(array_map(
            static fn(string $value): ?DocumentKind => DocumentKind::tryFrom($value),
            $values,
        )));
    }

    /**
     * Richtung kommt aus dem Tab; in „Alle" und „Gutschriften" ist sie
     * mehrdeutig und deshalb zusätzlich als Filter wählbar.
     *
     * @param  list<string>  $preset
     * @return list<DocumentDirection>
     */
    private function directions(array $preset, Request $request): array {
        if ($preset !== []) {
            return $this->directionValues($preset);
        }

        $chosen = $request->string('direction')->toString();

        return $chosen === '' ? [] : $this->directionValues([$chosen]);
    }

    /**
     * @param  list<string>  $values
     * @return list<DocumentDirection>
     */
    private function directionValues(array $values): array {
        return array_values(array_filter(array_map(
            static fn(string $value): ?DocumentDirection => DocumentDirection::tryFrom($value),
            $values,
        )));
    }

    private function currentUser(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
