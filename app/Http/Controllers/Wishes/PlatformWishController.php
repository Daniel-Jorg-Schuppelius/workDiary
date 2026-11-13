<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlatformWishController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Wishes;

use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\Wishes\AppWishVote;
use App\Services\Wishes\{AppWishService, VendorChannel};
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plattformsicht der Wünsche (Feature 206, MVP-1659/1677): weitergegebene
 * Wünsche aller Organisationen, nur für Plattform-Betreiber. Sichtbar sind
 * bereinigter Text, Art, Seite, Stimmenzahl und die Organisation (damit der
 * Betrieb antworten kann) — nie Ursprungstext, verfassende oder abstimmende
 * Personen. Der Betrieb bündelt gleichartige Wünsche, setzt Status und
 * Antwort; in einer selbst betriebenen Installation sendet der
 * Plattform-Admin einzelne Wünsche über den Herstellerkanal.
 */
class PlatformWishController extends Controller {
    use RequiresPlatformOperator;

    private const array SORTS = ['forwarded_at', 'votes'];

    private const int PER_PAGE = 25;

    public function __construct(
        private readonly AppWishService $wishes,
        private readonly VendorChannel $vendor,
    ) {}

    public function index(Request $request): View {
        $this->assertPlatformOperator();
        $kind = AppWishKind::tryFrom($request->string('kind')->toString());
        $status = AppWishStatus::tryFrom($request->string('status')->toString());
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'forwarded_at', 'desc');

        // Nur führende Wünsche als Zeilen; gebündelte erscheinen darunter (Stimmen zählen mit).
        $wishes = $this->wishes->forwardedQuery()
            ->whereNull('platform_bundle_id')
            ->withCount(['votes' => static fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])
            ->when($kind !== null, static fn ($q) => $q->where('kind', $kind))
            ->when($status !== null, static fn ($q) => $status === AppWishStatus::New
                ? $q->where(static fn ($w) => $w->whereNull('platform_status')->orWhere('platform_status', AppWishStatus::New))
                : $q->where('platform_status', $status))
            ->orderBy($sort === 'votes' ? 'votes_count' : 'forwarded_at', $dir)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $leadIds = $wishes->getCollection()->modelKeys();
        $bundledWishes = $this->wishes->forwardedQuery()
            ->whereIn('platform_bundle_id', $leadIds)
            ->withCount(['votes' => static fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])
            ->get(['id', 'organization_id', 'kind', 'route_name', 'forwarded_body', 'forwarded_at', 'platform_bundle_id']);
        $bundled = $bundledWishes->groupBy('platform_bundle_id');

