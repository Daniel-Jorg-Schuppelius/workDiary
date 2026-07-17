<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesOutboxTransitions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gemeinsames Outbox-Skelett (C14): idempotentes Enqueue plus die vier
 * Statusübergänge, die Integration- und Inventory-Stack teilen. Enum-Klasse,
 * Entry-/Job-Klasse und Fehlertext-Normalisierung liefert der jeweilige
 * Service — Kompensationsziele bleiben je Stack fachlich getrennt.
 *
 * @template TEntry of Model
 */
trait ManagesOutboxTransitions {
    /**
     * Status-Enum des Stacks (Cases Pending/Processing/Confirmed/Failed/
     * CompensationRequired, string-backed).
     *
     * @return class-string<\BackedEnum>
     */
    abstract protected function outboxStatusEnum(): string;

    /** Fehlertext vor der Persistenz normalisieren (z. B. kürzen). */
    protected function normalizeOutboxError(string $error): string {
        return $error;
    }

    /**
     * Enqueue-Skelett: firstOrCreate über organization_id+idempotency_key;
     * der Delivery-Job läuft erst nach dem Commit (afterCommit), weil Enqueue
     * aus Business-Transaktionen aufgerufen wird — sonst sieht der Job den
     * Eintrag (Nicht-DB-Queue-Driver) noch nicht.
     *
     * @param class-string<TEntry> $entryClass
     * @param class-string $jobClass
     * @param array<string, mixed> $attributes
     * @return TEntry
     */
    protected function enqueueOutboxEntry(string $entryClass, string $jobClass, int $organizationId, string $idempotencyKey, array $attributes): Model {
        $status = $this->outboxStatusEnum();

        $entry = $entryClass::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'idempotency_key' => $idempotencyKey],
            $attributes + ['status' => $status::Pending->value, 'attempts' => 0],
        );

        if ($entry->wasRecentlyCreated) {
            $jobClass::dispatch($entry->id)->afterCommit();
        }

        return $entry;
    }

    /** @param TEntry $entry */
    public function markProcessing(Model $entry): void {
        $status = $this->outboxStatusEnum();

        $entry->forceFill([
            'status' => $status::Processing,
            'attempts' => $entry->attempts + 1,
        ])->save();
    }

    /** @param TEntry $entry */
    public function markConfirmed(Model $entry): void {
        $status = $this->outboxStatusEnum();

        $entry->forceFill([
            'status' => $status::Confirmed,
            'last_error' => null,
            'confirmed_at' => Carbon::now(),
        ])->save();
    }

    /** @param TEntry $entry */
    public function markFailed(Model $entry, string $error): void {
        $status = $this->outboxStatusEnum();

        $entry->forceFill([
            'status' => $status::Failed,
            'last_error' => $this->normalizeOutboxError($error),
        ])->save();
    }

    /** @param TEntry $entry */
    public function markCompensationRequired(Model $entry, string $error): void {
        $status = $this->outboxStatusEnum();

        $entry->forceFill([
            'status' => $status::CompensationRequired,
            'last_error' => $this->normalizeOutboxError($error),
        ])->save();
    }
}
