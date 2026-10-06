<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BundesbankSeries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Plugins\Support\PluginHttpFactory;
use CommonToolkit\Helper\Data\NumberHelper;
use RuntimeException;

/** Monatsreihen der Bundesbank-Statistik (Basiszins, Verbraucherpreisindex): Abruf und CSV-Zeilen. */
final class BundesbankSeries {
    public function __construct(private readonly PluginHttpFactory $http) {}

    public function csv(string $url): string {
        $response = $this->http->coreClient('bundesbank', $url)
            ->getResponse($url, ['format' => 'csv'], ['timeout' => 30]);
        if (! $response->successful()) {
            throw new RuntimeException('Bundesbank-Abruf fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->body();
    }

    /**
     * Zeilen „JJJJ-MM;Wert;…" (Dezimalkomma); Kopf- und Bemerkungszeilen
     * fallen weg.
     *
     * @return array<string, numeric-string> „JJJJ-MM" => Wert als Dezimalzeichenkette, in Dateireihenfolge
     */
    public static function monthlyValues(string $csv): array {
        $months = [];
        foreach (preg_split('/\r?\n/', $csv) ?: [] as $line) {
            if (preg_match('/^(\d{4})-(\d{2});(-?[\d.,]+);/', trim($line), $m) === 1) {
                $months[$m[1] . '-' . $m[2]] = NumberHelper::normalizeDecimalString($m[3]);
            }
        }

        return $months;
    }
}