        return view('admin.wishes.index', [
            'wishes' => $wishes,
            'bundled' => $bundled,
            'organizations' => $this->organizationNames([...$leadIds, ...$bundledWishes->modelKeys()]),
            'filters' => ['kind' => $kind->value ?? '', 'status' => $status->value ?? ''],
            'sort' => $sort,
            'dir' => $dir,
            'vendorEnabled' => $this->vendor->enabled(),
        ]);
    }

    public function show(string $wish): View {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $members = $this->wishes->forwardedQuery()->where('platform_bundle_id', $model->id)->orderBy('forwarded_at')->get();
        $votes = AppWishVote::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('app_wish_id', [$model->id, ...$members->modelKeys()])
            ->selectRaw('app_wish_id, COUNT(*) as aggregate')
            ->groupBy('app_wish_id')
            ->pluck('aggregate', 'app_wish_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        $vendorEnabled = $this->vendor->enabled();

        return view('admin.wishes.show', [
            'wish' => $model,
            'members' => $members,
            'votes' => $votes,
            'bundleVotes' => array_sum($votes),
            'organizations' => $this->organizationNames([$model->id, ...$members->modelKeys()]),
            // Mögliche Bündel-Führer: andere führende Wünsche derselben Art.
            'leads' => $model->platform_bundle_id === null && $members->isEmpty()
                ? $this->wishes->forwardedQuery()->whereNull('platform_bundle_id')->where('kind', $model->kind)->whereKeyNot($model->id)->orderByDesc('forwarded_at')->limit(100)->get(['id', 'organization_id', 'forwarded_body', 'forwarded_at'])
                : collect(),
            'lead' => $model->platform_bundle_id !== null ? $this->wishes->forwardedQuery()->whereKey($model->platform_bundle_id)->first() : null,
            'vendorEnabled' => $vendorEnabled,
            'vendorText' => $this->vendor->text($model),
            // Vorschau genau dessen, was hinausginge (MVP-1677) — nur mit Opt-in und vor dem Versand.
            'preview' => $vendorEnabled && ! $model->isSentToVendor() && $model->platform_bundle_id === null ? $this->vendor->preview($model, $this->authUser()) : null,
        ]);
    }

    /** Status und Antwort des Betriebs (gelten für das Bündel). */
    public function update(Request $request, string $wish): RedirectResponse {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $data = $request->validate([
            'platform_status' => ['nullable', Rule::enum(AppWishStatus::class)],
            'platform_answer' => ['nullable', 'string', 'max:' . AppWishService::MAX_BODY],
        ]);

        $this->wishes->platformAnswer(
            $model,
            is_string($data['platform_status'] ?? null) && $data['platform_status'] !== '' ? AppWishStatus::from($data['platform_status']) : null,
            is_string($data['platform_answer'] ?? null) ? $data['platform_answer'] : null,
        );

        return redirect()->route('admin.wishes.show', $model->sqid)->with('success', __('wishes.flash.platform_answered'));
    }

    /** Gleichartigen Wunsch einem führenden zuordnen oder das Bündel lösen. */
    public function bundle(Request $request, string $wish): RedirectResponse {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $data = $request->validate(['lead' => ['nullable', 'string', 'max:64']]);
        $leadKey = is_string($data['lead'] ?? null) ? $data['lead'] : '';

        $this->wishes->bundle($model, $leadKey !== '' ? $this->wishes->findForwarded($leadKey) : null);

        return redirect()->route('admin.wishes.show', $model->sqid)->with('success', __($leadKey !== '' ? 'wishes.flash.bundled' : 'wishes.flash.unbundled'));
    }

    /** Fassung für den Hersteller vor dem Senden anpassen (MVP-1677). */
    public function vendorText(Request $request, string $wish): RedirectResponse {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $data = $request->validate(['vendor_body' => ['required', 'string', 'min:3', 'max:' . AppWishService::MAX_BODY]]);

        $this->vendor->saveText($model, (string) $data['vendor_body']);

        return redirect()->route('admin.wishes.show', $model->sqid)->with('success', __('wishes.flash.vendor_text_saved'));
    }

    /** Senden genau der gezeigten Vorschau an den Hersteller (MVP-1677). */
    public function vendorSend(Request $request, string $wish): RedirectResponse {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $data = $request->validate(['preview_hash' => ['required', 'string', 'max:128']]);

        $this->vendor->send($model, $this->authUser(), (string) $data['preview_hash']);

        return redirect()->route('admin.wishes.show', $model->sqid)->with('success', __('wishes.flash.vendor_sent'));
    }

    /** Antwort des Herstellers von Hand eintragen; die Organisation sieht sie. */
    public function vendorAnswer(Request $request, string $wish): RedirectResponse {
        $this->assertPlatformOperator();
        $model = $this->wishes->findForwarded($wish);
        $data = $request->validate(['vendor_answer' => ['nullable', 'string', 'max:' . AppWishService::MAX_BODY]]);

        $this->vendor->recordAnswer($model, is_string($data['vendor_answer'] ?? null) ? $data['vendor_answer'] : '');

        return redirect()->route('admin.wishes.show', $model->sqid)->with('success', __('wishes.flash.vendor_answered'));
    }

    /**
     * Name der Organisation je Wunsch — die einzige Herkunftsangabe, die der
     * Betrieb sieht.
     *
     * @param  array<int, int|string>  $wishIds
     * @return array<int, string>
     */
    private function organizationNames(array $wishIds): array {
        $orgByWish = $this->wishes->forwardedQuery()->whereIn('id', $wishIds)->pluck('organization_id', 'id')->all();
        $names = Organization::query()->whereIn('id', array_unique(array_values($orgByWish)))->pluck('name', 'id')->all();
        $out = [];
        foreach ($orgByWish as $wishId => $orgId) {
            $out[(int) $wishId] = (string) ($names[$orgId] ?? '—');
        }

        return $out;
    }
}
