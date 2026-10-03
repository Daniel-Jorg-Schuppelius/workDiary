<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataProduct.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata;

/**
 * Antwort des Großhändlers zu einem Artikel. `status` 950/951 heißt: der
 * gefragte Artikel ist ersetzt, geliefert wurde der Alternativ- bzw.
 * Nachfolgeartikel.
 *
 * @phpstan-type Raw array<string, mixed>
 */
final readonly class OpenMasterdataProduct {
    /** @param Raw $data */
    public function __construct(public array $data, public int $status = 200) {}

    public function supplierPid(): string {
        return trim((string) ($this->data['supplierPid'] ?? ''));
    }

    public function isAlternative(): bool {
        return $this->status === OpenMasterdataClient::STATUS_ALTERNATIVE;
    }

    public function isFollowup(): bool {
        return $this->status === OpenMasterdataClient::STATUS_FOLLOWUP;
    }

    /** Pfad wie `prices.netPrice.value`; fehlende Teile ergeben null. */
    public function get(string $path): mixed {
        return data_get($this->data, $path);
    }

    public function string(string $path): ?string {
        $value = $this->get($path);
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null || is_array($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
