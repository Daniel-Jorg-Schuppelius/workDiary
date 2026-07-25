<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DwdProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Weather;

use App\Services\Location\GeofenceMatcher;
use App\Services\Weather\Contracts\WeatherProvider;
use App\Support\Setting;
use Carbon\CarbonInterface;
use CommonToolkit\Enums\SpeedUnit;
use CommonToolkit\Helper\Data\{StringHelper, UnitConversionHelper};
use CommonToolkit\Helper\FileSystem\{File, Folder};
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use CommonToolkit\Parsers\CSVDocumentParser;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * DWD-Open-Data-Wetterprovider (Feature 062, MVP-131, Bauturbo A7): amtliche
 * Tageswerte (Climate Data Center, „daily/kl") von https://opendata.dwd.de —
 * ohne Credentials, Lizenz CC BY 4.0 (Quellenvermerk „Deutscher Wetterdienst"
 * wird im Snapshot-`raw` mitgeführt und an den Anzeigestellen ausgewiesen).
 *
 * Ablauf: Latin-1-Stationsliste (Festbreiten-Spalten) laden + cachen →
 * nächstgelegene, für den Tag gültige Station per Haversine bestimmen
 * (max. Distanz per Org-Setting, sonst {@see self::DEFAULT_MAX_STATION_KM};
 * darüber hinaus lieber „keine Station" als falsche Daten) → Stations-ZIP
 * (recent bzw. historical) laden, Produkt-CSV extrahieren und den Tag mappen.
 * ZIP/CSV/Encoding laufen über das php-common-toolkit. Fehlwert −999 ⇒ NULL;
 * FX (m/s) wird toolkit-seitig nach km/h konvertiert. Der HTTP-Client ist
 * injizierbar (Guzzle) — im Test über `MockHandler` ersetzt.
 */
class DwdProvider implements WeatherProvider {
    private const BASE = 'https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/daily/kl';
    private const STATIONS_CACHE_KEY = 'weather.dwd.stations.v1';
    private const STATIONS_CACHE_DAYS = 7; // Liste ist groß und ändert sich selten.
    private const RECENT_WINDOW_DAYS = 500; // recent-ZIPs decken ca. die letzten 1,5 Jahre ab.
    private const STATION_VALIDITY_SLACK_DAYS = 10; // bis_datum hinkt dem Messbetrieb einige Tage hinterher.
    private const MISSING = -999.0; // DWD-Fehlwert.

    public const DEFAULT_MAX_STATION_KM = 30;

    public function __construct(private readonly ClientInterface $http) {}

    public function key(): string {
        return 'dwd';
    }

    public function daily(float $lat, float $lng, CarbonInterface $date): ?array {
        try {
            $station = $this->nearestStation($lat, $lng, $date);
            if ($station === null) {
                return null; // Keine aktive Station in Reichweite: kein Snapshot statt falscher Daten.
            }

            $row = $this->dailyRow($station, $date);
            if ($row === null) {
                return null;
            }

            $gustMs = $this->numeric($row['FX'] ?? null); // FX = Tagesmaximum Windspitze in m/s.

            return [
                'temp_min' => $this->numeric($row['TNK'] ?? null),
                'temp_max' => $this->numeric($row['TXK'] ?? null),
                'precipitation_mm' => $this->numeric($row['RSK'] ?? null),
                'wind_gust_kmh' => $gustMs === null
                    ? null
                    : round(UnitConversionHelper::convertSpeed($gustMs, SpeedUnit::METER_PER_SECOND, SpeedUnit::KILOMETER_PER_HOUR), 1),
                // KL-Tageswerte führen keinen WMO-Wettercode; Bewölkung (NM)
                // und Sonnenschein (SDK) stehen ersatzweise im raw-Meta.
                'weather_code' => null,
                'raw' => [
                    'attribution' => 'Quelle: Deutscher Wetterdienst', // CC-BY-4.0-Pflichtvermerk.
                    'license' => 'CC BY 4.0',
                    'station_id' => $station['id'],
                    'station_name' => $station['name'],
                    'distance_km' => round($station['distance_km'], 1),
                    'temp_mean' => $this->numeric($row['TMK'] ?? null),
                    'sunshine_hours' => $this->numeric($row['SDK'] ?? null),
                    'cloud_cover_okta' => $this->numeric($row['NM'] ?? null),
                    'quality' => ['qn_3' => $row['QN_3'] ?? null, 'qn_4' => $row['QN_4'] ?? null],
                    'values' => $row,
                ],
            ];
        } catch (Throwable) {
            return null; // Vertragskonform: Ausfall blockiert nie einen Tagesbericht.
        }
    }

    /**
     * Nächstgelegene Station, deren Messzeitraum den Tag abdeckt, innerhalb
     * der konfigurierten Maximaldistanz (Org-Setting `weather.dwd_max_station_km`).
     *
     * @return array{id: int, name: string, distance_km: float}|null
     */
    private function nearestStation(float $lat, float $lng, CarbonInterface $date): ?array {
        $maxKm = (float) Setting::get('weather.dwd_max_station_km', self::DEFAULT_MAX_STATION_KM);
        $day = (int) $date->format('Ymd');
        // bis_datum aktiver Stationen hinkt der Liste einige Tage hinterher —
        // für junge Tage reicht daher Abdeckung bis „heute − Slack"; für
        // Alt-Tage muss der Messzeitraum den Tag strikt enthalten.
        $requiredUntil = min($day, (int) Carbon::today()->subDays(self::STATION_VALIDITY_SLACK_DAYS)->format('Ymd'));

        $best = null;
        $bestKm = INF;
        foreach ($this->stations() as $station) {
            if ($station['from'] > $day || $station['until'] < $requiredUntil) {
                continue; // Station misst (noch) nicht bzw. nicht mehr.
            }
            $km = GeofenceMatcher::distanceMeters($lat, $lng, $station['lat'], $station['lng']) / 1000.0;
            if ($km < $bestKm) {
                $best = $station;
                $bestKm = $km;
            }
        }

        if ($best === null || $bestKm > $maxKm) {
            return null;
        }

        return ['id' => $best['id'], 'name' => $best['name'], 'distance_km' => $bestKm];
    }

    /** @return list<array{id: int, from: int, until: int, lat: float, lng: float, name: string}> */
    private function stations(): array {
        /** @var list<array{id: int, from: int, until: int, lat: float, lng: float, name: string}> $stations */
        $stations = Cache::remember(
            self::STATIONS_CACHE_KEY,
            now()->addDays(self::STATIONS_CACHE_DAYS),
            fn (): array => $this->fetchStations(),
        );

        return $stations;
    }

    /**
     * Lädt und parst die Stationsliste (`KL_Tageswerte_Beschreibung_Stationen.txt`):
     * Latin-1-kodiert, Festbreiten-Spalten (Stations_id, von_datum, bis_datum,
     * Stationshoehe, geoBreite, geoLaenge, Stationsname, Bundesland, Abgabe).
     * Encoding-Konvertierung über das Toolkit; die sechs führenden numerischen
     * Spalten werden per Regex gelesen, der Stationsname als erster Block des
     * Rests (Festbreiten-Padding trennt Name und Bundesland mit ≥2 Leerzeichen).
     *
     * @return list<array{id: int, from: int, until: int, lat: float, lng: float, name: string}>
     */
    private function fetchStations(): array {
        $response = $this->http->request('GET', self::BASE . '/recent/KL_Tageswerte_Beschreibung_Stationen.txt', ['timeout' => 20]);
        $text = StringHelper::convertToUtf8((string) $response->getBody(), 'ISO-8859-1');

        $stations = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (! preg_match('/^\s*(\d{1,5})\s+(\d{8})\s+(\d{8})\s+(-?\d+)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(\S.*)$/', $line, $m)) {
                continue; // Kopfzeile, Trennstriche, Leerzeilen.
            }
            $tail = preg_split('/\s{2,}/', trim($m[7])) ?: [];
            $stations[] = [
                'id' => (int) $m[1],
                'from' => (int) $m[2],
                'until' => (int) $m[3],
                'lat' => (float) $m[5],
                'lng' => (float) $m[6],
                'name' => (string) ($tail[0] ?? ''),
            ];
        }

        return $stations;
    }

    /**
     * Tageszeile aus dem Stations-ZIP: junge Tage aus `recent/…_akt.zip`,
     * ältere über das `historical/`-Verzeichnislisting (der Dateiname trägt
     * den Messzeitraum und ist daher nicht vorab bekannt).
     *
     * @param array{id: int, name: string, distance_km: float} $station
     * @return array<string, string|null>|null
     */
    private function dailyRow(array $station, CarbonInterface $date): ?array {
        $isRecent = Carbon::parse($date->toDateString())->gte(Carbon::today()->subDays(self::RECENT_WINDOW_DAYS));
        $zipUrl = $isRecent
            ? sprintf('%s/recent/tageswerte_KL_%05d_akt.zip', self::BASE, $station['id'])
            : $this->historicalZipUrl($station['id']);
        if ($zipUrl === null) {
            return null;
        }

        $zipBody = (string) $this->http->request('GET', $zipUrl, ['timeout' => 30])->getBody();
        $csv = $this->extractProductCsv($zipBody);
        if ($csv === null) {
            return null;
        }

        // Produkt-CSV: Semikolon-getrennt, Werte/Spaltennamen rechtsbündig
        // gepolstert — Parsing über das Toolkit, Trim danach.
        $document = CSVDocumentParser::fromString($csv, ';', '"', true, 'ISO-8859-1');
        $needle = $date->format('Ymd');
        foreach ($document->toAssoc() as $row) {
            $clean = [];
            foreach ($row as $column => $value) {
                // toAssoc() liefert list<array<string,string>> — Werte sind nie
                // null (array_combine bricht bei Spalten-Mismatch, statt Lücken
                // zu füllen), die frühere null-Weiche war toter Code.
                $clean[trim((string) $column)] = trim((string) $value);
            }
            if (($clean['MESS_DATUM'] ?? null) === $needle) {
                return $clean;
            }
        }

        return null;
    }

    /** Sucht im historical-Verzeichnislisting den ZIP-Namen der Station. */
    private function historicalZipUrl(int $stationId): ?string {
        $listing = (string) $this->http->request('GET', self::BASE . '/historical/', ['timeout' => 20])->getBody();
        if (! preg_match(sprintf('/tageswerte_KL_%05d_\d{8}_\d{8}_hist\.zip/', $stationId), $listing, $m)) {
            return null;
        }

        return self::BASE . '/historical/' . $m[0];
    }

    /** Extrahiert die `produkt_klima_tag_*.txt` aus dem ZIP (Toolkit, Zip-Slip-geschützt). */
    private function extractProductCsv(string $zipBody): ?string {
        $workDir = sys_get_temp_dir() . '/dwd-' . bin2hex(random_bytes(8));
        try {
            Folder::create($workDir, 0755, true);
            $zipPath = $workDir . '/tageswerte.zip';
            File::write($zipPath, $zipBody);
            ZipFile::extract($zipPath, $workDir, true);

            $products = glob($workDir . '/produkt_*.txt') ?: [];

            return $products === [] ? null : File::read($products[0]);
        } finally {
            if (is_dir($workDir)) {
                Folder::delete($workDir, true);
            }
        }
    }

    /** DWD-Zahlwert: leere Felder und Fehlwert −999 werden zu NULL. */
    private function numeric(?string $value): ?float {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return $float <= self::MISSING ? null : $float;
    }
}
