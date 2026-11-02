<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanModuleGrace.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag im Downgrade-/Karenz-Ledger (Tabelle plan_module_grace).
 * Bewusst OHNE Org-Global-Scope: wird in Observer-/Command-Kontexten
 * organisationsuebergreifend gelesen und immer explizit nach organization_id
 * gefiltert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $module
 * @property \Illuminate\Support\Carbon $lost_at
 * @property \Illuminate\Support\Carbon $grace_until
 * @property \Illuminate\Support\Carbon|null $purged_at
 */
class PlanModuleGrace extends Model {
    protected $table = 'plan_module_grace';

    protected $fillable = [
        'organization_id',
        'module',
        'lost_at',
        'grace_until',
        'purged_at',
    ];

    protected $casts = [
        'lost_at' => 'datetime',
        'grace_until' => 'datetime',
        'purged_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    protected static function booted(): void {
        $forget = static fn (self $grace): bool => \Illuminate\Support\Facades\Cache::forget(self::pendingCacheKey((int) $grace->organization_id));
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Löschvorwarnungen des App-Layouts (Leistungsanalyse 2026-11-02, perf-10):
     * eine Abfrage je Seite, obwohl fast nie ein Downgrade anliegt — jetzt fünf
     * Minuten je Organisation gecacht, Schreibwege entwerten den Schlüssel.
     *
     * @param  array<string, bool>  $purgeable  Module, deren Daten nach der Karenz gelöscht werden
     * @return list<array{module: string, until: string}>  `until` als ISO-8601
     */
    public static function pendingPurgeItems(int $organizationId, array $purgeable): array {
        $rows = \Illuminate\Support\Facades\Cache::remember(self::pendingCacheKey($organizationId), 300, static function () use ($organizationId): array {
            return self::query()
                ->where('organization_id', $organizationId)
                ->whereNull('purged_at')
                ->where('grace_until', '>', now())
                ->orderBy('grace_until')
                ->get(['module', 'grace_until'])
                ->map(static fn (self $g): array => ['module' => (string) $g->module, 'until' => $g->grace_until->toIso8601String()])
                ->all();
        });

        return array_values(array_filter($rows, static fn (array $row): bool => ($purgeable[$row['module']] ?? false) === true));
    }

    public static function forgetPending(int $organizationId): void {
        \Illuminate\Support\Facades\Cache::forget(self::pendingCacheKey($organizationId));
    }

    private static function pendingCacheKey(int $organizationId): string {
        return 'plan-grace:pending:' . $organizationId;
    }
}
