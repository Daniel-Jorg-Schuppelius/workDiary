<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdvisoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\AdvisoryFormat;
use App\Http\Controllers\Controller;
use App\Models\Isms\IsmsAdvisory;
use App\Models\{Organization, User};
use App\Services\Isms\AdvisoryImportService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ISMS-Advisories (Feature 044, MVP 2): Liste der importierten CSAF/VEX-
 * Dokumente (Nachweis-Ablage mit SHA-256) und das Import-Upload-Modal. Der
 * Import parst nativ per json_decode, gleicht gegen Inventar + Release-SBOM
 * ab und erzeugt Schwachstelleneinträge (AdvisoryImportService); Treffer
 * werden NIE automatisch als ausnutzbar markiert.
 */
class AdvisoryController extends Controller {
    public function __construct(
        private readonly AdvisoryImportService $service,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', IsmsAdvisory::class);

        return view('isms.advisories.index', [
            'advisories' => IsmsAdvisory::query()
                ->with('importedBy')
                ->withCount('vulnerabilities')
                ->orderByDesc('created_at')
                ->paginate(25),
            'canManage' => Gate::allows('create', IsmsAdvisory::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsAdvisory::class);

        return view('isms.advisories._import_dialog');
    }

    /**
     * Import eines CSAF/VEX-JSON-Uploads. Re-Import ist idempotent
     * (file_hash); das Ergebnis (Zahl der erzeugten Schwachstellen) wird als
     * Flash gemeldet.
     */
    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsAdvisory::class);

        $data = $request->validate([
            'format' => ['required', 'string', Rule::enum(AdvisoryFormat::class)],
            'advisory' => ['required', 'file', 'mimetypes:application/json,text/plain,text/json', 'max:5120'],
        ]);

        /** @var User $importer */
        $importer = Auth::user();
        /** @var Organization $organization */
        $organization = $importer->organization()->firstOrFail();

        $content = (string) $request->file('advisory')->get();

        $advisory = $this->service->importCsaf(
            $content,
            $organization,
            $importer,
            AdvisoryFormat::from($data['format']),
        );

        return redirect()
            ->route('isms.vulnerabilities.index')
            ->with('success', __('isms.flash.advisory_imported', [
                'title' => $advisory->title,
                'count' => $advisory->vuln_count,
            ]));
    }
}
