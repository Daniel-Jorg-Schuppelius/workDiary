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

namespace App\Models\Platform;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * DB-Override eines Registry-Jobs (Feature 067, MVP-176):
 * aktiv/inaktiv, abweichende Kadenz, Zeitzone, Ruhezeit und Wiederholung
 * nach Fehlschlag (MVP-1619). Jede Änderung ist auditiert;
 * der Registrar liest die Overrides gecacht und DB-ausfallsicher.
 *
 * @property int $id
 * @property string $job_key
 * @property int|null $organization_id NULL = System (installationsweit)
 * @property bool $enabled
 * @property array<string, mixed>|null $cadence
 * @property string|null $timezone
 * @property string|null $quiet_from HH:MM, ab der der Job ruht
 * @property string|null $quiet_until HH:MM, ab der er wieder läuft
 * @property int $retry_attempts
 * @property int|null $retry_delay_minutes
 * @property int|null $updated_by_user_id
 */
class ScheduledJobOverride extends Model {
    use Auditable;

    public const string CACHE_KEY = 'scheduler.overrides.system:2';

    protected $table = 'scheduled_job_overrides';

    protected $fillable = ['job_key', 'organization_id', 'enabled', 'cadence', 'timezone', 'quiet_from', 'quiet_until', 'retry_attempts', 'retry_delay_minutes', 'updated_by_user_id'];

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
        'cadence' => 'array',
        'retry_attempts' => 'integer',
        'retry_delay_minutes' => 'integer',
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
     * @return array<string, array{enabled: bool, cadence: array<string, mixed>|null, timezone: string|null, quiet_from: string|null, quiet_until: string|null, retry_attempts: int, retry_delay_minutes: int|null}>
     */
    public static function systemMap(): array {
        try {
            /** @var array<string, array{enabled: bool, cadence: array<string, mixed>|null, timezone: string|null, quiet_from: string|null, quiet_until: string|null, retry_attempts: int, retry_delay_minutes: int|null}> $map */
            $map = Cache::rememberForever(self::CACHE_KEY, static function (): array {
                return self::query()
                    ->whereNull('organization_id')
                    ->get()
                    ->mapWithKeys(fn(self $row): array => [$row->job_key => [
                        'enabled' => $row->enabled,
                        'cadence' => $row->cadence,
                        'timezone' => $row->timezone,
                        'quiet_from' => $row->quiet_from,
                        'quiet_until' => $row->quiet_until,
                        'retry_attempts' => (int) $row->retry_attempts,
                        'retry_delay_minutes' => $row->retry_delay_minutes,
                    ]])
                    ->all();
            });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Trägt der Override noch etwas außer dem Aktiv-Schalter? */
    public function hasSettings(): bool {
        return $this->cadence !== null || $this->timezone !== null || $this->quiet_from !== null || $this->retry_attempts > 0;
    }
}
