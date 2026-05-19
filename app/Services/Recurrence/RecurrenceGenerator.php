<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Recurrence;

use App\Models\DiaryEntry;
use App\Models\RecurrenceRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Erzeugt aus aktiven RecurrenceRules bis zu `lookaheadDays` in die Zukunft
 * DiaryEntry-Datensätze. Idempotent: existiert für eine Rule und ein Datum
 * bereits ein Eintrag (über `due_date`), wird nichts erzeugt.
 */
class RecurrenceGenerator
{
    public function __construct(private readonly int $defaultLookaheadDays = 28) {}

    /**
     * Iteriert über alle aktiven Regeln und erzeugt fehlende Aufträge.
     *
     * @return int Anzahl erzeugter DiaryEntries
     */
    public function generateAll(?int $lookaheadDays = null, ?CarbonImmutable $now = null): int
    {
        $now = $now ?? CarbonImmutable::now();
        $lookahead = $lookaheadDays ?? $this->defaultLookaheadDays;

        $created = 0;

        RecurrenceRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($rules) use ($now, $lookahead, &$created): void {
                foreach ($rules as $rule) {
                    $created += $this->generateForRule($rule, $now, $lookahead);
                }
            });

        return $created;
    }

    /**
     * Erzeugt für eine einzelne Regel die fehlenden DiaryEntries.
     */
    public function generateForRule(RecurrenceRule $rule, CarbonImmutable $now, int $lookaheadDays): int
    {
        $horizon = $now->copy()->addDays($lookaheadDays)->startOfDay();
        if ($rule->ends_on !== null) {
            $end = CarbonImmutable::parse($rule->ends_on)->startOfDay();
            if ($end->lessThan($horizon)) {
                $horizon = $end;
            }
        }

        $start = CarbonImmutable::parse($rule->starts_on)->startOfDay();
        if ($rule->last_generated_until !== null) {
            $lastGen = CarbonImmutable::parse($rule->last_generated_until)->addDay()->startOfDay();
            if ($lastGen->greaterThan($start)) {
                $start = $lastGen;
            }
        }

        if ($start->greaterThan($horizon)) {
            return 0;
        }

        $occurrences = $this->occurrencesBetween($rule, $start, $horizon);
        if ($occurrences === []) {
            $this->markGeneratedUntil($rule, $horizon);

            return 0;
        }

        $createdCount = 0;
        DB::transaction(function () use ($rule, $occurrences, &$createdCount): void {
            foreach ($occurrences as $date) {
                $exists = DiaryEntry::query()
                    ->where('recurrence_rule_id', $rule->id)
                    ->whereDate('due_date', $date->toDateString())
                    ->exists();
                if ($exists) {
                    continue;
                }

                $this->createFromRule($rule, $date);
                $createdCount++;
            }
        });

        $this->markGeneratedUntil($rule, $horizon);

        return $createdCount;
    }

    private function markGeneratedUntil(RecurrenceRule $rule, CarbonImmutable $horizon): void
    {
        $rule->forceFill(['last_generated_until' => $horizon->toDateString()])->save();
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function occurrencesBetween(RecurrenceRule $rule, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];
        $interval = max(1, (int) $rule->interval);

        switch ($rule->frequency) {
            case RecurrenceRule::FREQ_DAILY:
                $cursor = CarbonImmutable::parse($rule->starts_on)->startOfDay();
                while ($cursor->lessThanOrEqualTo($end)) {
                    if ($cursor->greaterThanOrEqualTo($start)) {
                        $out[] = $cursor;
                    }
                    $cursor = $cursor->addDays($interval);
                }
                break;

            case RecurrenceRule::FREQ_WEEKLY:
                $weekdays = $this->weekdayBitmap($rule);
                $cursor = CarbonImmutable::parse($rule->starts_on)->startOfWeek(CarbonImmutable::MONDAY);
                while ($cursor->lessThanOrEqualTo($end)) {
                    for ($d = 0; $d < 7; $d++) {
                        $day = $cursor->addDays($d);
                        if ($day->lessThan($start) || $day->greaterThan($end)) {
                            continue;
                        }
                        $iso = ((int) $day->dayOfWeekIso) - 1; // 0=Mo .. 6=So
                        if (($weekdays & (1 << $iso)) === 0) {
                            continue;
                        }
                        $out[] = $day;
                    }
                    $cursor = $cursor->addWeeks($interval);
                }
                break;

            case RecurrenceRule::FREQ_MONTHLY:
                $cursor = CarbonImmutable::parse($rule->starts_on)->startOfMonth();
                $day = $rule->bymonthday ?? (int) CarbonImmutable::parse($rule->starts_on)->day;
                while ($cursor->lessThanOrEqualTo($end)) {
                    $occ = $this->safeDayOfMonth($cursor, $day);
                    if ($occ->greaterThanOrEqualTo($start) && $occ->lessThanOrEqualTo($end)) {
                        $out[] = $occ;
                    }
                    $cursor = $cursor->addMonths($interval);
                }
                break;

            case RecurrenceRule::FREQ_YEARLY:
                $cursor = CarbonImmutable::parse($rule->starts_on)->startOfYear();
                $month = $rule->bymonth ?? (int) CarbonImmutable::parse($rule->starts_on)->month;
                $day = $rule->bymonthday ?? (int) CarbonImmutable::parse($rule->starts_on)->day;
                while ($cursor->lessThanOrEqualTo($end)) {
                    $occ = $this->safeDayOfMonth($cursor->setMonth($month), $day);
                    if ($occ->greaterThanOrEqualTo($start) && $occ->lessThanOrEqualTo($end)) {
                        $out[] = $occ;
                    }
                    $cursor = $cursor->addYears($interval);
                }
                break;
        }

        return $out;
    }

    /**
     * Wochentag-Bitmap (Bit 0 = Mo, Bit 6 = So). Wenn nichts gesetzt ist,
     * wird der Wochentag des Startdatums genommen, damit „wöchentlich" nicht
     * leer bleibt.
     */
    private function weekdayBitmap(RecurrenceRule $rule): int
    {
        $codes = $rule->weekdays();
        if ($codes === []) {
            $iso = ((int) CarbonImmutable::parse($rule->starts_on)->dayOfWeekIso) - 1;

            return 1 << $iso;
        }

        $map = ['MO' => 0, 'TU' => 1, 'WE' => 2, 'TH' => 3, 'FR' => 4, 'SA' => 5, 'SU' => 6];
        $bm = 0;
        foreach ($codes as $code) {
            $code = strtoupper((string) $code);
            if (! isset($map[$code])) {
                continue;
            }
            $bm |= 1 << $map[$code];
        }

        return $bm;
    }

    /**
     * Setzt den Tag im Monat, klemmt bei Monatsende (z.B. 31. im Februar → 28./29.).
     */
    private function safeDayOfMonth(CarbonImmutable $monthAnchor, int $day): CarbonImmutable
    {
        $monthAnchor = $monthAnchor->startOfMonth();
        $maxDay = (int) $monthAnchor->daysInMonth;
        $day = min($day, $maxDay);

        return $monthAnchor->setDay($day);
    }

    /**
     * Legt einen DiaryEntry aus der Regel und einem Stichdatum an. Modus ist
     * `recurring` mit `due_date` als Stichdatum; das macht die Aufträge im
     * Backlog/Tour-Vorschlag sichtbar.
     */
    private function createFromRule(RecurrenceRule $rule, CarbonImmutable $date): DiaryEntry
    {
        $title = $this->renderTemplate($rule->title_template ?? '', $date);
        $content = $this->renderTemplate($rule->content_template, $date);

        $userId = $rule->created_by ?? $rule->assigned_user_id;
        if ($userId === null) {
            throw new \LogicException(sprintf(
                'RecurrenceRule #%d hat weder created_by noch assigned_user_id — kein Inhaber für den erzeugten Auftrag.',
                $rule->id
            ));
        }

        $entry = new DiaryEntry();
        $entry->organization_id = $rule->organization_id;
        $entry->entry_type_id = $rule->entry_type_id;
        $entry->user_id = $userId;
        $entry->assigned_user_id = $rule->assigned_user_id;
        $entry->project_id = $rule->project_id;
        $entry->customer_id = $rule->customer_id;
        $entry->title = $title !== '' ? $title : null;
        $entry->content = $content;
        $entry->status = DiaryEntry::STATUS_OPEN;
        $entry->priority = $rule->default_priority;
        $entry->service_minutes = $rule->default_service_minutes;
        $entry->mode = DiaryEntry::MODE_RECURRING;
        $entry->due_date = $date->toDateString();
        $entry->location_mode = $rule->default_location_mode ?: DiaryEntry::LOCATION_ONSITE;
        $entry->recurrence_rule_id = $rule->id;
        $entry->is_archived = false;
        $entry->save();

        return $entry;
    }

    private function renderTemplate(string $template, CarbonImmutable $date): string
    {
        return strtr($template, [
            '{date}' => $date->format('d.m.Y'),
            '{iso_date}' => $date->toDateString(),
            '{year}' => $date->format('Y'),
            '{month}' => $date->format('m'),
            '{week}' => $date->format('W'),
        ]);
    }
}
