<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseEncrypted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing\Casts;

use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use App\Services\Whistleblowing\WhistleblowingCryptoService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-Cast fuer fall-bezogen verschluesselte Felder. Ver- und entschluesselt
 * ueber den DEK des Falls, den das Modell via {@see ProvidesCaseDek::caseDek()}
 * liefert. Ist der DEK nicht mehr verfuegbar (Crypto-Shredding), liefert get()
 * null statt zu werfen – der Inhalt ist dann bewusst unwiederbringlich.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class CaseEncrypted implements CastsAttributes {
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $dek = $this->dek($model);
        if ($dek === null) {
            return null; // geschreddert: kein Schluessel mehr
        }

        return app(WhistleblowingCryptoService::class)->decryptWithDek((string) $value, $dek);
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null) {
            return [$key => null];
        }

        $dek = $this->dek($model);
        if ($dek === null) {
            throw new \RuntimeException('Kein DEK verfuegbar – verschluesseltes Feld kann nicht gesetzt werden.');
        }

        return [$key => app(WhistleblowingCryptoService::class)->encryptWithDek((string) $value, $dek)];
    }

    private function dek(Model $model): ?string {
        if (! $model instanceof ProvidesCaseDek) {
            throw new \LogicException('CaseEncrypted-Cast erfordert ein ProvidesCaseDek-Modell.');
        }

        return $model->caseDek();
    }
}
