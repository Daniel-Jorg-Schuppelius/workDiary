<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceBlockMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Sperrwirkung einer Prüfpflicht (MVP-284): von reiner Warnung bis zur
 * sofortigen Einsatzsperre bei Fälligkeit — Sperren entstehen im
 * gemeinsamen Sperrmodell (D12).
 */
enum AssetComplianceBlockMode: string implements HasLabel {
    use HasOptions;

    case None = 'none';
    case Warn = 'warn';
    case BlockAfterGrace = 'block_after_grace';
    case BlockImmediately = 'block_immediately';

    public function label(): string {
        return match ($this) {
            self::None => (string) __('enums.asset_compliance.asset_compliance_block_mode.none'),
            self::Warn => (string) __('enums.asset_compliance.asset_compliance_block_mode.warn'),
            self::BlockAfterGrace => (string) __('enums.asset_compliance.asset_compliance_block_mode.block_after_grace'),
            self::BlockImmediately => (string) __('enums.asset_compliance.asset_compliance_block_mode.block_immediately'),
        };
    }

    public function blocks(): bool {
        return in_array($this, [self::BlockAfterGrace, self::BlockImmediately], true);
    }
}
