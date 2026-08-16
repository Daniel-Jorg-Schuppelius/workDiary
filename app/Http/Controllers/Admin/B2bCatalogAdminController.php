<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bCatalogAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Article\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\B2b\{B2bCatalogAccess, B2bCatalogItem, B2bOrder};
use App\Models\{Customer, Organization, User};
use App\Services\B2bCatalog\B2bOrderIntakeService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-Verwaltung des B2B-Katalogzugangs (Feature 099, MVP-457/458):
 * Punchout-Zugänge je Kunde (Secret einmalig sichtbar, Muster SCIM-Token),
 * Artikel-Freigaben mit optionalem Kundenpreis und der Upload-Kanal für
 * openTRANS-Bestellungen. Enterprise-gegatet (`module.b2b_katalog`).
 */
class B2bCatalogAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.b2b-catalog.index', [
            'accesses' => B2bCatalogAccess::query()
                ->where('organization_id', $organization->id)
                ->with('customer')
                ->withCount('items')
                ->orderByDesc('id')
                ->get(),
            'customers' => Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'orders' => B2bOrder::query()
                ->where('organization_id', $organization->id)
                ->with('customer')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'issuedSecret' => session('b2b_issued_secret'),
            'punchoutUrl' => route('b2b-punchout.entry', ['org' => $organization->slug]),
        ]);
    }

    public function show(B2bCatalogAccess $access): View {
        $admin = $this->admin();
        $this->guard($admin, $access);

        return view('admin.b2b-catalog.show', [
            'access' => $access->load('customer'),
            'items' => B2bCatalogItem::query()
                ->where('access_id', $access->id)
                ->with('article')
                ->get()
                ->sortBy(fn(B2bCatalogItem $item): string => (string) $item->article?->number)
                ->values(),
            'articles' => Article::query()
                ->where('organization_id', $admin->organization_id)
                ->where('status', ArticleStatus::Active->value)
                ->where('sellable', true)
                ->orderBy('number')
                ->get(['id', 'number', 'name']),
            'issuedSecret' => session('b2b_issued_secret'),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'customer' => ['required', 'string'],
            'label' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._@-]+$/',
                Rule::unique('b2b_catalog_accesses', 'username')->where('organization_id', $organization->id),
            ],
        ]);

        $customer = $this->resolveCustomer($organization, (string) $data['customer']);
        abort_unless($customer instanceof Customer, 404);

        [$access, $plain] = B2bCatalogAccess::issue(
            (int) $organization->id,
            (int) $customer->id,
            (string) $data['label'],
            (string) $data['username'],
            (int) $admin->id,
        );
        $access->audit('b2b_catalog.access_issued', ['username' => $access->username, 'customer_id' => $customer->id]);

        return redirect()->route('b2b-catalog.show', $access)
            ->with('b2b_issued_secret', $plain)
            ->with('success', __('b2b_catalog.flash.access_issued'));
    }

    public function rotate(B2bCatalogAccess $access): RedirectResponse {
        $admin = $this->admin();
        $this->guard($admin, $access);

        $plain = $access->rotateSecret();
        $access->audit('b2b_catalog.access_rotated', ['username' => $access->username]);

        return back()->with('b2b_issued_secret', $plain)->with('success', __('b2b_catalog.flash.access_rotated'));
    }

    public function revoke(B2bCatalogAccess $access): RedirectResponse {
        $admin = $this->admin();
        $this->guard($admin, $access);

        $access->forceFill(['revoked_at' => now()])->save();
        $access->audit('b2b_catalog.access_revoked', ['username' => $access->username]);

        return back()->with('success', __('b2b_catalog.flash.access_revoked'));
    }

    /** Artikel freigeben (mit optionalem Kundenpreis) bzw. Preis aktualisieren. */
    public function storeItem(Request $request, B2bCatalogAccess $access): RedirectResponse {
        $admin = $this->admin();
        $this->guard($admin, $access);

        $data = $request->validate([
            'article' => ['required', 'string'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $article = Article::query()
            ->where('organization_id', $admin->organization_id)
            ->whereKey($this->decodeSqid(Article::class, (string) $data['article']))
            ->first();
        abort_unless($article instanceof Article, 404);

        B2bCatalogItem::query()->updateOrCreate(
            ['access_id' => $access->id, 'article_id' => $article->id],
            ['organization_id' => $access->organization_id, 'custom_price' => $data['custom_price'] ?? null],
        );
        $access->audit('b2b_catalog.item_released', ['article_id' => $article->id]);

        return back()->with('success', __('b2b_catalog.flash.item_released'));
    }

    public function destroyItem(B2bCatalogAccess $access, B2bCatalogItem $item): RedirectResponse {
        $admin = $this->admin();
        $this->guard($admin, $access);
        abort_unless($item->access_id === $access->id, 404);

        $item->delete();
        $access->audit('b2b_catalog.item_removed', ['article_id' => $item->article_id]);

        return back()->with('success', __('b2b_catalog.flash.item_removed'));
    }

    /** Upload-Kanal für openTRANS-2.1-ORDER-Dateien (MVP-458). */
    public function uploadOrder(Request $request, B2bOrderIntakeService $service): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $request->validate([
            'order_file' => ['required', 'file', 'max:10240', 'mimes:xml,txt'],
        ]);

        $xml = (string) file_get_contents((string) $request->file('order_file')?->getRealPath());

        try {
            $result = $service->intake($organization, $xml, B2bOrder::SOURCE_UPLOAD);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order_file' => __('b2b_catalog.error.not_opentrans', ['reason' => $e->getMessage()])]);
        }

        return back()->with('success', $result['status'] === 'created'
            ? __('b2b_catalog.flash.order_received', ['id' => $result['order']->external_order_id])
            : __('b2b_catalog.flash.order_duplicate', ['id' => $result['order']->external_order_id]));
    }

    private function resolveCustomer(Organization $organization, string $sqid): ?Customer {
        $id = $this->decodeSqid(Customer::class, $sqid);

        return $id === null ? null : Customer::query()
            ->where('organization_id', $organization->id)
            ->whereKey($id)
            ->first();
    }

    /**
     * Kundenindividuelle DATPREIS-Datei für diesen Zugang (Feature 107, W6):
     * K-Kontrollsatz mit der Kundennummer, effektive Nettopreise der
     * freigegebenen Artikel (Feature 099, `custom_price`).
     */
    public function exportDatanorm(Request $request, B2bCatalogAccess $access, \App\Services\Procurement\DatanormExportService $export): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse {
        $admin = $this->admin();
        $this->guard($admin, $access);
        $request->validate(['version' => ['nullable', 'in:4,5']]);

        // Widerrufene Zugänge liefern keine Kundenpreislisten mehr aus —
        // konsistent zur Zugangs-Semantik (Feature 107).
        if (! $access->isActive()) {
            return back()->with('error', (string) __('b2b_catalog.flash.datanorm_revoked'));
        }

        $version = $request->input('version') === '4'
            ? \ERechnungToolkit\Enums\DatanormVersion::V4
            : \ERechnungToolkit\Enums\DatanormVersion::V5;

        $result = $export->exportPrices(
            Organization::query()->findOrFail($access->organization_id),
            $version,
            \ERechnungToolkit\Enums\DatanormPriceIndicator::NetPrice,
            $access
        );
        if ($result['articles'] === 0) {
            return back()->with('error', (string) __('b2b_catalog.flash.datanorm_empty'));
        }

        // Kundenindividuelle Preislisten sind auditpflichtig.
        \App\Models\AuditLog::create([
            'organization_id' => $access->organization_id,
            'user_id' => $admin->id,
            'event' => 'datanorm.exported',
            'auditable_type' => B2bCatalogAccess::class,
            'auditable_id' => $access->id,
            'changes' => [
                'type' => 'customer_prices',
                'version' => $version->value,
                'customer_id' => $access->customer_id,
                'articles' => $result['articles'],
                'skipped' => count($result['skipped']),
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return \App\Http\Controllers\ArticleExportController::buildZipResponse(
            $result['files'],
            'datpreis-' . \Illuminate\Support\Str::slug($access->label) . '.zip'
        );
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    private function decodeSqid(string $modelClass, string $sqid): ?int {
        return app(\App\Services\SqidEncoder::class)->decode($modelClass, $sqid);
    }

    private function guard(User $admin, B2bCatalogAccess $access): void {
        abort_unless($access->organization_id === $admin->organization_id, 404);
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
