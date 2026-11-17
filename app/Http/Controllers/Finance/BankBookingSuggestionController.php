<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankBookingSuggestionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Finance\BankTransaction;
use App\Services\Accounting\Posting\{BankBookingSuggestions, PostingInboxService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kontierungsvorschläge für Bankumsätze ohne Beleg (Feature 207, MVP-1660):
 * gleichartige offene Umsätze gebündelt mit Vorschau, eine Bestätigung für
 * alle; abweichende Beträge stehen einzeln. Gebucht wird über den
 * {@see BankBookingSuggestions}-Dienst und damit über denselben Weg wie
 * „Auf Sachkonto buchen“ — samt Vier-Augen-Prinzip.
 */
class BankBookingSuggestionController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly BankBookingSuggestions $suggestions,
        private readonly PostingInboxService $inbox,
    ) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting.suggestions', [
            'overview' => $this->suggestions->overview($organization),
            'fourEyes' => $this->inbox->fourEyesEnabled(),
        ]);
    }

    /** Eine Gruppe bestätigen: nur die angezeigten Umsätze, nur mit unverändertem Vorschlag. */
    public function confirm(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $request->validate([
            'group' => ['required', 'string', 'max:96', 'regex:/^[a-z]+(-\d+){4}$/'],
            'transactions' => ['required', 'array', 'min:1', 'max:500'],
            'transactions.*' => ['required', 'string', 'max:64'],
        ]);

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $value): ?int => Sqid::decodeOrNumeric(BankTransaction::class, $value),
            $data['transactions'],
        ))));

        $result = $this->suggestions->confirm($organization, (string) $data['group'], $ids, $this->authUser());

        $redirect = redirect()->route('finance.accounting.inbox.suggestions.index')->with('status', __('accounting.suggestions.flash.confirmed', [
            'posted' => $result['posted'],
            'submitted' => $result['submitted'],
            'skipped' => $result['skipped'],
        ]));

        return $result['failed'] === [] ? $redirect : $redirect->with('error', implode(' ', array_unique($result['failed'])));
    }
}
