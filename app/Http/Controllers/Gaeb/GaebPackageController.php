<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPackageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\Gaeb\GaebImportStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{GaebImport, Project, User};
use App\Services\Gaeb\{BoqImportConflictException, GaebImportService, GaebPackageIntakeService};
use App\Services\SqidEncoder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use RuntimeException;

/**
 * Paketeingang für Vergabeunterlagen (Feature 108, MVP-627).
 *
 * Ein Vergabeportal liefert ein ZIP, kein Leistungsverzeichnis: darin liegen
 * LV, Bewerbungsbedingungen, Pläne und Vordrucke nebeneinander. Der Eingang
 * zerlegt das Paket und legt die erkannten GAEB-Dateien als **Vorschlag** ab
 * — welches Los tatsächlich bearbeitet wird, entscheidet ein Mensch.
 */
class GaebPackageController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly GaebPackageIntakeService $intake,
        private readonly GaebImportService $importer,
    ) {}

    public function index(): View {
        Gate::authorize(P::ProjectViewAny->value);

        return view('bill-of-quantities.packages', [
            'proposals' => GaebImport::query()
                ->where('status', GaebImportStatus::Pending)
                ->whereNotNull('stored_path')
                ->with('opportunity')
                ->latest('id')
                ->paginate(25),
            'opportunities' => ApplicationOpportunity::query()
                ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
                ->orderBy('title')
                ->get(['id', 'title']),
            'projects' => Project::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'canImport' => Gate::allows(P::ProjectImport->value),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);

        $request->validate([
            // Pakete sind ZIPs, Einzeldateien sind XML oder Text — welche
            // Familie eine GAEB-Datei hat, entscheidet später der Inhalt.
            'file' => ['required', 'file', 'max:102400'],
            'opportunity' => ['nullable', 'string'],
        ]);

        $opportunity = $this->resolveOpportunity($request->input('opportunity'));

        $file = $request->file('file');
        $contents = (string) file_get_contents($file->getRealPath());

        try {
            $result = $this->intake->intake(
                $contents,
                (string) $file->getClientOriginalName(),
                $this->currentOrganization()->id,
                $this->actor(),
                $opportunity,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':gaeb GAEB-Dateien erkannt, :documents Dokumente abgelegt, :skipped übergangen.', [
            'gaeb' => count($result['gaeb']),
            'documents' => $result['documents'],
            'skipped' => $result['skipped'],
        ]));
    }

    /** Der Vorschlag wird zum Leistungsverzeichnis — jetzt erst wird gelesen. */
    public function accept(Request $request, GaebImport $import): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);
        $this->guard($import);

        $path = $import->stored_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', __('Die Paketdatei ist nicht mehr vorhanden.'));
        }

        $projectId = $this->resolveProjectId($request->input('project'));

        try {
            $created = $this->importer->import(
                (string) Storage::disk('local')->get($path),
                $import->filename,
                (int) $import->organization_id,
                [
                    'project_id' => $projectId,
                    'created_by' => $this->actor()->id,
                ],
            );
        } catch (BoqImportConflictException $e) {
            return back()->with('error', __('gaeb.flash.conflict', ['refs' => implode(', ', $e->conflictingRefs)]));
        }

        if ($created->status === GaebImportStatus::PreflightFailed) {
            $errors = $created->preflight['errors'] ?? [];

            return back()
                ->with('error', __('gaeb.flash.preflight_failed', ['count' => count($errors)]))
                ->with('gaebErrors', $errors);
        }

        // Der Vorschlag hat seinen Zweck erfüllt; der Importlauf tritt an
        // seine Stelle. Die Datei bleibt am Importlauf nachvollziehbar.
        $created->forceFill([
            'stored_path' => $path,
            'package_name' => $import->package_name,
            'application_opportunity_id' => $import->application_opportunity_id,
        ])->save();
        $import->forceFill(['stored_path' => null])->delete();

        // Ein Vergabevorgang ohne LV bekommt seines - hängt schon eines dran,
        // bleibt es dort: ein Nachtrag ersetzt kein Verzeichnis.
        $opportunity = $created->application_opportunity_id !== null
            ? ApplicationOpportunity::query()->find($created->application_opportunity_id)
            : null;
        if ($opportunity !== null && $opportunity->bill_of_quantity_id === null) {
            $opportunity->update(['bill_of_quantity_id' => $created->bill_of_quantity_id]);
        }

        return redirect()
            ->route('bill-of-quantities.show', $created->billOfQuantity)
            ->with('success', __('gaeb.flash.imported', ['items' => $created->item_count]));
    }

    public function discard(GaebImport $import): RedirectResponse {
        Gate::authorize(P::ProjectImport->value);
        $this->guard($import);

        if ($import->stored_path !== null) {
            Storage::disk('local')->delete($import->stored_path);
        }
        $import->delete();

        return back()->with('success', __('Vorschlag verworfen.'));
    }

    private function resolveOpportunity(mixed $value): ?ApplicationOpportunity {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $id = app(SqidEncoder::class)->decode(ApplicationOpportunity::class, $value);

        // Der Org-Scope liegt auf dem Modell; ein fremder Sqid findet nichts.
        return $id === null ? null : ApplicationOpportunity::query()->find($id);
    }

    private function resolveProjectId(mixed $value): ?int {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $id = app(SqidEncoder::class)->decode(Project::class, $value);
        if ($id === null) {
            return null;
        }
        abort_unless(Project::query()->whereKey($id)->exists(), 422);

        return $id;
    }

    private function guard(GaebImport $import): void {
        abort_unless($import->organization_id === $this->currentOrganization()->id, 404);
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
