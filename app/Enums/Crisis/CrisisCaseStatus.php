<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Crisis;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Führungszustand einer Krisenakte (Feature 070, MVP-212): Meldung, Lage, Entwarnung, Nachbereitung, Abschluss. */
enum CrisisCaseStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Prepared = 'prepared';
    case Reported = 'reported';
    case Assessed = 'assessed';
    case Activated = 'activated';
    case InProgress = 'in_progress';
    case Stabilized = 'stabilized';
    case Recovery = 'recovery';
    case AllClear = 'all_clear';
    case PostReview = 'post_review';
    case Closed = 'closed';

    /** Wie „vorbereitet“: im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Discarded = 'discarded';

    /**
     * Aktive Führungszustände (Dashboard, Notfallzugriff, Statusseite).
     *
     * @return list<self>
     */
    public static function active(): array {
        return [self::Reported, self::Assessed, self::Activated, self::InProgress, self::Stabilized, self::Recovery];
    }

    /**
     * Lagezustände in der Auswahl der Akte — nur sie sind von Hand setzbar;
     * Aktivierung, Entwarnung, Nachbereitung und Abschluss haben eigene Aktionen.
     *
     * @return list<self>
     */
    public static function stages(): array {
        return [self::Assessed, self::InProgress, self::Stabilized, self::Recovery];
    }

    /**
     * Beendete Krisen (BCM-Bericht).
     *
     * @return list<self>
     */
    public static function ended(): array {
        return [self::AllClear, self::PostReview, self::Closed];
    }

    public function isActive(): bool {
        return in_array($this, self::active(), true);
    }

    public function isStage(): bool {
        return in_array($this, self::stages(), true);
    }

    /** Geschlossene und verworfene Akten nehmen nichts mehr auf. */
    public function isShelved(): bool {
        return in_array($this, [self::Closed, self::Discarded], true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /**
     * Lagezustände in der Auswahl der Akte: der aktuelle und die laut Tabelle
     * erreichbaren — Auswahl und Server sagen damit dasselbe.
     *
     * @return list<self>
     */
    public function selectableStages(): array {
        return array_values(array_filter(self::stages(), fn (self $stage): bool => $stage === $this || $this->canTransitionTo($stage)));
    }

    /**
     * Vor der Aktivierung (gemeldet/bewertet) stehen alle Lagezustände,
     * Entwarnung und Aktivierung offen. Einmal aktiviert, nie wieder
     * „bewertet“: ein Zurück ließe erneut aktivieren und die Meldefristen von
     * vorn beginnen. Aus einem Lagezustand bleibt die Aktivierung erreichbar,
     * damit eine ohne Alarm begonnene Akte noch alarmiert werden kann — ob sie
     * schon aktiviert war, prüft {@see \App\Models\Crisis\CrisisCase::canBeActivated()}
     * über `activated_at`. Danach Nachbereitung und Abschluss in dieser Folge.
     * Geschlossen und verworfen sind Endzustände; aus „vorbereitet“ führt
     * kein Weg, solange es keine Schreibstelle hat.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        $targets = match (true) {
            $this === self::Reported, $this === self::Assessed => [...self::stages(), self::AllClear, self::Activated],
            $this->isActive() => [self::InProgress, self::Stabilized, self::Recovery, self::AllClear, self::Activated],
            $this === self::AllClear => [self::PostReview],
            $this === self::PostReview => [self::Closed],
            default => [],
        };

        return array_values(array_filter($targets, fn (self $target): bool => $target !== $this));
    }
}
