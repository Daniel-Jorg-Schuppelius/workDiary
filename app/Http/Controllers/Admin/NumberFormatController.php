<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Numbering\NumberScope;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Numbering\SaveNumberFormatRequest;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\Organization;
use App\Services\Numbering\{NumberAuthority, NumberFormatOverview, NumberSequenceService};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NumberFormatController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly NumberSequenceService $numberSequence,
        private readonly NumberAuthority $authority,
        private readonly NumberFormatOverview $overview,
    ) {}

    public function index(): View|RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganizationOrUserOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->toList('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }

        $rows = $this->overview->rows($organization);
        $open = array_values(array_filter($rows, static fn (array $row): bool => $row['lockedBy'] === null));

        return view('admin.number-formats.index', [
            'organization' => $organization,
            'rows' => $rows,
            'separators' => [
                'prefix' => $this->mostCommon(array_map(static fn (array $row): string => (string) $row['format']->prefix_separator, $open)),
                'year' => $this->mostCommon(array_map(static fn (array $row): string => (string) $row['format']->year_separator, $open)),
            ],
        ]);
    }

    /**
     * Speichert alle Kreise eines Absendens (MVP-1147); geschrieben wird nur,
     * was sich ändert, damit Standardkreise „Default“ bleiben. „Für alle
     * Kreise übernehmen“ setzt beide Trenner in jedem offenen Kreis (MVP-1148).
     */
    public function update(SaveNumberFormatRequest $request): RedirectResponse {
        Gate::authorize('manage', NumberFormat::class);

        $organization = $this->currentOrganizationOrUserOrganization();
        if (! $organization instanceof Organization) {
            return redirect()->toList('admin.organizations.index')
                ->with('warning', __('Bitte zuerst eine Organisation anlegen bzw. dem aktuellen Benutzer zuweisen.'));
        }

        $locked = $this->overview->lockedScopes($organization);
        $separators = $request->boolean('apply_separators') ? [
            'prefix_separator' => (string) $request->input('separators.prefix'),
            'year_separator' => (string) $request->input('separators.year'),
        ] : [];

        $saved = 0;
        foreach ((array) $request->validated('formats') as $value => $row) {
            $scope = NumberScope::from((string) $value);
            $row = (array) $row;
            $attributes = filled($row['source'] ?? null) ? ['source' => (string) $row['source']] : [];
            // Gesperrte Kreise: das Format legt das Programm fest; mitgesendete Werte einer älteren Seite zählen nicht (E6).
            if (! isset($locked[$scope->value])) {
                $attributes = [...$attributes, ...$this->formatAttributes($row), ...$separators];
            }

            if ($this->changes($organization, $scope, $attributes)) {
                $this->numberSequence->setFormat($organization, $scope, $attributes);
                $saved++;
            }
        }

        return redirect()->route('admin.number-formats.index')
            ->with('success', $saved > 0 ? trans_choice('numbering.flash.saved', $saved, ['count' => $saved]) : __('numbering.flash.unchanged'));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, bool|int|string>
     */
    private function formatAttributes(array $row): array {
        $attributes = [];
        foreach (['prefix', 'prefix_separator', 'year_separator'] as $key) {
            if (array_key_exists($key, $row)) {
                $attributes[$key] = (string) $row[$key];
            }
        }
        foreach (['include_year', 'reset_per_year'] as $key) {
            if (array_key_exists($key, $row)) {
                $attributes[$key] = StringHelper::parseBool($row[$key]) ?? false;
            }
        }
        foreach (['padding', 'starts_at'] as $key) {
            if (array_key_exists($key, $row)) {
                $attributes[$key] = (int) $row[$key];
            }
        }

        return $attributes;
    }

    /** @param array<string, bool|int|string> $attributes */
    private function changes(Organization $organization, NumberScope $scope, array $attributes): bool {
        $format = $this->numberSequence->resolveFormat((int) $organization->id, $scope);
        foreach ($attributes as $key => $value) {
            $current = $key === 'source' ? $this->authority->configuredSource($organization, $scope) : $format->getAttribute($key);
            $same = match (true) {
                is_bool($value) => (bool) $current === $value,
                is_int($value) => (int) $current === $value,
                default => (string) $current === $value,
            };
            if (! $same) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $values */
    private function mostCommon(array $values): string {
        $counts = array_count_values($values);
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
