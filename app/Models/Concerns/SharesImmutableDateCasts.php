<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharesImmutableDateCasts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Teilt die Datumsobjekte der `immutable_date`-Spalten zwischen allen Zugriffen:
 * Eloquent baut sonst bei jedem Lesen ein neues Objekt (rund 16 µs), und
 * Rechenwege über viele Perioden lesen dieselben Tage zehntausendfach. Die
 * Objekte sind unveränderlich; je Rohwert und Zeitzone entsteht eines. Dazu
 * merkt das Modell seine Cast-Liste ({@see getCasts()}).
 */
trait SharesImmutableDateCasts {
    /** @var array<string, mixed> "<Zeitzone>|<Rohwert>" → Datum */
    private static array $sharedImmutableDates = [];

    /** @var array<string, string>|null */
    private ?array $castsOfRecord = null;

    /** @var array<string, string>|null Stand von `$casts`, zu dem {@see} gehört */
    private ?array $castsOfRecordSource = null;

    /**
     * Eloquent fragt die Casts je Attributzugriff mehrfach ab und fügt dabei jedes Mal den Schlüssel-Cast
     * per `array_merge` hinzu. Das Ergebnis gilt, solange `$casts` dasselbe Array ist (`mergeCasts()` ersetzt es).
     *
     * @return array<string, string>
     */
    public function getCasts() {
        if ($this->castsOfRecord === null || $this->castsOfRecordSource !== $this->casts) {
            $this->castsOfRecordSource = $this->casts;
            $this->castsOfRecord = parent::getCasts();
        }

        return $this->castsOfRecord;
    }

    protected function castAttribute($key, $value) {
        if (is_string($value) && ($this->casts[$key] ?? null) === 'immutable_date') {
            return self::$sharedImmutableDates[date_default_timezone_get() . '|' . $value] ??= parent::castAttribute($key, $value);
        }

        return parent::castAttribute($key, $value);
    }
}
