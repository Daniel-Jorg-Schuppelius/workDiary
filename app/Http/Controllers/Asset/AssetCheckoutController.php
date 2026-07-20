<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCheckoutController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Asset;

use App\Exceptions\AssetValidationException;
use App\Http\Controllers\Controller;
use App\Models\{Asset, AssetAssignment, DiaryEntry, Team, User};
use App\Services\Asset\AssetAssignmentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssetCheckoutController extends Controller {
    public function __construct(private readonly AssetAssignmentService $assignments) {}

    public function create(Asset $asset): View {
        Gate::authorize('checkout', $asset);

        /** @var User $auth */
        $auth = auth()->user();

        return view('assets._checkout_form_dialog', [
            'asset' => $asset,
            // Nur Nutzer der eigenen Organisation (Whitebox-Befund 2026-07).
            'users' => User::query()->where('organization_id', $auth->organization_id)->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'diaryEntries' => $asset->diaryEntries()->limit(30)->get(['id', 'title', 'start_at']),
        ]);
    }

    public function store(Asset $asset, Request $request): RedirectResponse {
        Gate::authorize('checkout', $asset);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'string'],
            'assigned_to_team_id' => ['nullable', 'string'],
            'diary_entry_id' => ['nullable', 'string'],
            'expected_return_at' => ['nullable', 'date'],
            'condition_out' => ['nullable', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $targetUser = $this->resolveUser($validated['assigned_to_user_id'] ?? null);
        $targetTeam = $this->resolveTeam($validated['assigned_to_team_id'] ?? null);
        $diaryEntry = $this->resolveDiaryEntry($validated['diary_entry_id'] ?? null);
        $expectedReturnAt = ! empty($validated['expected_return_at'])
            ? Carbon::parse((string) $validated['expected_return_at'])
            : null;

        try {
            $this->assignments->checkOut(
                $asset,
                $user,
                $targetUser,
                $targetTeam,
                $expectedReturnAt,
                $diaryEntry,
                $validated['condition_out'] ?? null,
                $validated['note'] ?? null,
            );
        } catch (AssetValidationException $exception) {
            return back()->withInput()->withErrors(['assigned_to_user_id' => __($exception->getMessage())]);
        } catch (\App\Exceptions\AssetNotUsableException $exception) {
            // Vollaudit 2026-07 (H2/H3): D12-Sperre (asset_blocks) blockt die Ausgabe.
            return back()->withInput()->withErrors(['assigned_to_user_id' => $exception->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('Asset ausgegeben.'));
    }

    public function update(Asset $asset, AssetAssignment $assignment, Request $request): RedirectResponse {
        Gate::authorize('checkout', $asset);
        $this->ensureAssignmentBelongsToAsset($asset, $assignment);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'condition_in' => ['nullable', 'string', 'max:180'],
        ]);

        try {
            $this->assignments->checkIn($assignment, $user, $validated['condition_in'] ?? null);
        } catch (AssetValidationException $exception) {
            return back()->withInput()->withErrors(['condition_in' => __($exception->getMessage())]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('Asset zurückgenommen.'));
    }

    private function ensureAssignmentBelongsToAsset(Asset $asset, AssetAssignment $assignment): void {
        if ($assignment->asset_id !== $asset->id) {
            abort(404);
        }
    }

    private function resolveUser(?string $raw): ?User {
        $id = $this->decodeId(User::class, $raw);
        if ($id === null) {
            return null;
        }

        // Mandantengrenze: Ausgabe nur an Nutzer der eigenen Org — User hat keinen
        // globalen OrganizationScope (Whitebox-Befund 2026-07).
        $authUser = auth()->user();
        $orgId = $authUser instanceof User ? $authUser->organization_id : null;

        return User::query()
            ->when($orgId !== null, fn ($q) => $q->where('organization_id', $orgId))
            ->find($id);
    }

    private function resolveTeam(?string $raw): ?Team {
        $id = $this->decodeId(Team::class, $raw);

        return $id !== null ? Team::query()->find($id) : null;
    }

    private function resolveDiaryEntry(?string $raw): ?DiaryEntry {
        $id = $this->decodeId(DiaryEntry::class, $raw);

        return $id !== null ? DiaryEntry::query()->find($id) : null;
    }

    /** @param  class-string<\Illuminate\Database\Eloquent\Model>  $model */
    private function decodeId(string $model, ?string $raw): ?int {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return Sqid::decodeOrNumeric($model, $raw);
    }
}
