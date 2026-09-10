<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenceMonths.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\View\Components\Resale;

use App\Services\Reselling\Register\LicenseMonths;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Lizenzmonate und Stückzahlen des Reselling-Registers anzeigen (Feature 152,
 * Review 2026-09-10 D): die eine Formatierung statt `$fmt`-Closures in sechs
 * Views. Mit `per-licence` als „5 × 12 Mon." (LicenseMonths::label), sonst
 * als kompakte Zahl ohne Nachkomma-Nullen; `unit` hängt „Mon." an.
 *
 *     <x-resale.licence-months :value="12" />                 → 12
 *     <x-resale.licence-months :value="2.5" unit />           → 2,5 Mon.
 *     <x-resale.licence-months :value="60" :per-licence="12" /> → 5 × 12 Mon.
 *
 * Für Attribute und Übersetzungsparameter: `LicenceMonths::compact($v)`.
 */
final class LicenceMonths extends Component {
    public function __construct(
        public float|int|string $value = 0,
        public float|int|string|null $perLicence = null,
        public bool $unit = false,
    ) {}

    /** Zahl ohne Nachkomma-Nullen, deutsches Format wie `Money::format()`. */
    public static function compact(float|int|string $value): string {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    public function text(): string {
        $value = (float) $this->value;
        if ($this->perLicence !== null) {
            return LicenseMonths::label($value, (float) $this->perLicence);
        }

        return self::compact($value) . ($this->unit ? ' ' . __('resale.link.months_short') : '');
    }

    public function render(): View {
        return view('components.resale.licence-months');
    }
}
