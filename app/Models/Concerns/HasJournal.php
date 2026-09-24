<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasJournal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Journal\JournalEntry;
use App\Models\Platform\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Träger eines Journals (MVP-864): `journal()` liest, `record()` schreibt —
 * die Journalklasse nennt `protected static string $journalClass`.
 */
trait HasJournal {
    /** @return HasMany<JournalEntry, $this> */
    public function journal(): HasMany {
        /** @var class-string<JournalEntry> $class */
        $class = static::$journalClass;
        $entry = new $class;

        return $this->hasMany($class, $entry->subject()->getForeignKeyName())
            ->orderBy((string) $class::column('occurred_at'))
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra  Zusatzspalten des Journals
     */
    public function record(string|BackedEnum $event, array $payload = [], User|int|string|null $actor = null, ?CarbonInterface $at = null, array $extra = []): JournalEntry {
        /** @var class-string<JournalEntry> $class */
        $class = static::$journalClass;

        return $class::log($this, $event, $payload, $actor, $at, $extra);
    }
}
