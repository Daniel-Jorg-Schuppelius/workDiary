<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Migration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status eines Buchhaltungswechsels (MVP-653, Issue #86). Der Lauf führt
 * von der Planung über Analyse und Zuordnung in den Doppelbetrieb, die
 * Umschaltung am Stichtag und die Prüfung bis zum Abschluss.
 */
enum AccountingMigrationStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Analyzing = 'analyzing';
    case Mapping = 'mapping';
    case Ready = 'ready';
    case ParallelRun = 'parallel_run';
    case Cutover = 'cutover';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => __('enums.migration.accounting_migration_status.draft'),
            self::Analyzing => __('enums.migration.accounting_migration_status.analyzing'),
            self::Mapping => __('enums.migration.accounting_migration_status.mapping'),
            self::Ready => __('enums.migration.accounting_migration_status.ready'),
            self::ParallelRun => __('enums.migration.accounting_migration_status.parallel_run'),
            self::Cutover => __('enums.migration.accounting_migration_status.cutover'),
            self::Verifying => __('enums.migration.accounting_migration_status.verifying'),
            self::Completed => __('enums.migration.accounting_migration_status.completed'),
            self::Blocked => __('enums.migration.accounting_migration_status.blocked'),
            self::Cancelled => __('enums.migration.accounting_migration_status.cancelled'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Completed => 'success',
            self::Blocked, self::Cancelled => 'error',
            self::Cutover, self::ParallelRun => 'warning',
            self::Ready, self::Verifying => 'info',
            default => 'ghost',
        };
    }

    /** Endzustand — kein weiterer Übergang möglich. */
    public function isFinal(): bool {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** Läuft der Doppelbetrieb (beide Systeme verbunden)? */
    public function isRunning(): bool {
        return in_array($this, [self::ParallelRun, self::Cutover, self::Verifying], true);
    }

    /**
     * Erlaubte Folgezustände. `blocked` ist aus jedem laufenden Zustand
     * erreichbar und kehrt zur Zuordnung zurück.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Analyzing, self::Cancelled],
            self::Analyzing => [self::Mapping, self::Blocked, self::Cancelled],
            self::Mapping => [self::Ready, self::Analyzing, self::Blocked, self::Cancelled],
            self::Ready => [self::ParallelRun, self::Mapping, self::Blocked, self::Cancelled],
            self::ParallelRun => [self::Cutover, self::Blocked, self::Cancelled],
            self::Cutover => [self::Verifying, self::Blocked],
            self::Verifying => [self::Completed, self::Cutover, self::Blocked],
            self::Blocked => [self::Mapping, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
