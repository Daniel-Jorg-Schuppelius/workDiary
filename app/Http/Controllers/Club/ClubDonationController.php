<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubDonationKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Club\{ClubDonation, ClubDonationReceipt, ClubFeeAccount, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\{ClubDonationReceiptPdfRenderer, ClubDonationService};
use App\Support\Sqid;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Spenden und Zuwendungsbestätigungen (Feature 159, MVP-1003); Rechte wie die Beitragsverwaltung. */
class ClubDonationController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly ClubDonationService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $organization = $this->currentOrganization();
        $year = max(2000, min((int) now()->year + 1, $request->integer('year', (int) now()->year)));

        return view('club.fees.donations.index', [
            'year' => $year,
            'donations' => ClubDonation::query()->where('organization_id', $organization->id)->whereYear('received_on', $year)
                ->with(['member', 'receipt'])->orderByDesc('received_on')->orderByDesc('id')->get(),
            'receipts' => ClubDonationReceipt::query()->where('organization_id', $organization->id)->where('year', $year)->orderByDesc('receipt_no')->get(),
            'openByDonor' => $this->service->openByDonor($organization, $year),
            'exemptionComplete' => $this->service->exemptionComplete($this->service->exemption($organization)),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function form(?ClubDonation $donation = null): View {
        Gate::authorize('create', ClubFeeAccount::class);
        $organization = $this->currentOrganization();
        abort_if($donation !== null && (int) $donation->organization_id !== (int) $organization->id, 404);

        return view('club.fees.donations._donation_dialog', [
            'donation' => $donation,
            'members' => ClubMember::query()->where('organization_id', $organization->id)->orderBy('last_name')->orderBy('first_name')->get(),
            'feesConfirmable' => $this->service->membershipFeesConfirmable($organization),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = $request->user();
        $donation = $this->service->record($this->currentOrganization(), $actor, $this->validated($request));

        return redirect()->route('club.fees.donations.index', ['year' => $donation->received_on->year])->with('success', __('club.donations.flash.saved'));
    }

    public function update(Request $request, ClubDonation $donation): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        abort_unless((int) $donation->organization_id === (int) $this->currentOrganization()->id, 404);
        $this->service->update($donation, $this->validated($request));

        return redirect()->route('club.fees.donations.index', ['year' => $donation->received_on->year])->with('success', __('club.donations.flash.saved'));
    }

    public function destroy(ClubDonation $donation): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        abort_unless((int) $donation->organization_id === (int) $this->currentOrganization()->id, 404);
        $year = $donation->received_on->year;
        $this->service->delete($donation);

        return redirect()->route('club.fees.donations.index', ['year' => $year])->with('success', __('club.donations.flash.deleted'));
    }

    public function issue(Request $request, ClubDonation $donation): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        abort_unless((int) $donation->organization_id === (int) $this->currentOrganization()->id, 404);
        /** @var User $actor */
        $actor = $request->user();
        $receipt = $this->service->issueSingle($donation, $actor);

        return redirect()->route('club.fees.donations.index', ['year' => $receipt->year])->with('success', __('club.donations.flash.issued', ['no' => $receipt->displayNo()]));
    }

    public function issueCollective(Request $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['donor' => ['required', 'string', 'max:100'], 'year' => ['required', 'integer', 'between:2000,2100']]);
        /** @var User $actor */
        $actor = $request->user();
        $receipt = $this->service->issueCollective($this->currentOrganization(), (string) $data['donor'], (int) $data['year'], $actor);

        return redirect()->route('club.fees.donations.index', ['year' => $receipt->year])->with('success', __('club.donations.flash.issued', ['no' => $receipt->displayNo()]));
    }

    public function pdf(ClubDonationReceipt $receipt, ClubDonationReceiptPdfRenderer $renderer): Response {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        abort_unless((int) $receipt->organization_id === (int) $this->currentOrganization()->id, 404);

        return response($renderer->output($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($receipt) . '"',
        ]);
    }

    /** @return array{club_member_id: ?int, donor_name: ?string, donor_address: ?string, kind: ClubDonationKind, amount: string, received_on: string, is_expense_waiver: bool, note: ?string} */
    private function validated(Request $request): array {
        $data = $request->validate([
            'member' => ['nullable', 'string'],
            'donor_name' => ['nullable', 'string', 'max:200'],
            'donor_address' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', Rule::enum(ClubDonationKind::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'received_on' => ['required', 'date'],
            'is_expense_waiver' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $memberId = null;
        if (! empty($data['member'])) {
            $memberId = (int) Sqid::decodeOrNumeric(ClubMember::class, (string) $data['member']);
            abort_unless(ClubMember::query()->where('organization_id', $this->currentOrganization()->id)->whereKey($memberId)->exists(), 422);
        }

        return [
            'club_member_id' => $memberId,
            'donor_name' => $memberId === null ? ($data['donor_name'] ?? null) : null,
            'donor_address' => $memberId === null ? ($data['donor_address'] ?? null) : null,
            'kind' => ClubDonationKind::from((string) $data['kind']),
            'amount' => Decimal::of((string) $data['amount'], 2)->getValue(),
            'received_on' => (string) $data['received_on'],
            'is_expense_waiver' => $request->boolean('is_expense_waiver'),
            'note' => $data['note'] ?? null,
        ];
    }
}
