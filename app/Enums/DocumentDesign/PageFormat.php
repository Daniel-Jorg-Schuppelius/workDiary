<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Seitenformat einer Profilversion (MVP-652, Issue #85). Bis dahin war das
 * Dokumentdesign auf A4-Hochformat festgelegt; Querformat-Dokumente (17
 * Berichte) liefen deklariert design-frei. Ein Profil gilt für genau EIN
 * Format — Hochformat-Druckbereiche passen nicht auf Querformat.
 */
enum PageFormat: string implements HasLabel {
    use HasOptions;

    case A4Portrait = 'a4_portrait';

    case A4Landscape = 'a4_landscape';

    public function label(): string {
        return match ($this) {
            self::A4Portrait => __('A4 Hochformat'),
            self::A4Landscape => __('A4 Querformat'),
        };
    }

    /** Seitenbreite in Millimetern. */
    public function widthMm(): float {
        return $this === self::A4Landscape ? 297.0 : 210.0;
    }

    /** Seitenhöhe in Millimetern. */
    public function heightMm(): float {
        return $this === self::A4Landscape ? 210.0 : 297.0;
    }

    public function isLandscape(): bool {
        return $this === self::A4Landscape;
    }

    /** Format aus der Writer-Ausrichtung (`orientation` der Render-Optionen). */
    public static function fromOrientation(?string $orientation): self {
        return strtolower(trim((string) $orientation)) === 'landscape' ? self::A4Landscape : self::A4Portrait;
    }

    /** Seitenverhältnis Höhe/Breite — Maßprüfung hochgeladener Firmenbögen. */
    public function aspectRatio(): float {
        return $this->heightMm() / $this->widthMm();
    }
}
