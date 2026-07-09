<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemSetting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Cache, Crypt};

/**
 * Systemweiter Betreiber-Override einer Registry-Einstellung
 * (Feature 067, MVP-173). Werte werden JSON-kodiert abgelegt; bei
 * is_sensitive zusätzlich verschlüsselt (APP_KEY). Der Klartext
 * sensibler Werte erscheint weder in DB-Dumps noch im Audit.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property bool $is_sensitive
 * @property int|null $updated_by_user_id
 */
class SystemSetting extends Model {
    use Auditable {
        getAuditAttributes as private auditableGetAuditAttributes;
    }

    public const CACHE_KEY = 'system_settings.values';

    protected $table = 'system_settings';

    protected $fillable = ['key', 'value', 'is_sensitive', 'updated_by_user_id'];

    /** @var array<string, string> */
    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    protected static function booted(): void {
        $flush = static fn() => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function setResolvedValue(mixed $value, bool $sensitive): void {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $this->is_sensitive = $sensitive;
        $this->value = $sensitive ? Crypt::encryptString($encoded) : $encoded;
    }

    public function resolvedValue(): mixed {
        if ($this->value === null) {
            return null;
        }
        $encoded = $this->is_sensitive ? Crypt::decryptString($this->value) : $this->value;

        return json_decode($encoded, true);
    }

    /**
     * Alle System-Overrides als key=>Klartextwert-Map, request- und
     * store-gecacht. DB-ausfallsicher: vor Migration/bei DB-Fehlern
     * liefert die Map leer, damit Setting::get() auf config() zurückfällt.
     *
     * @return array<string, mixed>
     */
    public static function valueMap(): array {
        try {
            /** @var array<string, mixed> $map */
            $map = Cache::rememberForever(self::CACHE_KEY, static function (): array {
                return self::query()->get()
                    ->mapWithKeys(fn(self $row): array => [$row->key => $row->resolvedValue()])
                    ->all();
            });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function getAuditAttributes(array $attributes): array {
        $attributes = $this->auditableGetAuditAttributes($attributes);
        if ($this->is_sensitive && array_key_exists('value', $attributes)) {
            $attributes['value'] = '<redacted>';
        }

        return $attributes;
    }
}
