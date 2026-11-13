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

use App\Enums\Privacy\RetentionProposalStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Privacy\{ComplianceFinding, RetentionProposal};
use App\Services\Retention\{RetentionRegistry, RetentionScanService};
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\ErrorText;
use CommonToolkit\Helper\Data\NumberHelper;
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
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly RetentionScanService $scanner,
        private readonly RetentionRegistry $registry,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ComplianceFinding::class);

        $proposals = RetentionProposal::query()
            ->whereIn('status', [RetentionProposalStatus::Pending, RetentionProposalStatus::Approved])
            ->orderBy('area')
            ->orderBy('retention_until')
            ->paginate(50);

        $organization = $this->currentOrganizationOrUserOrganization();

        // Alle Katalog-Bereiche (Feature 130): auch reine Ausweis-Bereiche
        // ohne Scan-Policy (time_records, location_points, documents_general)
        // erscheinen in der Fristen-Tabelle — mit Kennzeichnung.
        $areas = collect(array_keys((array) config('retention.areas')))->map(fn(string $area) => [
            'area' => $area,
            'label' => $this->registry->labelFor($area),
            'years' => $organization !== null ? $this->registry->yearsFor($organization, $area) : null,
            'days' => $this->registry->daysFor($area),
            'basis' => $organization !== null ? $this->registry->basisFor($organization, $area) : null,
            'scanned' => $this->registry->policy($area) !== null,
            // Je Organisation einstellbare Frist (MVP-1683): eigener Wert und Vorgabe des Rechtsraums.
            'adjustable' => $this->registry->yearsSettingFor($area) !== null,
            'own_years' => $organization !== null ? $this->registry->organizationYearsFor($organization, $area) : null,
            'default_years' => $organization !== null ? $this->registry->defaultYearsFor($organization, $area) : null,
        ])->values();

        return view('privacy.retention.index', [
            'proposals' => $proposals,
            'region' => $organization !== null ? $this->registry->regionFor($organization) : 'DE',
            'areas' => $areas,
            'areaLabels' => $areas->pluck('label', 'area')->all(),
            'canManage' => Gate::allows('manage', ComplianceFinding::class),
        ]);
    }

    /**
     * Eigene Frist eines einstellbaren Bereichs (MVP-1683); leer setzt auf die
     * Vorgabe des Rechtsraums zurück. Grenzen und Audit liefert die Registry.
     */
    public function updateYears(Request $request, SettingsRegistry $settings): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $organization = $this->currentOrganizationOrAbort(404);

        $data = $request->validate(['area' => ['required', 'string', 'max:40']]);
        $key = $this->registry->yearsSettingFor((string) $data['area']);
        abort_if($key === null, 404);

        $request->validate(['years' => ['nullable', ...$settings->definition($key)->validationRules()]]);
        $years = NumberHelper::toIntOrNull($request->input('years'));
        if ($years === null) {
            $settings->reset($key, SettingScope::Organization, $organization);
        } else {
            $settings->set($key, $years, SettingScope::Organization, $organization, (int) $this->authUser()->id);
        }

        return back()->with('status', __('privacy.retention.years_saved', ['area' => $this->registry->labelFor((string) $data['area'])]));
    }

    public function scan(Request $request): RedirectResponse {
        Gate::authorize('manage', ComplianceFinding::class);

        $organization = $this->currentOrganizationOrAbort(404);

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
            return back()->with('error', ErrorText::for($e));
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
        $held = 0;
        RetentionProposal::query()
            ->where('area', $data['area'])
            ->where('status', RetentionProposalStatus::Approved)
            ->orderBy('id')
            ->get()
            ->each(function (RetentionProposal $proposal) use ($actor, &$count, &$held): void {
                try {
                    $this->scanner->purge($proposal, $actor);
                    $count++;
                } catch (\App\Exceptions\Privacy\LegalHoldException) {
                    $held++;
                }
            });

        $message = __(':count Datensatz/Datensätze endgültig gelöscht.', ['count' => $count]);
        if ($held > 0) {
            $message .= ' ' . __(':count wegen Legal Hold übersprungen.', ['count' => $held]);
        }

        return back()->with('status', $message);
    }
}
