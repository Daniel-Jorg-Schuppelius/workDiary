<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherWarningScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Diary\Status;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Weather\WeatherWarningThreshold;
use App\Models\{DiaryEntry, Organization, User, WeatherWarning};
use App\Models\Notification\NotificationRule;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Weather\WeatherService;
use App\Support\{OrganizationContext, Setting};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Wetterwarnungen für disponierte Einsätze (Feature 062, MVP-716 — Vollscan
 * G15): für Aufträge der nächsten {@see self::HORIZON_DAYS} Tage mit
 * Koordinaten (Auftragsadresse, sonst Kunde) wird die Tagesvorhersage gegen
 * die Org-Schwellen ({@see WeatherWarningThreshold}) geprüft. Je Treffer
 * entsteht genau eine {@see WeatherWarning}-Zeile (Unique Einsatz+Tag+
 * Schwelle) — sie ist das Subjekt der Benachrichtigung, das
 * notification_dispatch_log dedupliziert darüber.
 *
 * Läuft im stündlichen notifications:scan-deadlines mit: Vorhersagen sind je
 * Koordinate/Provider für einige Stunden gecacht ({@see WeatherService::forecast}),
 * Orgs ohne aktive Regel bzw. mit `weather.warnings_enabled = false` lösen
 * keinen Abruf aus. Provider ohne Vorhersage (DWD) → null → keine Warnung,
 * kein Fehler.
 */
class WeatherWarningScan extends AbstractDeadlineScan {
    public const HORIZON_DAYS = 3;

    public function key(): string {
        return 'weather_warnings';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            $this->candidates(),
            fn(Organization $organization): int => OrganizationContext::run(
                $organization,
                fn(): int => $this->scanOrganization($organization, $dispatcher),
            ),
        );
    }

    /**
     * Disponierte Einsätze im Horizont: terminiert (scheduled_for/start_at)
     * UND zugewiesen oder geplant, nicht erledigt/storniert. Ohne Org-Kontext
     * ungescopt — die Regel-/Setting-Auflösung läuft danach je Organisation.
     *
     * @return Builder<DiaryEntry>
     */
    private function candidates(): Builder {
        $from = CarbonImmutable::today();
        $to = $from->addDays(self::HORIZON_DAYS - 1);

        return DiaryEntry::query()
            ->whereNotIn('status', [Status::Done->value, Status::AcceptedFinal->value, Status::Invoiced->value, Status::Cancelled->value])
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->where(fn(Builder $q) => $q->whereNotNull('assigned_user_id')->orWhereNotNull('planned_at'))
            ->where(fn(Builder $q) => $q
                ->whereBetween('scheduled_for', DateRange::days($from, $to))
                ->orWhere(fn(Builder $w) => $w->whereNull('scheduled_for')->whereBetween('start_at', [$from->startOfDay(), $to->endOfDay()])));
    }

    private function scanOrganization(Organization $organization, NotificationDispatcher $dispatcher): int {
        if (! (bool) Setting::get('weather.warnings_enabled', true)) {
            return 0;
        }
        if (! NotificationRule::resolveFor((int) $organization->id, NotificationEvent::WeatherWarning)->enabled) {
            return 0; // Kein Abruf, wenn niemand die Warnung bekäme.
        }

        /** @var array<string, float> $limits */
        $limits = [];
        foreach (WeatherWarningThreshold::cases() as $threshold) {
            $limits[$threshold->value] = (float) Setting::get($threshold->settingKey(), $threshold->defaultLimit());
        }

        $weather = app(WeatherService::class);
        $sent = 0;

        foreach ($this->candidates()->where('organization_id', $organization->id)->lazyById(200) as $entry) {
            $day = $this->dayOf($entry);
            $coords = $weather->coordsForDiaryEntry($entry);
            if ($day === null || $coords === null) {
                continue;
            }

            $forecast = $weather->forecast($organization, $coords[0], $coords[1], self::HORIZON_DAYS);
            if ($forecast === null) {
                continue; // Provider ohne Vorhersage oder Ausfall: sauber degradieren.
            }
            $row = $forecast[$day] ?? null;
            if ($row === null) {
                continue;
            }

            foreach (WeatherWarningThreshold::cases() as $threshold) {
                $value = $threshold->valueOf($row);
                $limit = $limits[$threshold->value];
                if ($value === null || ! $threshold->isExceeded($value, $limit)) {
                    continue;
                }

                $warning = WeatherWarning::query()->firstOrCreate(
                    ['diary_entry_id' => $entry->id, 'forecast_date' => CarbonImmutable::parse($day), 'threshold' => $threshold->value],
                    [
                        'organization_id' => $organization->id,
                        'value' => round($value, 2),
                        'limit_value' => round($limit, 2),
                        'provider' => $weather->providerKey(),
                        'forecast' => $row,
                    ],
                );

                $sent += $dispatcher->notify(
                    NotificationEvent::WeatherWarning,
                    $warning,
                    $this->affected($entry),
                    $this->payload($entry, $warning, $day),
                    dedup: true,
                );
            }
        }

        return $sent;
    }

    /** Einsatztag: disponiertes Datum, sonst Beginn. */
    private function dayOf(DiaryEntry $entry): ?string {
        if ($entry->scheduled_for !== null) {
            return Carbon::parse((string) $entry->scheduled_for)->toDateString();
        }
        if ($entry->start_at !== null) {
            return Carbon::parse((string) $entry->start_at)->toDateString();
        }

        return null;
    }

    private function affected(DiaryEntry $entry): ?User {
        $userId = $entry->getAttribute('assigned_user_id');

        return $userId === null ? null : User::query()->find((int) $userId);
    }

    /**
     * Render-time-Payload (title_key/message_key + params) mit `due_at` für
     * den Kalender-Kanal.
     *
     * @return array{title: string, message: string, url: string, icon: string, due_at: Carbon}
     */
    private function payload(DiaryEntry $entry, WeatherWarning $warning, string $day): array {
        $threshold = $warning->threshold;
        $params = [
            'title' => (string) ($entry->title ?: ('#' . $entry->id)),
            'date' => Carbon::parse($day)->format('d.m.Y'),
            'value' => (string) round((float) $warning->value, 1),
            'limit' => (string) round((float) $warning->limit_value, 1),
            'unit' => $threshold->unit(),
        ];
        $messageKey = 'notification.message.weather_warning_' . $threshold->value;

        return [
            'title' => (string) __('notification.message.weather_warning_title', $params),
            'title_key' => 'notification.message.weather_warning_title',
            'title_params' => $params,
            'message' => (string) __($messageKey, $params),
            'message_key' => $messageKey,
            'message_params' => $params + ['date' => $day],
            'url' => route('diary.show', $entry),
            'icon' => NotificationEvent::WeatherWarning->icon(),
            'due_at' => Carbon::parse($day)->startOfDay(),
        ];
    }
}
