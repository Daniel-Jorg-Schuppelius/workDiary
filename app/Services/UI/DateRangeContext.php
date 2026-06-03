<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRangeContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\UI;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;

/**
 * Holds the globally selected UI date range used by the new-area
 * reports and invoice flows. Persisted in the session so the choice
 * survives navigation between pages.
 */
class DateRangeContext {
    public const PRESET_TODAY = 'today';

    public const PRESET_THIS_WEEK = 'this_week';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_LAST_MONTH = 'last_month';

    public const PRESET_THIS_YEAR = 'this_year';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_LAST_30_DAYS = 'last_30_days';

    public const PRESET_LAST_90_DAYS = 'last_90_days';

    public const PRESET_CUSTOM = 'custom';

    /** @var array<int, string> */
    public const PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_THIS_WEEK,
        self::PRESET_THIS_MONTH,
        self::PRESET_LAST_MONTH,
        self::PRESET_THIS_YEAR,
        self::PRESET_LAST_7_DAYS,
        self::PRESET_LAST_30_DAYS,
        self::PRESET_LAST_90_DAYS,
        self::PRESET_CUSTOM,
    ];

    private const KEY_PRESET = 'ui.daterange.preset';

    private const KEY_FROM = 'ui.daterange.from';

    private const KEY_TO = 'ui.daterange.to';

    public function __construct(private readonly Session $session) {}

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, preset: string, effectivePreset: string, label: string, unit: string, isoWeekLabel: ?string}
     */
    public function current(): array {
        $preset = (string) $this->session->get(self::KEY_PRESET, self::PRESET_THIS_MONTH);
        if (! in_array($preset, self::PRESETS, true)) {
            $preset = self::PRESET_THIS_MONTH;
        }

        if ($preset === self::PRESET_CUSTOM) {
            $from = $this->parseDate((string) $this->session->get(self::KEY_FROM, ''))
                ?? CarbonImmutable::now()->startOfMonth();
            $to = $this->parseDate((string) $this->session->get(self::KEY_TO, ''))
                ?? CarbonImmutable::now()->endOfMonth();
        } else {
            [$from, $to] = $this->resolvePreset($preset);
        }

        if ($to->lessThan($from)) {
            $to = $from;
        }

        $effective = $preset === self::PRESET_CUSTOM
            ? ($this->detectMatchingPreset($from, $to) ?? self::PRESET_CUSTOM)
            : $preset;

        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'effectivePreset' => $effective,
            'label' => $this->labelFor($effective, $from, $to),
            'unit' => $this->unitFor($effective, $from, $to),
            'isoWeekLabel' => $this->isoWeekLabel($from, $to),
        ];
    }

    /**
     * Erkennt, ob ein beliebiger [from, to]-Range exakt einem der bekannten
     * Presets entspricht (bezogen auf "heute"). Liefert den passenden Preset-
     * Schlüssel oder null, wenn nichts matcht.
     */
    private function detectMatchingPreset(CarbonImmutable $from, CarbonImmutable $to): ?string {
        $candidates = [
            self::PRESET_TODAY,
            self::PRESET_THIS_WEEK,
            self::PRESET_THIS_MONTH,
            self::PRESET_LAST_MONTH,
            self::PRESET_THIS_YEAR,
            self::PRESET_LAST_7_DAYS,
            self::PRESET_LAST_30_DAYS,
            self::PRESET_LAST_90_DAYS,
        ];

        foreach ($candidates as $key) {
            [$pf, $pt] = $this->resolvePreset($key);
            if ($from->isSameDay($pf) && $to->isSameDay($pt)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Verschiebt den aktuellen Range um eine Periode (je nach Preset) vor
     * (+1) oder zurück (-1). Bei Non-Custom-Presets wechselt der Preset auf
     * 'custom', wenn das Ergebnis nicht mehr dem dynamischen Default
     * entspricht (z.B. „Letzter Monat“ wird zu Custom). Bei Custom-Ranges
     * wird um die Länge des Ranges verschoben.
     */
    public function shift(int $direction): void {
        $direction = $direction >= 0 ? 1 : -1;
        $state = $this->current();
        $from = $state['from'];
        $to = $state['to'];
        $unit = $state['unit'];

        switch ($unit) {
            case 'day':
                $newFrom = $from->addDays($direction);
                $newTo = $newFrom->endOfDay();
                $newFrom = $newFrom->startOfDay();
                break;
            case 'week':
                $newFrom = $from->addWeeks($direction)->startOfWeek();
                $newTo = $newFrom->endOfWeek();
                break;
            case 'month':
                $newFrom = $from->addMonthsNoOverflow($direction)->startOfMonth();
                $newTo = $newFrom->endOfMonth();
                break;
            case 'year':
                $newFrom = $from->addYears($direction)->startOfYear();
                $newTo = $newFrom->endOfYear();
                break;
            default: // custom
                $days = $from->diffInDays($to) + 1;
                $newFrom = $from->addDays($direction * $days)->startOfDay();
                $newTo = $newFrom->addDays($days - 1)->endOfDay();
                break;
        }

        $this->session->put(self::KEY_PRESET, self::PRESET_CUSTOM);
        $this->session->put(self::KEY_FROM, $newFrom->toDateString());
        $this->session->put(self::KEY_TO, $newTo->toDateString());
    }

    private function unitFor(string $preset, CarbonImmutable $from, CarbonImmutable $to): string {
        switch ($preset) {
            case self::PRESET_TODAY:
                return 'day';
            case self::PRESET_THIS_WEEK:
                return 'week';
            case self::PRESET_THIS_MONTH:
            case self::PRESET_LAST_MONTH:
                return 'month';
            case self::PRESET_THIS_YEAR:
                return 'year';
        }
        // Custom: leite Einheit aus Range ab, damit Vor/Zurück sinnvoll ist.
        // Tag-genauer Vergleich (isSameDay), da Custom-Ranges nur als Datum
        // (ohne Uhrzeit) in der Session liegen und endOf*-Zeiten beim Roundtrip
        // verloren gehen. Sonst würde ein voller Monat/Jahr fälschlich als
        // 'custom' erkannt und beim Vor/Zurück um Tage driften (Schaltjahre).
        if ($from->isSameDay($to)) {
            return 'day';
        }
        if ($from->isSameDay($from->startOfWeek()) && $to->isSameDay($from->endOfWeek())) {
            return 'week';
        }
        if ($from->isSameDay($from->startOfMonth()) && $to->isSameDay($from->endOfMonth())) {
            return 'month';
        }
        if ($from->isSameDay($from->startOfYear()) && $to->isSameDay($from->endOfYear())) {
            return 'year';
        }

        return 'custom';
    }

    private function isoWeekLabel(CarbonImmutable $from, CarbonImmutable $to): ?string {
        if (! $from->equalTo($from->startOfWeek()) || ! $to->equalTo($from->endOfWeek())) {
            return null;
        }

        return sprintf('KW %02d/%d', $from->isoWeek, $from->isoWeekYear);
    }

    /**
     * Persist a new range. For non-custom presets the dates are derived
     * dynamically and the supplied $from/$to are ignored.
     */
    public function set(string $preset, ?string $from = null, ?string $to = null): void {
        if (! in_array($preset, self::PRESETS, true)) {
            $preset = self::PRESET_THIS_MONTH;
        }

        $this->session->put(self::KEY_PRESET, $preset);

        if ($preset === self::PRESET_CUSTOM) {
            $f = $this->parseDate((string) $from) ?? CarbonImmutable::now()->startOfMonth();
            $t = $this->parseDate((string) $to) ?? $f->endOfMonth();
            if ($t->lessThan($f)) {
                $t = $f;
            }
            $this->session->put(self::KEY_FROM, $f->toDateString());
            $this->session->put(self::KEY_TO, $t->toDateString());
        } else {
            $this->session->forget([self::KEY_FROM, self::KEY_TO]);
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePreset(string $preset): array {
        $now = CarbonImmutable::now();

        return match ($preset) {
            self::PRESET_TODAY => [$now->startOfDay(), $now->endOfDay()],
            self::PRESET_THIS_WEEK => [$now->startOfWeek(), $now->endOfWeek()],
            self::PRESET_LAST_MONTH => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ],
            self::PRESET_THIS_YEAR => [$now->startOfYear(), $now->endOfYear()],
            self::PRESET_LAST_7_DAYS => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            self::PRESET_LAST_30_DAYS => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            self::PRESET_LAST_90_DAYS => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    private function parseDate(string $raw): ?CarbonImmutable {
        if ($raw === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function labelFor(string $preset, CarbonImmutable $from, CarbonImmutable $to): string {
        return match ($preset) {
            self::PRESET_TODAY => __('Heute'),
            self::PRESET_THIS_WEEK => __('Diese Woche'),
            self::PRESET_THIS_MONTH => __('Dieser Monat'),
            self::PRESET_LAST_MONTH => __('Letzter Monat'),
            self::PRESET_THIS_YEAR => __('Dieses Jahr'),
            self::PRESET_LAST_7_DAYS => __('Letzte 7 Tage'),
            self::PRESET_LAST_30_DAYS => __('Letzte 30 Tage'),
            self::PRESET_LAST_90_DAYS => __('Letzte 90 Tage'),
            default => $this->customLabel($from, $to),
        };
    }

    private function customLabel(CarbonImmutable $from, CarbonImmutable $to): string {
        if ($from->isSameDay($to)) {
            $today = CarbonImmutable::today();
            if ($from->isSameDay($today)) {
                return __('Heute');
            }
            if ($from->isSameDay($today->subDay())) {
                return __('Gestern');
            }
            if ($from->isSameDay($today->addDay())) {
                return __('Morgen');
            }

            return $from->format('d.m.Y');
        }

        return $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y');
    }
}
