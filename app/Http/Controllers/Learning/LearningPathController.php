<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPathController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningPath, LearningPathItem};
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Learning\LearningPathService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Lernpfade (Feature 149, MVP-745): eine Reihenfolge von Kursen mit
 * Fristen — die Einarbeitung, nicht ein zweiter Pflichtkatalog. Das Soll
 * bleibt bei Feature 145.
 */
class LearningPathController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly LearningPathService $paths,
    ) {}

    public function index(): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.paths.index', [
            'paths' => LearningPath::query()
                ->withCount('items')
                ->orderBy('title')
                ->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_role' => ['nullable', 'string', 'max:60'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        LearningPath::query()->create($data + [
            'organization_id' => $this->currentOrganization()->id,
            'is_active' => true,
        ]);

        return redirect()->route('learning.paths.index')
            ->with('success', __('learning.flash.path_created'));
    }

    public function show(LearningPath $path): View {
        Gate::authorize(Permission::LearningManage->value);

        $path->load(['items.course']);

        return view('learning.paths.show', [
            'path' => $path,
            'courses' => LearningCourse::query()->orderBy('title')->get(),
            // Auf die eigene Organisation gescopt — eine Auswahlliste mit
            // fremder Belegschaft wäre ein Datenabfluss.
            'users' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(),
        ]);
    }

    public function storeItem(Request $request, LearningPath $path): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        // Das Formular sendet Sqids; numerische IDs bleiben gültig.
        $request->merge(['learning_course_id' => Sqid::decodeOrNumeric(LearningCourse::class, $request->input('learning_course_id'))]);

        $data = $request->validate([
            'learning_course_id' => ['required', 'integer', new ExistsInCurrentOrganization('learning_courses')],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        LearningPathItem::query()->create([
            'organization_id' => $path->organization_id,
            'learning_path_id' => $path->id,
            'learning_course_id' => (int) $data['learning_course_id'],
            'position' => (int) $path->items()->max('position') + 1,
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? true),
            'due_days' => $data['due_days'] ?? null,
        ]);

        return redirect()->route('learning.paths.show', $path->sqid)
            ->with('success', __('learning.flash.path_item_added'));
    }

    public function destroyItem(LearningPath $path, LearningPathItem $item): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);
        abort_unless($item->learning_path_id === $path->id, 404);

        $item->delete();

        return redirect()->route('learning.paths.show', $path->sqid);
    }

    /** Pfad einer Person zuweisen — über die reguläre Einschreibung. */
    public function assign(Request $request, LearningPath $path): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);

        $data = $request->validate([
            'user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);
        $created = $this->paths->assign($path, $user);

        return redirect()->route('learning.paths.show', $path->sqid)
            ->with('success', __('learning.flash.path_assigned', ['count' => count($created)]));
    }

    /**
     * Automatische Zuweisung nach Zielrolle. Läuft wiederholt — doppelte
     * Zuweisung ist kein Fehler, sondern ein No-Op.
     */
    public function assignByRole(): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $result = $this->paths->assignByRole($this->currentOrganization());

        return redirect()->route('learning.paths.index')
            ->with('success', __('learning.flash.paths_assigned', $result));
    }

}
