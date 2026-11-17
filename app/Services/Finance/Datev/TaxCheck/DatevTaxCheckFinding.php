<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTaxCheckFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev\TaxCheck;

use App\Enums\Finance\DatevTaxCheckKind;

/**
 * Ein Befund der Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661).
 *
 * Der Schlüssel ist stabil je Buchungszeile bzw. Beleg und Befundart — eine
 * begründete Freigabe gilt genau für ihn, auch in einem späteren Stapel. Ein
 * neuer Fehler hat einen neuen Schlüssel und sperrt wieder.
 */
final readonly class DatevTaxCheckFinding {
    public function __construct(
        public string $key,
        public DatevTaxCheckKind $kind,
        public bool $blocking,
        public string $message,
        public string $reference,
        public ?string $url = null,
    ) {}
}
