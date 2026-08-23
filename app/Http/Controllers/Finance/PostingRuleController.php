<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTaxCode};
use App\Services\Accounting\Posting\PostingRuleResolver;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Buchungsregeln (Feature 125, MVP-673): Quelle + Rolle → Konto.
 *
 * Änderungen erzeugen eine neue Fassung (Version + Gültigkeitsbeginn) statt
 * die alte zu überschreiben — sonst würde eine Kontenumstellung rückwirkend
 * behaupten, es sei schon immer so gebucht worden.
 */
class PostingRuleController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly PostingRuleResolver $rules) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting.rules', [
            'rules' => $this->rules->all($organization),
            'kinds' => PostingSourceKind::cases(),
            'roles' => PostingAccountRole::cases(),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
        ]);
    }

    public function form(?AccountingPostingRule $rule = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._rule_dialog', [
            'rule' => $rule,
            'kinds' => PostingSourceKind::cases(),
            'roles' => PostingAccountRole::cases(),
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->active()
                ->orderBy('number')
                ->get(),
            'taxCodes' => AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $data = $this->validated($request, $organization);

        AccountingPostingRule::query()->create($data + ['organization_id' => $organization->id, 'version' => 1]);

        return back()->with('status', __('accounting.rules.flash.saved'));
    }

    /** Neue Fassung: alte Regel endet am Vortag, die neue beginnt am Stichtag. */
    public function update(Request $request, AccountingPostingRule $rule): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $rule->organization_id === (int) $organization->id, 404);

        $data = $this->validated($request, $organization);
        $validFrom = CarbonImmutable::parse((string) $data['valid_from'])->startOfDay();

        // Wurde nur innerhalb derselben Gültigkeit korrigiert, bleibt es eine
        // Korrektur; ein neuer Stichtag erzeugt eine echte Folgefassung.
        if ($validFrom->toDateString() === $rule->valid_from->toDateString()) {
            $rule->update($data);

            return back()->with('status', __('accounting.rules.flash.saved'));
        }

        $rule->update(['valid_to' => $validFrom->subDay()->toDateString()]);
        AccountingPostingRule::query()->create($data + [
            'organization_id' => $organization->id,
            'version' => $rule->version + 1,
        ]);

        return back()->with('status', __('accounting.rules.flash.versioned'));
    }

    public function destroy(AccountingPostingRule $rule): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $rule->organization_id === (int) $organization->id, 404);

        // Stilllegen statt löschen: Buchungen verweisen über die Regelversion
        // auf diese Zeile.
        $rule->update(['is_active' => false]);

        return back()->with('status', __('accounting.rules.flash.deactivated'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, \App\Models\Organization $organization): array {
        $data = $request->validate([
            'source_kind' => ['required', 'string', 'in:' . implode(',', array_column(PostingSourceKind::cases(), 'value'))],
            'role' => ['required', 'string', 'in:' . implode(',', array_column(PostingAccountRole::cases(), 'value'))],
            'account' => ['required', 'string'],
            'tax_code' => ['nullable', 'string'],
            'match_key' => ['nullable', 'string', 'max:64'],
            'match_value' => ['nullable', 'string', 'max:64'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $accountId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['account']);
        $ownAccount = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereKey($accountId)
            ->exists();
        abort_unless($ownAccount, 422);

        $taxCodeId = null;
        if (! empty($data['tax_code'])) {
            $taxCodeId = (int) Sqid::decodeOrNumeric(AccountingTaxCode::class, (string) $data['tax_code']);
            $ownTaxCode = AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->whereKey($taxCodeId)
                ->exists();
            abort_unless($ownTaxCode, 422);
        }

        $match = [];
        if (! empty($data['match_key']) && isset($data['match_value']) && $data['match_value'] !== '') {
            $match[(string) $data['match_key']] = (string) $data['match_value'];
        }

        return [
            'source_kind' => PostingSourceKind::from((string) $data['source_kind']),
            'role' => PostingAccountRole::from((string) $data['role']),
            'accounting_account_id' => $accountId,
            'accounting_tax_code_id' => $taxCodeId,
            'match_criteria' => $match === [] ? null : $match,
            'priority' => (int) $data['priority'],
            'valid_from' => (string) $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ];
    }
}
