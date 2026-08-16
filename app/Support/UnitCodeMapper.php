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
 * Zentrale Übersetzung der Freitext-Mengeneinheit (`Article::base_unit`) in
 * UN/ECE-Rec-20-Codes (Feature 107, W7-Konsolidierung). Auflösung: direkter
 * ISO-Code → Wortliste (die Stück-Familie mit wählbarem Zielcode, weil
 * Bestell-XML historisch `H87` und OCI `C62` nutzt) → Toolkit-Abkürzungen
 * ({@see UnitCode::abbreviation()}).
 *
 * Für DATANORM 5 liefert {@see toDatanorm()} die dortigen ISO-Einheiten
 * (`PCE` für Stück statt `C62`, plus `STG` für Stangen, das UN/ECE nicht
 * kennt).
 */
final class UnitCodeMapper {
    /** Freitext (lowercase, ohne Schlusspunkt) → Einheit; Stück-Familie separat. */
    private const PIECE_WORDS = ['stk', 'st', 'stück', 'stueck', 'pc', 'pcs', 'pce', 'piece'];

    private const WORDS = [
        'h' => UnitCode::HOUR,
        'std' => UnitCode::HOUR,
        'stunde' => UnitCode::HOUR,
        'stunden' => UnitCode::HOUR,
        'hour' => UnitCode::HOUR,
        'min' => UnitCode::MINUTE,
        'minute' => UnitCode::MINUTE,
        'minuten' => UnitCode::MINUTE,
        'lfm' => UnitCode::METRE,
        'meter' => UnitCode::METRE,
        'qm' => UnitCode::SQUARE_METRE,
        'm2' => UnitCode::SQUARE_METRE,
        'cbm' => UnitCode::CUBIC_METRE,
        'm3' => UnitCode::CUBIC_METRE,
        'liter' => UnitCode::LITRE,
        'paar' => UnitCode::PAIR,
        'satz' => UnitCode::SET,
        'set' => UnitCode::SET,
        'pkg' => UnitCode::PACKAGE,
        'paket' => UnitCode::PACKAGE,
        'dutzend' => UnitCode::DOZEN,
        'dtzd' => UnitCode::DOZEN,
    ];

    /** DATANORM-Sonderfälle außerhalb von UN/ECE Rec 20. */
    private const DATANORM_SPECIALS = ['stange' => 'STG', 'stg' => 'STG'];

    private function __construct() {
        // Statischer Helfer.
    }

    /**
     * Löst eine Freitext-Einheit zum UN/ECE-Code auf; null, wenn sie nicht
     * erkannt wird. `$pieceCode` bestimmt den Zielcode der Stück-Familie
     * (Bestell-XML: `UNIT_H87`, OCI/DATANORM: `PIECE`).
     */
    public static function tryUnitCode(?string $unit, UnitCode $pieceCode = UnitCode::PIECE): ?UnitCode {
        $trimmed = trim((string) $unit);
        if ($trimmed === '') {
            return null;
        }

        $direct = UnitCode::tryFrom(strtoupper($trimmed));
        if ($direct !== null) {
            return $direct;
        }

        $normalized = mb_strtolower(rtrim($trimmed, '.'));
        if (in_array($normalized, self::PIECE_WORDS, true)) {
            return $pieceCode;
        }
        if (isset(self::WORDS[$normalized])) {
            return self::WORDS[$normalized];
        }

        foreach (UnitCode::cases() as $case) {
            if (mb_strtolower(rtrim($case->abbreviation(), '.')) === $normalized) {
                return $case;
            }
        }

        return null;
    }

    /** ISO-Einheit für DATANORM 5; unbekannte Einheiten fallen auf `PCE` zurück. */
    public static function toDatanorm(?string $unit): string {
        $normalized = mb_strtolower(rtrim(trim((string) $unit), '.'));
        if (isset(self::DATANORM_SPECIALS[$normalized])) {
            return self::DATANORM_SPECIALS[$normalized];
        }

        $code = self::tryUnitCode($unit);
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
