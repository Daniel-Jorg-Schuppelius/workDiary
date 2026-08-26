<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\{ComplianceFinding, RetentionProposal};
use App\Models\User;
use App\Services\Privacy\Retention\{RetentionRegistry, RetentionScanService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Aufbewahrungs-Review (Restpunkt 66): Lösch-Vorschläge des Scans sichten
 * und zweistufig bestätigen (approve → purge) oder ablehnen — gebündelt je
 * Bereich oder einzeln. Rechte folgen der Compliance-Verwaltung
 * (Datenschutz-Rolle).
 */
class RetentionController extends Controller {
    public function __construct(
        private readonly RetentionScanService $scanner,
        private readonly RetentionRegistry $registry,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ComplianceFinding::class);

        $proposals = RetentionProposal::query()
            ->whereIn('status', [RetentionProposal::STATUS_PENDING, RetentionProposal::STATUS_APPROVED])
            ->orderBy('area')
            ->orderBy('retention_until')
            ->paginate(50);

        $organization = Auth::user()?->organization;

        return view('privacy.retention.index', [
            'proposals' => $proposals,
            'region' => $organization !== null ? $this->registry->regionFor($organization) : 'DE',
            // Alle Katalog-Bereiche (Feature 130): auch reine Ausweis-Bereiche
            // ohne Scan-Policy (time_records, location_points, documents_general)
            // erscheinen in der Fristen-Tabelle — mit Kennzeichnung.
            'areas' => collect(array_keys((array) config('retention.areas')))->map(fn(string $area) => [
                'area' => $area,
                'label' => (string) config("retention.areas.{$area}.label", $area),
                'years' => $organization !== null ? $this->registry->yearsFor($organization, $area) : null,
                'days' => $this->registry->daysFor($area),
                'basis' => $organization !== null ? $this->registry->basisFor($organization, $area) : null,
                'scanned' => $this->registry->policy($area) !== null,
            ])->values(),
            'canManage' => Gate::allows('manage', ComplianceFinding::class),
        ]);
    }

    public function scan(Request $request): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $organization = $request->user()?->organization;
        abort_unless($organization !== null, 404);

        $result = $this->scanner->scan($organization);

        return back()->with('status', __(':proposed Vorschlag/Vorschläge erzeugt, :exempt Ausnahme(n).', $result));
    }

    /** Einzel- oder Bündel-Entscheidung (approve/reject/purge). */
    public function decide(Request $request, RetentionProposal $proposal): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $data = $request->validate(['action' => ['required', 'in:approve,reject,purge']]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($data['action']) {
                'approve' => $this->scanner->approve($proposal, $actor),
                'reject' => $this->scanner->reject($proposal, $actor),
                'purge' => $this->scanner->purge($proposal, $actor),
                default => throw new RuntimeException('Unbekannte Aktion.'),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Vorschlag aktualisiert.'));
    }

    /** Gebündelt: alle bestätigten Vorschläge eines Bereichs löschen. */
    public function purgeArea(Request $request): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $data = $request->validate(['area' => ['required', 'string', 'max:40']]);

        /** @var User $actor */
        $actor = Auth::user();

        $count = 0;
        RetentionProposal::query()
            ->where('area', $data['area'])
            ->where('status', RetentionProposal::STATUS_APPROVED)
            ->orderBy('id')
            ->get()
            ->each(function (RetentionProposal $proposal) use ($actor, &$count): void {
                $this->scanner->purge($proposal, $actor);
                $count++;
            });

        return back()->with('status', __(':count Datensatz/Datensätze endgültig gelöscht.', ['count' => $count]));
    }
}
