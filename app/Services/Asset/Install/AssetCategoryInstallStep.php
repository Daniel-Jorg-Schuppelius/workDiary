<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCategoryInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Install;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification\Classification;
use App\Models\Platform\{Organization, User};
use App\Services\Classification\ClassificationResolver;
use App\Services\Classification\Contracts\{ProfileInstallStep, ProfileUninstallStep};
use App\Support\BranchProfileFiles;
use Illuminate\Support\Facades\DB;

/**
 * Branchenprofil-Abschnitt `asset_categories` (MVP-1508): Codes aus dem Katalog
 * `database/data/asset_categories.php` werden Klassifikationen der Domäne
 * `asset_category`. Ergänzt nur fehlende Codes und überschreibt nie eigene;
 * Codes des globalen Pools `config('asset_categories')` gibt es schon.
 * Deinstallieren entfernt nur unbenutzte, unveränderte Kategorien, die kein
 * anderes installiertes Profil mehr mitbringt.
 */
final class AssetCategoryInstallStep implements ProfileInstallStep, ProfileUninstallStep {
    public function __construct(private readonly ClassificationResolver $resolver) {}

    public function key(): string {
        return 'asset_categories';
    }

    /**
     * @param  array<int|string, mixed>  $rows  Codes oder Zeilen `{code, label?, label_i18n?}`
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $catalog = $this->catalog();
        $pool = (array) config('asset_categories', []);
        $created = 0;
        $skipped = 0;
        $sort = (int) Classification::query()
            ->where('organization_id', $organization->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)
            ->max('sort_order');

        foreach ($rows as $row) {
            $code = $this->codeOf($row);
            if ($code === '' || array_key_exists($code, $pool)) {
                $skipped++;

                continue;
            }
            $entry = (is_array($row) ? $row : []) + ($catalog[$code] ?? []);

            $existing = $this->find($organization, $code);
            if ($existing instanceof Classification) {
                if (! is_array($existing->label_i18n) && is_array($entry['label_i18n'] ?? null)) {
                    $existing->update(['label_i18n' => $entry['label_i18n']]);
                }
                $skipped++;

                continue;
            }

            $sort += 10;
            Classification::query()->create([
                'organization_id' => $organization->id,
                'domain' => ClassificationDomain::AssetCategory->value,
                'code' => $code,
                'label' => (string) ($entry['label'] ?? $code),
                'label_i18n' => is_array($entry['label_i18n'] ?? null) ? $entry['label_i18n'] : null,
                'sort_order' => $sort,
                'active' => true,
            ]);
            $created++;
        }

        $this->resolver->forget($organization->id, ClassificationDomain::AssetCategory);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  list<string>  $remainingProfileCodes
     * @return array{removed: int, kept: int}
     */
    public function uninstall(Organization $organization, array $rows, array $remainingProfileCodes): array {
        $catalog = $this->catalog();
        $stillDeclared = [];
        foreach ($remainingProfileCodes as $profileCode) {
            foreach ((array) (BranchProfileFiles::profile($profileCode)['asset_categories'] ?? []) as $row) {
                $stillDeclared[$this->codeOf($row)] = true;
            }
        }

        $removed = 0;
        $kept = 0;
        foreach ($rows as $row) {
            $code = $this->codeOf($row);
            $category = $code === '' || isset($stillDeclared[$code]) ? null : $this->find($organization, $code);
            if (! $category instanceof Classification) {
                continue;
            }

            $profileLabel = (string) ((is_array($row) ? $row['label'] ?? null : null) ?? $catalog[$code]['label'] ?? $code);
            $inUse = DB::table('assets')->where('organization_id', $organization->id)->where('category_code', $code)->exists();
            if ($inUse || $category->label !== $profileLabel) {
                $kept++;

                continue;
            }

            $category->delete();
            $removed++;
        }

        $this->resolver->forget($organization->id, ClassificationDomain::AssetCategory);

        return ['removed' => $removed, 'kept' => $kept];
    }

    private function codeOf(mixed $row): string {
        return trim((string) (is_array($row) ? ($row['code'] ?? '') : (is_string($row) ? $row : '')));
    }

    private function find(Organization $organization, string $code): ?Classification {
        return Classification::query()
            ->where('organization_id', $organization->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)
            ->where('code', $code)
            ->first();
    }

    /** @return array<string, array{label: string, label_i18n: array<string, string>}> */
    private function catalog(): array {
        return require database_path('data/asset_categories.php');
    }
}
