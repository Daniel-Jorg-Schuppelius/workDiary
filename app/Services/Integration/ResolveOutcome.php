<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveOutcome.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationInboxItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Ergebnis eines {@see IntegrationResolver}-Laufs.
 */
final class ResolveOutcome {
    public const LINKED = 'linked';       // bestehendem lokalen Datensatz zugeordnet
    public const CREATED = 'created';     // neu angelegt (Opt-in)
    public const STAGED = 'staged';       // unmatched-Inbox-Item
    public const CONFLICT = 'conflict';   // conflict-Inbox-Item (Feld-Abweichung)
    public const AMBIGUOUS = 'ambiguous'; // ambiguous-Inbox-Item (mehrere Kandidaten)

    private function __construct(
        public readonly string $type,
        public readonly ?Model $model = null,
        public readonly ?IntegrationInboxItem $inboxItem = null,
    ) {}

    public static function linked(Model $model): self {
        return new self(self::LINKED, model: $model);
    }

    public static function created(Model $model): self {
        return new self(self::CREATED, model: $model);
    }

    public static function staged(IntegrationInboxItem $item): self {
        return new self(self::STAGED, inboxItem: $item);
    }

    public static function conflict(IntegrationInboxItem $item): self {
        return new self(self::CONFLICT, inboxItem: $item);
    }

    public static function ambiguous(IntegrationInboxItem $item): self {
        return new self(self::AMBIGUOUS, inboxItem: $item);
    }

    /** Wurde ein lokaler Datensatz erzeugt oder verknüpft (kein Inbox-Eintrag)? */
    public function isResolved(): bool {
        return in_array($this->type, [self::LINKED, self::CREATED], true);
    }

    public function needsAttention(): bool {
        return ! $this->isResolved();
    }
}
