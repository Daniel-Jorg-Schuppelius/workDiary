<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageLinkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\{ContentReference, KnowledgePage};
use App\Services\Knowledge\Pages\KnowledgePageLinks;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verweise Wissensseite ↔ Kunde, Projekt, Asset, Auftrag, Ticket und
 * Prozedurvorlage (Feature 191, MVP-1667) — von der Seite aus über den
 * Dialog, vom Objekt aus über die Karte „Wissensseiten“.
 */
class KnowledgePageLinkController extends Controller {
    public function __construct(private readonly KnowledgePageLinks $links) {}

    public function create(KnowledgePage $page): View {
        Gate::authorize('link', $page);

        return view('knowledge-pages._link_dialog', [
            'page' => $page,
            'groups' => $this->links->pickerOptions($page, $this->authUser()),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'page' => ['required', 'string', 'max:64'],
            'target' => ['required', 'string', 'max:96', 'regex:/^[a-z_]+:[A-Za-z0-9]+$/'],
        ]);
        $pageId = Sqid::decode(KnowledgePage::class, (string) $data['page']);
        $page = $pageId !== null ? KnowledgePage::query()->find($pageId) : null;
        abort_if(! $page instanceof KnowledgePage, 404);
        Gate::authorize('link', $page);
        [$type, $sqid] = explode(':', (string) $data['target'], 2);
        $subject = $this->links->findVisibleOrFail($type, $sqid, $this->authUser());
        $this->links->link($page, $subject, $this->authUser());

        return back()->with('success', __('knowledge_pages.flash.linked', ['title' => $page->title]));
    }

    public function destroy(KnowledgePage $page, ContentReference $reference): RedirectResponse {
        Gate::authorize('link', $page);
        $this->links->unlink($page, $reference);

        return back()->with('success', __('knowledge_pages.flash.unlinked'));
    }
}
