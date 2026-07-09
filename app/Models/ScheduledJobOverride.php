<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledJobOverride.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * DB-Override eines Registry-Jobs (Feature 067, MVP-176):
 * aktiv/inaktiv + abweichende Kadenz. Jede Änderung ist auditiert;
 * der Registrar liest die Overrides gecacht und DB-ausfallsicher.
 *
 * @property int $id
 * @property string $job_key
 * @property int|null $organization_id NULL = System (installationsweit)
 * @property bool $enabled
 * @property array<string, mixed>|null $cadence
 * @property int|null $updated_by_user_id
 */
class ScheduledJobOverride extends Model {
    use Auditable;

    public const CACHE_KEY = 'scheduler.overrides.system';

    protected $table = 'scheduled_job_overrides';

    protected $fillable = ['job_key', 'organization_id', 'enabled', 'cadence', 'updated_by_user_id'];

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
        'cadence' => 'array',
    ];

    protected static function booted(): void {
        $flush = static fn() => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * System-Overrides (organization_id NULL) als job_key-Map, gecacht
     * und DB-ausfallsicher (vor Migration/bei DB-Fehlern leer → der
     * Registrar fällt auf die config-Defaults zurück).
     *
     * @return array<string, array{enabled: bool, cadence: array<string, mixed>|null}>
     */
    public static function systemMap(): array {
        try {
            /** @var array<string, array{enabled: bool, cadence: array<string, mixed>|null}> $map */
            $map = Cache::rememberForever(self::CACHE_KEY, static function (): array {
                return self::query()
                    ->whereNull('organization_id')
                    ->get()
                    ->mapWithKeys(fn(self $row): array => [$row->job_key => [
                        'enabled' => $row->enabled,
                        'cadence' => $row->cadence,
                    ]])
                    ->all();
            });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }
}
