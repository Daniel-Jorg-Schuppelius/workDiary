<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\Retention;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Aufbewahrungs-Policy eines Datenbereichs (Restpunkt 66): liefert die
 * fristüberfälligen Datensätze und optional ein Ausnahme-Prädikat
 * (z. B. GoBD-aufbewahrungspflichtig → KEIN Löschvorschlag, mit Begründung).
 */
final class RetentionPolicy {
    /**
     * @param string $area Bereichs-Schlüssel (== config/retention.php areas.*)
     * @param class-string<Model> $modelClass
     * @param Closure $overdueQuery fn(Organization, CarbonImmutable): Builder — überfällige Datensätze
     * @param Closure|null $exempt fn(Model): ?string — Ausnahme-Begründung oder null (= löschbar)
     * @param Closure|null $purge fn(Model, User): void — eigene Löschlogik (Default: $model->delete());
     *                     zweites Argument ist der bestätigende Actor (Feature 130), optional entgegennehmbar
     */
    public function __construct(
        public readonly string $area,
        public readonly string $modelClass,
        public readonly Closure $overdueQuery,
        public readonly ?Closure $exempt = null,
        public readonly ?Closure $purge = null,
    ) {}
}
