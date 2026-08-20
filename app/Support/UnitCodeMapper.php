<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnitCodeMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use ERechnungToolkit\Enums\UnitCode;

/**
 * Freitext-Mengeneinheit (`Article::base_unit`) → UN/ECE-Rec-20-Code
 * (Feature 107, W7-Konsolidierung).
 *
 * Die Auflösung selbst liegt seit dem Toolkit-Paket der Audit-Welle 5 im
 * erechnung-toolkit ({@see UnitCode::fromText()}) — sie ist Formatwissen und
 * gehört nicht in die App. Hier bleibt, was workDiary-Geschäftsregel ist:
 * die DATANORM-5-Eigenheiten aus {@see toDatanorm()} (`PCE` für Stück statt
 * `C62`, dazu `STG` für Stangen, das UN/ECE gar nicht kennt).
 */
final class UnitCodeMapper {
    /** DATANORM-Sonderfälle außerhalb von UN/ECE Rec 20. */
    private const DATANORM_SPECIALS = ['stange' => 'STG', 'stg' => 'STG'];

    private function __construct() {
        // Statischer Helfer.
    }

    /** ISO-Einheit für DATANORM 5; unbekannte Einheiten fallen auf `PCE` zurück. */
    public static function toDatanorm(?string $unit): string {
        $normalized = mb_strtolower(rtrim(trim((string) $unit), '.'));
        if (isset(self::DATANORM_SPECIALS[$normalized])) {
            return self::DATANORM_SPECIALS[$normalized];
        }

        $code = UnitCode::fromText($unit);
        if ($code === null) {
            return 'PCE';
        }

        // DATANORM verwendet PCE für Stück, nicht die UN/ECE-Stückcodes.
        return match ($code) {
            UnitCode::PIECE, UnitCode::UNIT, UnitCode::UNIT_H87 => 'PCE',
            default => $code->value,
        };
    }
}
