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

    // v2: Die Karte enthaelt seit dem Sicherheitsaudit 2026-09-13 KEINE
    // Klartextwerte sensibler Einstellungen mehr; der neue Schluessel laesst
    // Altbestaende im Cache verfallen, statt sie falsch zu lesen.
    public const CACHE_KEY = 'system_settings.values.v2';

    /** @var array<string, mixed>|null Anfrage-Memo fuer sensible Werte (nie im Cache). */
    private static ?array $sensitiveMemo = null;

    protected $table = 'system_settings';

    protected $fillable = ['key', 'value', 'is_sensitive', 'updated_by_user_id'];

    /** @var array<string, string> */
    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    protected static function booted(): void {
        $flush = static function (): void {
            Cache::forget(self::CACHE_KEY);
            self::$sensitiveMemo = null;
        };
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
            /** @var array{values: array<string, mixed>, sensitive: list<string>} $cached */
            $cached = Cache::rememberForever(self::CACHE_KEY, static function (): array {
                $values = [];
                $sensitive = [];

                foreach (self::query()->get() as $row) {
                    // Sensible Werte bleiben aus dem Cache: er liegt dauerhaft
                    // in der Datenbank (und je nach Treiber auch anderswo),
                    // waehrend die Spalte selbst verschluesselt ist — der Cache
                    // haette den Schutz ausgehebelt (Sicherheitsaudit 2026-09-13).
                    if ($row->is_sensitive) {
                        $sensitive[] = (string) $row->key;

                        continue;
                    }
                    $values[$row->key] = $row->resolvedValue();
                }

                return ['values' => $values, 'sensitive' => $sensitive];
            });

            $map = $cached['values'];
            $sensitive = $cached['sensitive'];

            return $sensitive === [] ? $map : $map + self::sensitiveValues($sensitive);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Sensible Werte frisch aus den Zeilen — einmal je Anfrage, nicht gecacht.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function sensitiveValues(array $keys): array {
        if (is_array(self::$sensitiveMemo)) {
            return self::$sensitiveMemo;
        }

        return self::$sensitiveMemo = self::query()->whereIn('key', $keys)->get()
            ->mapWithKeys(fn (self $row): array => [$row->key => $row->resolvedValue()])
            ->all();
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
