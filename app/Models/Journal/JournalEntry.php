<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JournalEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Journal;

use App\Enums\Contracts\HasLabel;
use App\Models\Concerns\AppendOnly;
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Journal-Baustein (MVP-864): append-only Ereigniszeile eines Trägers.
 *
 * Vertrag: `event`, `actor_user_id`, `payload`, `occurred_at` — physisch dürfen
 * die Spalten anders heißen (`metadata`, `meta`, `event_type`, `user_id`,
 * `created_at`), das Journal nennt die Abweichung in {@see $journalColumns};
 * das Schema bleibt, Hash-Ketten hashen unverändert ihre eigene Kanonik.
 * Geschrieben wird nur über {@see log()} bzw. `HasJournal::record()` auf dem
 * Träger (Gate `JournalContractRuleTest`).
 *
 * @phpstan-consistent-constructor
 */
abstract class JournalEntry extends Model {
    use AppendOnly;

    /**
     * Abweichende Spaltennamen des Vertrags; `null` = Spalte gibt es nicht
     * (z. B. kein Akteur bei externen Teilnehmern).
     *
     * @var array<string, string|null>
     */
    protected static array $journalColumns = [];

    /** Bestehender Übersetzungspräfix der Ereignisse (z. B. `finance.event`), bevor `journal.<modul>` greift. */
    protected static ?string $labelPrefix = null;

    /** @var array<class-string, string|null> Modulcode je Journalklasse */
    private static array $moduleCodes = [];

    /**
     * Träger des Journals.
     *
     * @return BelongsTo<covariant Model, $this>
     */
    abstract public function subject(): BelongsTo;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, static::column('actor_user_id') ?? 'actor_user_id');
    }

    /** Physischer Spaltenname eines Vertragsfelds, null = nicht vorhanden. */
    public static function column(string $field): ?string {
        return array_key_exists($field, static::$journalColumns) ? static::$journalColumns[$field] : ($field === 'occurred_at' ? 'created_at' : $field);
    }

    public function eventKey(): string {
        $event = $this->getAttribute((string) static::column('event'));

        return $event instanceof BackedEnum ? (string) $event->value : (string) $event;
    }

    public function occurredAt(): ?CarbonInterface {
        $value = $this->getAttribute((string) static::column('occurred_at'));

        return $value instanceof CarbonInterface ? $value : null;
    }

    /** @return array<string, mixed> */
    public function payloadData(): array {
        $column = static::column('payload');
        $value = $column !== null ? $this->getAttribute($column) : null;

        return is_array($value) ? $value : [];
    }

    /** Anzeigename des Ereignisses: Enum-Label, `journal.<modul>.<event>`, bestehender Präfix, sonst lesbarer Schlüssel. */
    public function label(): string {
        $event = $this->getAttribute((string) static::column('event'));
        if ($event instanceof HasLabel) {
            return $event->label();
        }
        $key = $this->eventKey();
        $candidates = [];
        $module = static::moduleCode();
        if ($module !== null) {
            $candidates[] = "journal.{$module}.{$key}";
        }
        if (static::$labelPrefix !== null) {
            $candidates[] = static::$labelPrefix . '.' . $key;
        }
        foreach ($candidates as $candidate) {
            if (Lang::has($candidate)) {
                return (string) __($candidate);
            }
        }

        return Str::headline(str_replace(['.', ':'], ' ', $key));
    }

    /**
     * Zentrale Schreibstelle aller Journale.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra  Zusatzspalten des Journals (from/to_status, actor_type, ip, …)
     */
    public static function log(?Model $subject, string|BackedEnum $event, array $payload = [], User|int|string|null $actor = null, ?CarbonInterface $at = null, array $extra = []): static {
        $entry = new static;
        $eventKey = $event instanceof BackedEnum ? (string) $event->value : $event;
        static::validateEntry($eventKey, $payload);

        $attributes = $extra;
        if ($subject !== null) {
            $attributes[$entry->subject()->getForeignKeyName()] = $subject->getKey();
            if ($entry->isFillable('organization_id') && ! array_key_exists('organization_id', $attributes) && $subject->getAttribute('organization_id') !== null) {
                $attributes['organization_id'] = $subject->getAttribute('organization_id');
            }
        }
        $attributes[(string) static::column('event')] = $eventKey;
        $actorColumn = static::column('actor_user_id');
        if ($actorColumn !== null && ! array_key_exists($actorColumn, $attributes)) {
            $attributes[$actorColumn] = $actor instanceof User ? $actor->getKey() : ($actor === null ? null : (int) $actor);
        }
        $payloadColumn = static::column('payload');
        if ($payloadColumn !== null) {
            $attributes[$payloadColumn] = $payload === [] ? null : $payload;
        }
        $attributes[(string) static::column('occurred_at')] = $at ?? now();

        return static::query()->create($attributes);
    }

    /**
     * Hook für Journale mit Ereigniskatalog/Pflicht-Payload (Agile); Standard: alles erlaubt.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function validateEntry(string $event, array $payload): void {}

    public static function moduleCode(): ?string {
        return self::$moduleCodes[static::class] ??= app(ModuleRegistry::class)->byTable((new static)->getTable())?->code();
    }
}
