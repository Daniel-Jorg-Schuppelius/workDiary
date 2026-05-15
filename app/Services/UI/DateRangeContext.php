<?php

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
    public const PRESET_CUSTOM = 'custom';

    /** @var array<int, string> */
    public const PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_THIS_WEEK,
        self::PRESET_THIS_MONTH,
        self::PRESET_LAST_MONTH,
        self::PRESET_THIS_YEAR,
        self::PRESET_CUSTOM,
    ];

    private const KEY_PRESET = 'ui.daterange.preset';
    private const KEY_FROM = 'ui.daterange.from';
    private const KEY_TO = 'ui.daterange.to';

    public function __construct(private readonly Session $session) {
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, preset: string, label: string}
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

        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'label' => $this->labelFor($preset, $from, $to),
        ];
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
            default => $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
        };
    }
}
