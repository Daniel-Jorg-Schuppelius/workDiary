<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityUrl.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\{Asset, DiaryEntry, ManufacturingOrder, Protocol, SafetyEvent};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Learning\LearningEnrollment;
use App\Models\Project\Project;
use App\Models\Sales\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Detailseite eines Trägers (MVP-818). Zuvor lag dieselbe Abbildung dreifach
 * vor — in `NotificationLinks`, im `SearchResultLinker` und privat im
 * `ProcedureRunController`, jede mit eigener Typliste. Diese Stelle führt sie
 * zusammen; Träger ohne eigene Seite liefern `null`.
 *
 * Fehlt die Route (abgeschaltetes Modul, Konsolenkontext ohne Request), ist das
 * Ergebnis ebenfalls `null` statt einer Ausnahme — ein Link ist Beiwerk, kein
 * Grund, eine Liste scheitern zu lassen.
 */
final class EntityUrl {
    public static function for(?Model $model): ?string {
        if ($model === null) {
            return null;
        }

        return self::route($model::class, (int) $model->getKey());
    }

    /** Wie {@see for()}, aber aus Morph-Typ und Schlüssel — ohne den Träger zu laden. */
    public static function byType(?string $type, ?int $id): ?string {
        if ($type === null || $id === null) {
            return null;
        }

        return self::route(Relation::getMorphedModel($type) ?? $type, $id);
    }

    /** @param class-string|string $class */
    private static function route(string $class, int $id): ?string {
        try {
            return match ($class) {
                DiaryEntry::class => route('diary.show', Sqid::encode(DiaryEntry::class, $id)),
                Customer::class => route('customers.show', Sqid::encode(Customer::class, $id)),
                ForeignCustomer::class => route('foreign-customers.show', Sqid::encode(ForeignCustomer::class, $id)),
                Project::class => route('projects.show', Sqid::encode(Project::class, $id)),
                Asset::class => route('assets.show', Sqid::encode(Asset::class, $id)),
                SafetyEvent::class => route('safety-events.show', Sqid::encode(SafetyEvent::class, $id)),
                Protocol::class => route('protocols.show', Sqid::encode(Protocol::class, $id)),
                Lead::class => route('leads.show', Sqid::encode(Lead::class, $id)),
                LearningEnrollment::class => route('learning.my.show', Sqid::encode(LearningEnrollment::class, $id)),
                ManufacturingOrder::class => route('manufacturing-orders.show', Sqid::encode(ManufacturingOrder::class, $id)),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
