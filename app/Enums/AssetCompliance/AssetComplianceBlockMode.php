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

/**
 * Sperrwirkung einer Prüfpflicht (MVP-284): von reiner Warnung bis zur
 * sofortigen Einsatzsperre bei Fälligkeit — Sperren entstehen im
 * gemeinsamen Sperrmodell (D12).
 */
enum AssetComplianceBlockMode: string {
    case None = 'none';
    case Warn = 'warn';
    case BlockAfterGrace = 'block_after_grace';
    case BlockImmediately = 'block_immediately';

    public function label(): string {
        return match ($this) {
            self::None => (string) __('Keine Wirkung'),
            self::Warn => (string) __('Warnung'),
            self::BlockAfterGrace => (string) __('Sperre nach Nachfrist'),
            self::BlockImmediately => (string) __('Sofortige Sperre'),
        };
    }

    public function blocks(): bool {
        return in_array($this, [self::BlockAfterGrace, self::BlockImmediately], true);
    }
}
