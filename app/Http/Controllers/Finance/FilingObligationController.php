<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingObligationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{FilingObligationStatus, VatFilingInterval};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingFilingObligation;
use App\Services\Accounting\Filing\FilingObligationService;
use App\Services\Accounting\VatFilingProfileResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Steuertermine (Feature 125, MVP-686).
 *
 * Die Seite zeigt berechnete Fristen und hält fest, was erledigt ist. Sie
 * übermittelt nichts — abgegeben wird weiterhin bei ELSTER bzw. über die
 * Steuerberatung.
 */
class FilingObligationController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly FilingObligationService $obligations,
        private readonly VatFilingProfileResolver $profile,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $year = (int) $request->query('year', (string) now()->year);
        // Beim Aufruf abgleichen: Termine folgen dem Profil, nicht einem
        // eingefrorenen Kalender.
        if (Gate::allows(Permission::AccountingLedgerPost->value)) {
            $this->obligations->syncYear($organization, $year);
        }

        $from = CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 1, 1));

        return view('finance.accounting.filings', [
            'year' => $year,
            'years' => range((int) now()->year + 1, (int) now()->year - 3),
            'obligations' => $this->obligations->inRange($organization, $from, $from->endOfYear()->addMonths(8)),
            'overdue' => $this->obligations->overdue($organization),
            'interval' => $this->profile->at($organization),
            'hasExtension' => $this->profile->hasExtension($organization),
            'taxAdvised' => $this->obligations->taxAdvised(),
            'noReturns' => $this->profile->at($organization) === VatFilingInterval::None,
            'canManage' => Gate::allows(Permission::AccountingLedgerPost->value),
            'statuses' => FilingObligationStatus::cases(),
        ]);
    }

    /** Erledigung festhalten — abgegeben oder bewusst nicht erforderlich. */
    public function mark(Request $request, AccountingFilingObligation $obligation): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $obligation->organization_id === (int) $organization->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_column(FilingObligationStatus::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $status = FilingObligationStatus::from((string) $data['status']);

        // „Nicht erforderlich" braucht einen Grund — sonst verschwindet eine
        // Pflicht lautlos aus der Liste.
        if ($status === FilingObligationStatus::NotRequired && trim((string) ($data['note'] ?? '')) === '') {
            return back()->withErrors(['note' => __('accounting.filing.error.note_required')]);
        }

        $this->obligations->mark($obligation, $status, $actor, $data['note'] ?? null);

        return back()->with('status', __('accounting.filing.flash.marked'));
    }
}
