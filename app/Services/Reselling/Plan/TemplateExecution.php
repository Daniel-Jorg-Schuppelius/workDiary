<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateExecution.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;

/**
 * Was aus der letzten Ausführung einer Serienvorlage wurde (MVP-1441): das
 * Buchhaltungsprogramm meldet nur „fehlgeschlagen“ mit Text — ob die Rechnung
 * dabei entstand und bezahlt ist, sagt der Belegspiegel. Lexoffice erzeugt bei
 * „fertigstellen + versenden“ die Rechnung auch dann, wenn nur die E-Mail
 * scheitert (Prod 2026-10-31: 14 von 16 Fällen, Rechnungen teils bezahlt).
 */
final readonly class TemplateExecution {
    public const PAID = 'paid';

    public const OPEN = 'open';

    public const MISSING = 'missing';

    public function __construct(
        public string $state,
        public ?string $voucherNumber,
        public ?CarbonImmutable $voucherDate,
        public ?CarbonImmutable $expectedOn,
    ) {}

    public function label(): string {
        $key = $this->state === self::MISSING && $this->expectedOn !== null ? 'missing_expected' : $this->state;

        return (string) __('resale.plan.execution.' . $key, [
            'number' => (string) $this->voucherNumber,
            'date' => $this->voucherDate !== null ? CarbonFmt::fdate($this->voucherDate) : '',
            'expected' => $this->expectedOn !== null ? CarbonFmt::fdate($this->expectedOn) : '',
        ]);
    }
}
