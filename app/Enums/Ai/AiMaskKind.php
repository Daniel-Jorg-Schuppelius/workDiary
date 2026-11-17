<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMaskKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Werts, den die Maskierung vor dem KI-Anbieter verbirgt
 * (Feature 025, MVP-1671): erkennbar ohne Stammdaten, ersetzt durch einen
 * Platzhalter `[<Kürzel>-<n>]` ({@see \App\Services\Ai\Support\SensitiveDataMask}).
 */
enum AiMaskKind: string implements HasLabel {
    use HasOptions;

    case Iban = 'iban';
    case VatId = 'vat_id';
    case TaxNumber = 'tax_number';
    case TaxId = 'tax_id';
    case Email = 'email';
    case Phone = 'phone';

    public function label(): string {
        return (string) __('enums.ai.mask_kind.' . $this->value);
    }

    /**
     * Kürzel im Platzhalter. Bewusst sprachneutral in Großbuchstaben: Ein
     * deutsches „E-Mail“ übersetzte DeepL mit, und die Rückersetzung fände
     * den Platzhalter nicht wieder.
     */
    public function token(): string {
        return match ($this) {
            self::Iban => 'IBAN',
            self::VatId => 'VATID',
            self::TaxNumber => 'TAXNO',
            self::TaxId => 'TAXID',
            self::Email => 'EMAIL',
            self::Phone => 'PHONE',
        };
    }
}
