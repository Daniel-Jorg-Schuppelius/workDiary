<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Wishes;

use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wishes\StoreAppWishRequest;
use App\Models\Wishes\{AppWish, AppWishVote};
use App\Services\Wishes\AppWishService;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659): Liste der Organisation
 * mit Filter und Sortierung nach Stimmen oder Datum, Dialog aus der
 * Kopfleiste (nimmt die Seite mit), Stimme, Antwort und Weitergabe an den
 * Plattformbetrieb. Wünsche fremder Organisationen löst die Bindung nicht auf
 * (Organisations-Scope), `assertSameOrganization` sichert zusätzlich.
 */
class AppWishController extends Controller {
    use ResolvesCurrentOrganization;

    private const array SORTS = ['created_at', 'votes'];

    private const int PER_PAGE = 25;

    public function __construct(private readonly AppWishService $wishes) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', AppWish::class);
        $viewer = $this->authUser();
        $organization = $this->currentOrganization();
        $kind = AppWishKind::tryFrom($request->string('kind')->toString());
        $status = AppWishStatus::tryFrom($request->string('status')->toString());
        $open = $request->boolean('open');
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'created_at', 'desc');

        $wishes = AppWish::query()
            ->where('organization_id', $organization->id)
            ->with('author:id,name')
            ->withCount('votes')
            ->when($kind !== null, static fn ($q) => $q->where('kind', $kind))
            ->when($status !== null, static fn ($q) => $q->where('status', $status))
            ->when($open, static fn ($q) => $q->whereIn('status', [AppWishStatus::New, AppWishStatus::InReview, AppWishStatus::Planned]))
            ->orderBy($sort === 'votes' ? 'votes_count' : 'created_at', $dir)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $voted = AppWishVote::query()
            ->where('user_id', $viewer->id)
            ->whereIn('app_wish_id', $wishes->getCollection()->modelKeys())
            ->pluck('app_wish_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return view('wishes.index', [
            'wishes' => $wishes,
            'voted' => $voted,
            'canManage' => Gate::allows('manage', AppWish::class),
            'filters' => ['kind' => $kind->value ?? '', 'status' => $status->value ?? '', 'open' => $open],
            'sort' => $sort,
            'dir' => $dir,
            'highlight' => $request->string('wish')->toString(),
            'dailyLimit' => AppWishService::DAILY_LIMIT,
        ]);
    }

    /** Dialog aus der Kopfleiste: `?route=` ist die Seite, auf der die Person gerade war. */
    public function create(Request $request): View {
        Gate::authorize('create', AppWish::class);

        return view('wishes._form_dialog', [
            'wish' => null,
            'routeName' => $request->string('route')->limit(150, '')->toString(),
            'remaining' => max(0, AppWishService::DAILY_LIMIT - $this->wishes->createdToday($this->authUser())),
        ]);
    }

    public function store(StoreAppWishRequest $request): RedirectResponse {
        Gate::authorize('create', AppWish::class);
        $data = $request->validated();

        $wish = $this->wishes->create(
            $this->authUser(),
            $this->currentOrganization(),
            AppWishKind::from((string) $data['kind']),
            (string) $data['body'],
            is_string($data['route_name'] ?? null) ? $data['route_name'] : null,
        );

        return redirect()->route('wishes.index', ['wish' => $wish->sqid])->with('success', __('wishes.flash.created'));
    }

    public function edit(AppWish $appWish): View {
        $this->assertSameOrganization($appWish);
        Gate::authorize('update', $appWish);

        return view('wishes._form_dialog', [
            'wish' => $appWish,
            'routeName' => $appWish->route_name ?? '',
            'remaining' => null,
        ]);
    }

    public function update(StoreAppWishRequest $request, AppWish $appWish): RedirectResponse {
        $this->assertSameOrganization($appWish);
        Gate::authorize('update', $appWish);
        $data = $request->validated();

        $this->wishes->update($appWish, AppWishKind::from((string) $data['kind']), (string) $data['body']);

        return redirect()->toList('wishes.index')->with('success', __('wishes.flash.updated'));
    }

    public function vote(AppWish $appWish): RedirectResponse {
        $this->assertSameOrganization($appWish);
        Gate::authorize('vote', $appWish);
        $this->wishes->vote($appWish, $this->authUser());

        return back()->with('success', __('wishes.flash.voted'));
    }

    public function unvote(AppWish $appWish): RedirectResponse {
        $this->assertSameOrganization($appWish);
        $this->wishes->unvote($appWish, $this->authUser());

        return back()->with('success', __('wishes.flash.unvoted'));
    }

    public function answerDialog(AppWish $appWish): View {
        $this->assertSameOrganization($appWish);
        Gate::authorize('answer', $appWish);

        return view('wishes._answer_dialog', ['wish' => $appWish]);
    }

    public function answer(Request $request, AppWish $appWish): RedirectResponse {
        $this->assertSameOrganization($appWish);
        Gate::authorize('answer', $appWish);
        $data = $request->validate([
            'status' => ['required', Rule::enum(AppWishStatus::class)],
            'answer' => ['nullable', 'string', 'max:' . AppWishService::MAX_BODY],
        ]);

        $this->wishes->answer($appWish, $this->authUser(), AppWishStatus::from((string) $data['status']), is_string($data['answer'] ?? null) ? $data['answer'] : null);

        return redirect()->toList('wishes.index')->with('success', __('wishes.flash.answered'));
    }

    /** Weitergabe an den Plattformbetrieb (MVP-1659): Text vor dem Senden bereinigen. */
    public function forwardDialog(AppWish $appWish): View {
        $this->assertSameOrganization($appWish);
        Gate::authorize('forward', $appWish);

        return view('wishes._forward_dialog', [
            'wish' => $appWish,
            'votes' => $appWish->votes()->count(),
        ]);
    }

    public function forward(Request $request, AppWish $appWish): RedirectResponse {
        $this->assertSameOrganization($appWish);
        Gate::authorize('forward', $appWish);
        $data = $request->validate([
            'forwarded_body' => ['required', 'string', 'min:3', 'max:' . AppWishService::MAX_BODY],
            'confirm_clean' => ['accepted'],
        ]);

        $this->wishes->forward($appWish, $this->authUser(), (string) $data['forwarded_body']);

        return redirect()->toList('wishes.index')->with('success', __('wishes.flash.forwarded'));
    }
}
