<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OutboxTransitionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Statusübergänge einer Outbox (C14) — implementiert von den OutboxServices
 * über {@see \App\Services\Concerns\ManagesOutboxTransitions}; typisiert den
 * Service für den gemeinsamen Delivery-Job.
 *
 * @template TEntry of Model
 */
interface OutboxTransitionService {
    /** @param TEntry $entry */
    public function markProcessing(Model $entry): void;

    /** @param TEntry $entry */
    public function markConfirmed(Model $entry): void;

    /** @param TEntry $entry */
    public function markFailed(Model $entry, string $error): void;

    /** @param TEntry $entry */
    public function markCompensationRequired(Model $entry, string $error): void;
}
