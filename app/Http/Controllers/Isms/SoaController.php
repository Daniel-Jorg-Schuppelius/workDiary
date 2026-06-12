<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use App\Services\SqidEncoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Statement of Applicability (Feature 044/046): Read-Only-Tabelle der
 * SoA-Aussagen eines Geltungsbereichs (Query-Param scope={sqid}, Default =
 * Default-Scope; optionaler Norm-Filter norm="norm|edition") — je
 * Anforderung Anwendbarkeit, Begründung, Umsetzungsstatus sowie gemappte
 * Maßnahmen und (über die Maßnahmen) verknüpfte Risiken. Standard ist der
 * Dialog (entry-modal, Hausstandard); mit `?print=1` bleibt die druckbare
 * Standalone-Ansicht erreichbar (Muster: Fallakte).
 */
class SoaController extends Controller {
    public function __construct(
        private readonly SqidEncoder $sqids,
    ) {}

    public function __invoke(Request $request): View {
        Gate::authorize('viewAny', IsmsRequirement::class);

        $scope = $this->resolveScope($request->query('scope'));
        $normFilter = $this->parseNormFilter((string) $request->query('norm', 'all'));
        [$norm, $edition] = $normFilter ?? [null, null];

        $statements = $scope === null
            ? collect()
            : IsmsApplicabilityStatement::query()
                ->where('isms_scope_id', $scope->id)
                ->when($norm !== null, fn($query) => $query->whereHas(
                    'requirement',
                    fn($q) => $q->where('norm', $norm)->where('edition', $edition),
                ))
                ->with([
                    'requirement.controls' => fn($q) => $q->orderBy('title'),
                    'requirement.controls.risks' => fn($q) => $q->orderBy('risk_no'),
                ])
                ->get()
                ->sort(fn(IsmsApplicabilityStatement $a, IsmsApplicabilityStatement $b): int => strcmp((string) $a->requirement?->norm, (string) $b->requirement?->norm)
                    ?: strnatcmp((string) $a->requirement?->ref_no, (string) $b->requirement?->ref_no))
                ->values();

        $data = [
            'scope' => $scope,
            'statements' => $statements,
            'normLabel' => $normFilter === null
                ? null
                : ($normFilter[1] === '-' ? $normFilter[0] : $normFilter[0] . ':' . $normFilter[1]),
            'normFilter' => $normFilter === null ? 'all' : implode('|', $normFilter),
            'generatedAt' => now(),
            'organizationName' => (string) (app()->bound('currentOrganization')
                ? app('currentOrganization')->name
                : ''),
        ];

        return $request->boolean('print')
            ? view('isms.soa', $data)
            : view('isms._soa_dialog', $data);
    }

    /**
     * Scope-Query-Param (Sqid) auflösen — ungültige oder fremde Werte
     * (Org-Scope!) fallen auf den Default-Scope zurück.
     */
    private function resolveScope(mixed $sqid): ?IsmsScope {
        if (is_string($sqid) && $sqid !== '') {
            $id = $this->sqids->decode(IsmsScope::class, $sqid);
            $scope = $id === null ? null : IsmsScope::query()->whereKey($id)->first();

            if ($scope !== null) {
                return $scope;
            }
        }

        return IsmsScope::query()->where('is_default', true)->first();
    }

    /**
     * Kombinierten Norm-Filterwert "norm|edition" zerlegen; 'all' oder
     * Werte ohne Treffer in den Requirements der Org ⇒ kein Filter (null).
     *
     * @return array{0: string, 1: string}|null
     */
    private function parseNormFilter(string $value): ?array {
        if ($value === 'all' || $value === '') {
            return null;
        }

        $pos = strrpos($value, '|');
        [$norm, $edition] = $pos === false
            ? [$value, '-']
            : [substr($value, 0, $pos), substr($value, $pos + 1)];

        $exists = IsmsRequirement::query()
            ->where('norm', $norm)
            ->where('edition', $edition)
            ->exists();

        return $exists ? [$norm, $edition] : null;
    }
}
