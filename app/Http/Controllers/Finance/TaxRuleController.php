<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\{TaxRule, User};
use App\Services\Invoicing\TaxResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Steuerregelmatrix (Phase 23, MVP-242): Admin-Pflege der org-eigenen
 * Regeln (Override des ausgelieferten Katalogs) mit Überschneidungs-
 * prüfung, Lückenwarnung, CSV-Import und Rollback (retire, nie löschen).
 * Recht: finance.config — kritische Änderungen sind auditiert.
 */
class TaxRuleController extends Controller {
    public function __construct(private readonly TaxResolver $resolver) {}

    public function index(): View {
        $this->authorizeConfig();
        $orgId = (int) Auth::user()?->organization_id;

        $rules = TaxRule::query()
            ->where(fn($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId))
            ->orderBy('country')->orderBy('category')->orderBy('rate_type')->orderByDesc('valid_from')
            ->get();

        // Lückenwarnung (MVP-242): aktive Regelketten mit zeitlichem Loch.
        $gaps = [];
        foreach ($rules->where('status', 'active')->groupBy(fn(TaxRule $r): string => $r->country . '|' . $r->category . '|' . $r->rate_type . '|' . ($r->organization_id ?? 'global')) as $key => $chain) {
            $sorted = $chain->sortBy(fn(TaxRule $r): string => $r->valid_from->toDateString())->values()->all();
            for ($i = 0; $i < count($sorted) - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];
                if ($current->valid_to === null) {
                    continue;
                }
                if ($current->valid_to->copy()->addDay()->lt($next->valid_from)) {
                    $gaps[] = (string) __(':key: Lücke zwischen :from und :to', [
                        'key' => str_replace('|', ' / ', $key),
                        'from' => $current->valid_to->toDateString(),
                        'to' => $next->valid_from->toDateString(),
                    ]);
                }
            }
        }

        return view('finance.tax-rules', [
            'rules' => $rules,
            'gaps' => $gaps,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeConfig();
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:10'],
            'category' => ['required', 'in:' . implode(',', TaxRule::CATEGORIES)],
            'rate_type' => ['required', 'in:' . implode(',', TaxRule::RATE_TYPES)],
            'rate' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'source' => ['nullable', 'string', 'max:300'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $rule = new TaxRule([
            ...$data,
            'country' => strtoupper($data['country']),
            'organization_id' => (int) Auth::user()?->organization_id,
            'status' => 'active',
            'created_by' => (int) Auth::id(),
        ]);

        try {
            $this->resolver->assertNoOverlap($rule);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $rule->save();
        $rule->audit('tax_rule.created', ['country' => $rule->country, 'category' => $rule->category, 'rate' => (string) $rule->rate]);

        return back()->with('status', __('Steuerregel angelegt (Org-Override).'));
    }

    /** Rollback (MVP-242): Regeln werden stillgelegt, nie gelöscht (Audit). */
    public function retire(TaxRule $rule): RedirectResponse {
        $this->authorizeConfig();
        abort_if($rule->organization_id === null, 403, (string) __('Der ausgelieferte Katalog wird nicht verändert — Org-Override anlegen.'));
        abort_unless((int) $rule->organization_id === (int) Auth::user()?->organization_id, 404);

        $rule->update(['status' => 'retired']);
        $rule->audit('tax_rule.retired', ['country' => $rule->country, 'category' => $rule->category]);

        return back()->with('status', __('Regel stillgelegt — der Katalog/ältere Regeln greifen wieder.'));
    }

    /** CSV-Import: country;category;rate_type;rate;valid_from;valid_to;source;note */
    public function import(Request $request): RedirectResponse {
        $this->authorizeConfig();
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt']]);

        $lines = preg_split('/\r\n|\r|\n/', trim((string) file_get_contents((string) $request->file('file')->getRealPath()))) ?: [];
        $imported = 0;
        $errors = [];
        foreach ($lines as $index => $line) {
            if ($index === 0 && str_contains(strtolower($line), 'country')) {
                continue; // Kopfzeile
            }
            $parts = array_map(static fn(?string $part): string => trim((string) $part), str_getcsv($line, ';'));
            if (count($parts) < 5) {
                continue;
            }
            $rule = new TaxRule([
                'organization_id' => (int) Auth::user()?->organization_id,
                'country' => strtoupper($parts[0]),
                'category' => $parts[1],
                'rate_type' => $parts[2],
                'rate' => $parts[3],
                'valid_from' => $parts[4],
                'valid_to' => ($parts[5] ?? '') !== '' ? $parts[5] : null,
                'source' => $parts[6] ?? null,
                'note' => $parts[7] ?? null,
                'status' => 'active',
                'created_by' => (int) Auth::id(),
            ]);
            if (! in_array($rule->category, TaxRule::CATEGORIES, true) || ! in_array($rule->rate_type, TaxRule::RATE_TYPES, true)) {
                $errors[] = (string) __('Zeile :line: unbekannte Kategorie/Satztyp.', ['line' => $index + 1]);

                continue;
            }
            try {
                $this->resolver->assertNoOverlap($rule);
                $rule->save();
                $imported++;
            } catch (\RuntimeException $e) {
                $errors[] = (string) __('Zeile :line: :error', ['line' => $index + 1, 'error' => $e->getMessage()]);
            }
        }

        $message = (string) __(':count Regeln importiert.', ['count' => $imported]);
        if ($errors !== []) {
            $message .= ' ' . implode(' ', array_slice($errors, 0, 5));
        }

        return back()->with($errors === [] ? 'status' : 'error', $message);
    }

    private function authorizeConfig(): void {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->isAdmin() || $user->can(P::FinanceConfig->value)), 403);
    }
}
