<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeRunController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Club\{ClubFeeAccount, ClubFeeRun, ClubMember};
use App\Models\User;
use App\Services\Club\ClubFeeRunService;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Beitragsläufe (Feature 159, MVP-850): Entwurf mit eingefrorener Vorschau,
 * Neuberechnung, Freigabe, Übergabeliste (CSV) bei externer Rechnungshoheit.
 */
class ClubFeeRunController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubFeeRunService $runs,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);

        return view('club.fees.runs.index', [
            'runs' => ClubFeeRun::query()->with('releasedBy:id,name')->orderByDesc('year')->orderByDesc('month')->orderByDesc('id')->paginate(50),
            'external' => $this->runs->externalBillingMode($this->currentOrganization()),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.runs._run_dialog', ['month' => CarbonImmutable::today()->startOfMonth()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('Y-m', (string) $data['month']);
        abort_unless($month instanceof CarbonImmutable, 422);
        /** @var User $actor */
        $actor = Auth::user();
        $run = $this->runs->prepare($this->currentOrganization(), (int) $month->year, (int) $month->month, $actor);

        return redirect()->route('club.fees.runs.show', $run)->with('success', __('club.fees.flash.run_prepared'));
    }

    public function show(ClubFeeRun $run): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $positions = collect($run->positions ?? []);
        $accountIds = $positions->pluck('account_id')->unique()->values();
        $memberIds = $positions->pluck('member_id')->filter()->unique()->values();

        return view('club.fees.runs.show', [
            'run' => $run,
            'positions' => $positions,
            'accounts' => ClubFeeAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id'),
            'members' => ClubMember::query()->whereIn('id', $memberIds)->get()->keyBy('id'),
            'claims' => $run->claims()->with('account:id,name')->orderBy('sequence')->get(),
            'external' => $this->runs->externalBillingMode($this->currentOrganization()),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function recalculate(ClubFeeRun $run): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->runs->prepare($this->currentOrganization(), $run->year, $run->month, $actor, $run);

        return redirect()->route('club.fees.runs.show', $run)->with('success', __('club.fees.flash.run_recalculated'));
    }

    public function release(ClubFeeRun $run): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $claims = $this->runs->release($run, $actor);

        return redirect()->route('club.fees.runs.show', $run)->with('success', __('club.fees.flash.run_released', ['count' => $claims->count()]));
    }

    public function cancel(ClubFeeRun $run): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->runs->cancelRun($run, $actor);

        return redirect()->route('club.fees.runs.index')->with('success', __('club.fees.flash.run_cancelled'));
    }

    /** Übergabeliste (CSV) der eingefrorenen Vorschau — bei externer Rechnungshoheit der Weg statt der lokalen Freigabe. */
    public function export(ClubFeeRun $run): StreamedResponse {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $positions = collect($run->positions ?? []);
        $accounts = ClubFeeAccount::query()->whereIn('id', $positions->pluck('account_id')->unique())->with('customer:id,number,name')->get()->keyBy('id');
        $members = ClubMember::query()->whereIn('id', $positions->pluck('member_id')->filter()->unique())->get()->keyBy('id');
        $rows = $positions->map(function (array $p) use ($accounts, $members): array {
            $account = $accounts->get((int) $p['account_id']);
            $member = $p['member_id'] ? $members->get((int) $p['member_id']) : null;

            return [
                (string) ($account?->customer->number ?? ''),
                (string) ($account->name ?? ''),
                $member?->displayNo() ?? '',
                $member?->fullName() ?? '',
                (string) $p['kind'],
                (string) $p['label'],
                (string) $p['period_start'],
                (string) $p['period_end'],
                (string) $p['due_on'],
                Money::of((string) $p['amount'], \CommonToolkit\Enums\CurrencyCode::tryFrom((string) $p['currency']) ?? \CommonToolkit\Enums\CurrencyCode::Euro)->getAmount(),
                (string) $p['currency'],
                (string) $p['source_key'],
            ];
        })->all();
        $run->audit('club.fee.runExported', ['rows' => count($rows)]);

        return CsvExport::streamFromRows('beitragslauf-' . $run->year . '-' . str_pad((string) $run->month, 2, '0', STR_PAD_LEFT) . '.csv', [
            __('club.fees.field.customer'), __('club.fees.field.account'), __('club.field.member_no'), __('club.field.member'), __('club.fees.field.position'), __('club.fees.field.name'),
            __('club.field.valid_from'), __('club.field.valid_to'), __('club.fees.field.due_on'), __('club.fees.field.amount'), __('club.fees.field.currency'), __('club.fees.field.source_key'),
        ], $rows);
    }
}
