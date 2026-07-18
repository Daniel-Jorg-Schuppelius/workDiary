<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDefectController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Asset;

use App\Enums\Asset\DefectSeverity;
use App\Exceptions\AssetValidationException;
use App\Http\Controllers\Controller;
use App\Models\{Asset, AssetDefect, User};
use App\Services\Asset\AssetAssignmentService;
use App\Services\Attachments\FileAttacher;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssetDefectController extends Controller {
    public function __construct(private readonly AssetAssignmentService $assignments) {}

    public function create(Asset $asset): View {
        Gate::authorize('manageDefects', $asset);

        return view('assets._defect_form_dialog', [
            'asset' => $asset,
            'severityOptions' => $this->severityOptions(),
        ]);
    }

    public function store(Asset $asset, Request $request): RedirectResponse {
        Gate::authorize('manageDefects', $asset);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'severity' => ['required', 'string', 'in:' . implode(',', array_map(fn(DefectSeverity $s): string => $s->value, DefectSeverity::cases()))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'blocks_usage' => ['nullable', 'boolean'],
            // Fotos direkt bei der Meldung (mobil per Kamera-Capture).
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:' . FileAttacher::maxKb()],
        ]);

        $defect = $this->assignments->reportDefect($asset, $user, [
            'severity' => $validated['severity'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'blocks_usage' => (bool) ($validated['blocks_usage'] ?? false),
        ]);

        $this->attachPhotos($defect, $user, (array) $request->file('photos', []));

        return redirect()->route('assets.show', $asset)->with('success', __('Defekt gemeldet.'));
    }

    public function resolveForm(Asset $asset, AssetDefect $defect, Request $request): View {
        Gate::authorize('manageDefects', $asset);
        $this->ensureDefectBelongsToAsset($asset, $defect);

        $action = $request->string('action')->toString();
        if (! in_array($action, ['resolve', 'writeOff'], true)) {
            abort(404);
        }

        return view('assets._defect_resolve_dialog', [
            'asset' => $asset,
            'defect' => $defect,
            'action' => $action,
        ]);
    }

    public function transition(Asset $asset, AssetDefect $defect, Request $request): RedirectResponse {
        Gate::authorize('manageDefects', $asset);
        $this->ensureDefectBelongsToAsset($asset, $defect);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:inRepair,resolve,writeOff'],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = (string) ($validated['resolution_note'] ?? '');

        try {
            match ((string) $validated['action']) {
                'inRepair' => $this->assignments->markInRepair($defect, $user),
                'resolve' => $this->assignments->resolveDefect($defect, $user, $note),
                'writeOff' => $this->assignments->writeOff($defect, $user, $note),
                default => abort(422),
            };
        } catch (AssetValidationException $exception) {
            return back()->withErrors(['resolution_note' => __($exception->getMessage())]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('Defektstatus aktualisiert.'));
    }

    private function ensureDefectBelongsToAsset(Asset $asset, AssetDefect $defect): void {
        if ($defect->asset_id !== $asset->id) {
            abort(404);
        }
    }

    /**
     * Legt die hochgeladenen Fotos als Anhänge am Defekt ab (Whitelist bereits
     * validiert). Fehlerhafte Uploads werden übersprungen.
     *
     * @param  array<int, mixed>  $photos
     */
    private function attachPhotos(AssetDefect $defect, User $user, array $photos): void {
        $folder = 'assets/defects/' . now()->format('Y/m');

        foreach ($photos as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }
            $ext = strtolower($photo->getClientOriginalExtension() ?: ($photo->extension() ?: 'jpg'));
            $path = $photo->storeAs($folder, Str::uuid()->toString() . '.' . $ext, 'local');
            if ($path === false) {
                continue;
            }

            $defect->attachments()->create([
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => \App\Support\Filename::sanitize($photo->getClientOriginalName()),
                'mime' => $photo->getMimeType() ?: 'application/octet-stream',
                'size' => $photo->getSize(),
                'meta_type' => AssetDefect::PHOTO_META,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function severityOptions(): array {
        return collect(DefectSeverity::cases())
            ->mapWithKeys(fn(DefectSeverity $s): array => [$s->value => $s->label()])
            ->all();
    }
}
