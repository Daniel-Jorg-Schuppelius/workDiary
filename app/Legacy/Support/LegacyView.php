<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyView.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Support;

use Illuminate\Http\Request;

/**
 * Darstellung des Legacy-Bereichs (Tagebuch, Callcenter, Support-Portal):
 * „classic" im Aussehen der Altportale oder „modern" in der App-Oberfläche.
 * Routen, Prüfungen und Daten sind dieselben; die Wahl gilt je Browser
 * (Cookie), die Vorgabe kommt aus der Konfiguration.
 */
final class LegacyView {
    public const string COOKIE = 'legacy_view';

    public const string CLASSIC = 'classic';

    public const string MODERN = 'modern';

    /** @var list<string> */
    public const array MODES = [self::CLASSIC, self::MODERN];

    public static function mode(Request $request): string {
        $chosen = $request->cookie(self::COOKIE);
        if (is_string($chosen) && in_array($chosen, self::MODES, true)) {
            return $chosen;
        }

        $default = (string) config('app.legacy_view', self::CLASSIC);

        return in_array($default, self::MODES, true) ? $default : self::CLASSIC;
    }

    public static function isClassic(Request $request): bool {
        return self::mode($request) === self::CLASSIC;
    }

    /**
     * Viewname der gewählten Darstellung.
     *
     * @param  view-string  $modern
     * @param  view-string  $classic
     * @return view-string
     */
    public static function pick(Request $request, string $modern, string $classic): string {
        return self::isClassic($request) ? $classic : $modern;
    }
}
