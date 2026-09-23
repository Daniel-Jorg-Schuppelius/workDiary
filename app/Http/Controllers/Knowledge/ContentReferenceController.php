<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentReferenceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Knowledge;

use App\Models\Knowledge\ContentCollection;
use App\Models\Knowledge\ContentReference;
use App\Models\Platform\User;
use App\Services\Collections\{CollectableTypes, ContentReferenceService};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Http\Controllers\Controller;

/**
 * Verweise setzen und lösen (MVP-811, Feature 155). Verweise ordnen wie
 * Sammlungen und laufen deshalb über dasselbe Recht `collection.manage`.
 */
class ContentReferenceController extends Controller {
    public function __construct(
        private readonly ContentReferenceService $service,
        private readonly CollectableTypes $types,
    ) {}

    /** Dialog „Verweis setzen“ von der Detailseite eines Inhalts. */
    public function create(Request $request): View {
        Gate::authorize('create', ContentCollection::class);
        $type = (string) $request->query('type', '');
        $source = $this->resolveItem($type, (string) $request->query('item', ''));

        return view('collections._reference_dialog', [
            'type' => $type,
            'itemSqid' => (string) $request->query('item'),
            'itemTitle' => $this->types->title($source),
            'groups' => $this->service->pickerOptions($source, $this->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ContentCollection::class);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in($this->types->keys())],
            'item' => ['required', 'string', 'max:64'],
            'target' => ['required', 'string', 'max:96', 'regex:/^[a-z_]+:[A-Za-z0-9]+$/'],
        ]);

        [$targetType, $targetSqid] = explode(':', (string) $data['target'], 2);
        $source = $this->resolveItem((string) $data['type'], (string) $data['item']);
        $target = $this->resolveItem($targetType, $targetSqid);

        $this->service->add($source, $target, $this->user());

        return redirect()->back()->with('success', __('collections.references.flash.added', ['title' => $this->types->title($target)]));
    }

    public function destroy(ContentReference $reference): RedirectResponse {
        Gate::authorize('create', ContentCollection::class);

        $this->service->remove($reference, $this->user());

        return redirect()->back()->with('success', __('collections.references.flash.removed'));
    }

    /** Inhalt aus Typ und Kennung — nur, wenn die Person ihn sehen darf. */
    private function resolveItem(string $type, string $sqid): Model {
        $class = $this->types->classFor($type);
        abort_if($class === null, 404);
        $id = Sqid::decode($class, $sqid);
        abort_if($id === null, 404);

        $visible = $this->types->visible($type, $this->user(), [$id]);
        abort_if($visible === [], 404);

        return $visible[0];
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
