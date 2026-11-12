<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OwnershipClaim.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Dto;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Schreibversuch einer Quelle in einen geführten Bereich (MVP-1592):
 * Art und Schlüssel bilden den Konfliktfall, `payload` trägt alles, was
 * die Replay-Klasse zum einmaligen Schreiben braucht.
 */
final class OwnershipClaim {
    /**
     * @param  string  $kind  Konfliktart, die eine {@see \App\Services\Integration\Contracts\OwnershipConflictReplay} abspielt
     * @param  string  $key  stabil je Fremdobjekt und Vorgang (z. B. Ticket-ID)
     * @param  class-string<Model>  $target  lokales Zielmodell
     * @param  array<string, mixed>  $payload
     * @param  string|null  $legacyKey  Schlüssel des Falls, den der Schreibweg vor MVP-1592 selbst anlegte
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $key,
        public readonly string $title,
        public readonly string $target,
        public readonly array $payload,
        public readonly ?string $externalId = null,
        public readonly ?Model $local = null,
        public readonly ?string $subtitle = null,
        public readonly ?string $legacyKey = null,
    ) {}
}
