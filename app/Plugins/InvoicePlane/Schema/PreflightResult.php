<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PreflightResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

/**
 * Ergebnis des Schema-Preflights (Feature 086, MVP-419). `ok=false` bedeutet
 * **Blocked-State**: der Adapter versucht keine „Best-Effort"-Schreiboperation,
 * sondern zeigt die Gründe sichtbar an.
 */
final readonly class PreflightResult {
    /**
     * @param  list<string>  $reasons  Blockier-Gründe (leer, wenn ok)
     */
    public function __construct(
        public bool $ok,
        public ?string $versionKey,
        public array $reasons,
        public string $fingerprint,
    ) {}

    public function isBlocked(): bool {
        return ! $this->ok;
    }
}
