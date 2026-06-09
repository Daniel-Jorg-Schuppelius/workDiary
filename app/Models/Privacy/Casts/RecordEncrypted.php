<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordEncrypted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy\Casts;

use App\Models\Privacy\Concerns\ProvidesRecordDek;
use App\Services\Privacy\DataProtectionCryptoService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-Cast fuer datensatz-bezogen verschluesselte Felder (per-Fall-DEK).
 * Ist der DEK geschreddert, liefert get() null statt zu werfen.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class RecordEncrypted implements CastsAttributes {
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        $dek = $this->dek($model);
        if ($dek === null) {
            return null; // geschreddert
        }

        return app(DataProtectionCryptoService::class)->decryptWithDek((string) $value, $dek);
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null) {
            return [$key => null];
        }
        $dek = $this->dek($model);
        if ($dek === null) {
            throw new \RuntimeException('Kein DEK verfuegbar – verschluesseltes Feld kann nicht gesetzt werden.');
        }

        return [$key => app(DataProtectionCryptoService::class)->encryptWithDek((string) $value, $dek)];
    }

    private function dek(Model $model): ?string {
        if (! $model instanceof ProvidesRecordDek) {
            throw new \LogicException('RecordEncrypted-Cast erfordert ein ProvidesRecordDek-Modell.');
        }

        return $model->recordDek();
    }
}
