<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeoDatabaseUpdater.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Plugins\Support\PluginHttpFactory;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use CommonToolkit\Helper\Geo\IpLocationHelper;
use Generator;
use MaxMind\Db\Reader;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Hält die lokale IP-Geodatenbank aktuell (Feature 085, MVP-1021): lädt die
 * DB-IP-Lite-Datei des laufenden Monats (sonst des Vormonats), entpackt sie
 * neben das Ziel, prüft sie mit dem Reader und tauscht sie per rename aus —
 * eine defekte Lieferung ersetzt die laufende Datenbank nie.
 */
class GeoDatabaseUpdater {
    public const SERVICE_ID = 'geoip';

    /** Öffentliche Adresse, für die jede Stadt-/Länder-Datenbank einen Eintrag führt. */
    public const PROBE_IP = '8.8.8.8';

    private const CHUNK_BYTES = 1 << 20;

    public function __construct(private readonly PluginHttpFactory $http) {}

    /** Build-Zeitpunkt der Datenbank unter $path, null ohne lesbare Datei. */
    public static function buildOf(?string $path): ?CarbonImmutable {
        if ($path === null || $path === '' || ! File::isFile($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);
            try {
                return CarbonImmutable::createFromTimestampUTC($reader->metadata()->buildEpoch);
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{status: 'updated'|'current', month: string}
     *
     * @throws GeoDatabaseUpdateException
     */
    public function update(bool $force = false): array {
        $target = (string) config('geoip.database', '');
        if ($target === '') {
            throw new GeoDatabaseUpdateException('GEOIP_DATABASE ist nicht gesetzt — die Aktualisierung braucht einen Zielpfad.');
        }

        $installed = self::buildOf($target)?->format('Y-m');
        $now = CarbonImmutable::now('UTC');

        // DB-IP veröffentlicht die Monatsdatei in den ersten Tagen des Monats.
        foreach ([$now, $now->subMonthNoOverflow()] as $month) {
            $label = $month->format('Y-m');
            if (! $force && $installed !== null && $installed >= $label) {
                return ['status' => 'current', 'month' => $installed];
            }

            $url = str_replace('{month}', $label, (string) config('geoip.update.url'));
            $response = $this->http->coreClient(self::SERVICE_ID, $url)->getResponse($url, [], ['timeout' => 600]);
            if ($response->status() === 404) {
                continue;
            }
            if (! $response->successful()) {
                throw new GeoDatabaseUpdateException("Abruf der Geodatenbank fehlgeschlagen (HTTP {$response->status()}).");
            }

            $this->install($response->toPsrResponse()->getBody(), $target);

            return ['status' => 'updated', 'month' => $label];
        }

        throw new GeoDatabaseUpdateException('Für den laufenden und den Vormonat ist keine Geodatenbank veröffentlicht.');
    }

    private function install(StreamInterface $gzip, string $target): void {
        $directory = File::directory($target);
        if (! Folder::exists($directory)) {
            Folder::create($directory, 0755, true);
        }

        // Im Zielverzeichnis entpacken: nur dort ist der Austausch ein atomares rename.
        $temporary = $target . '.download';
        $inflate = inflate_init(ZLIB_ENCODING_GZIP);
        if ($inflate === false) {
            throw new GeoDatabaseUpdateException('Die zlib-Erweiterung kann das Archiv nicht entpacken.');
        }

        try {
            if (File::writeStream($temporary, $this->inflated($gzip, $inflate)) === false
                || inflate_get_status($inflate) !== ZLIB_STREAM_END) {
                throw new GeoDatabaseUpdateException('Die Geodatenbank kam unvollständig an.');
            }
            $this->assertUsable($temporary);
            File::move($temporary, $directory, File::filename($target), true);
        } finally {
            if (File::exists($temporary)) {
                File::delete($temporary);
            }
        }
    }

    /** @return Generator<int, string> */
    private function inflated(StreamInterface $gzip, \InflateContext $inflate): Generator {
        while (! $gzip->eof()) {
            try {
                $chunk = inflate_add($inflate, $gzip->read(self::CHUNK_BYTES), ZLIB_SYNC_FLUSH);
            } catch (Throwable $e) {
                throw new GeoDatabaseUpdateException('Das Archiv der Geodatenbank ist beschädigt.', 0, $e);
            }
            if ($chunk === false) {
                throw new GeoDatabaseUpdateException('Das Archiv der Geodatenbank ist beschädigt.');
            }
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }

    private function assertUsable(string $path): void {
        try {
            $reader = new Reader($path);
        } catch (Throwable $e) {
            throw new GeoDatabaseUpdateException('Die Lieferung ist keine lesbare MaxMind-Datenbank.', 0, $e);
        }

        try {
            if (IpLocationHelper::mapRecord($reader->get(self::PROBE_IP)) === null) {
                throw new GeoDatabaseUpdateException('Die gelieferte Geodatenbank kennt die Prüfadresse nicht.');
            }
        } finally {
            $reader->close();
        }
    }
}
