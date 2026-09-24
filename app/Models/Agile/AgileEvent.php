<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Prozessereignis (Feature 064): fester Katalog, fachliche
 * Änderung + Event in derselben Transaktion (Service-Schicht). Kein
 * updated_at, Ereignisse werden nie verändert oder gelöscht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $board_id
 * @property int|null $work_item_id
 * @property int|null $sprint_id
 * @property string $event
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $payload
 */
class AgileEvent extends JournalEntry {
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    /** Fester Ereigniskatalog (getestet — nie freie Strings). */
    public const CATALOG = [
        'backlog.added', 'backlog.removed', 'backlog.reranked', 'column.moved',
        'sprint.item_added', 'sprint.item_removed', 'item.blocked',
        'item.unblocked', 'points.changed', 'sprint.started', 'sprint.completed',
        'sprint.cancelled', 'override.wip', 'override.dod', 'override.criteria',
        'epic.assigned',
    ];

    protected $fillable = [
        'organization_id',
        'board_id',
        'work_item_id',
        'sprint_id',
        'event',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    /**
     * Pflicht-Payload-Schlüssel je Eventtyp (P5) — Kennzahlen sind nur
     * reproduzierbar, wenn die Ereignisse vollständig sind. Nicht
     * gelistete Events haben keine Pflichtfelder.
     */
    public const REQUIRED_PAYLOAD = [
        'column.moved' => ['from', 'to'],
        'sprint.item_added' => ['added_after_start'],
        'item.blocked' => ['reason'],
        'points.changed' => ['from', 'to'],
        'override.wip' => ['reason'],
        'override.dod' => ['reason'],
        'override.criteria' => ['reason'],
        'sprint.cancelled' => ['reason'],
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<AgileWorkItem, $this> */
    public function workItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(AgileWorkItem::class, 'work_item_id');
    }

    /**
     * Ereigniskatalog und Pflicht-Payload (Feature 064) — geprüft beim Schreiben über den Journal-Baustein.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function validateEntry(string $event, array $payload): void {
        if (! in_array($event, self::CATALOG, true)) {
            throw new \InvalidArgumentException("Unbekanntes Agile-Ereignis: {$event}");
        }
        foreach (self::REQUIRED_PAYLOAD[$event] ?? [] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new \InvalidArgumentException("Pflicht-Payload '{$key}' fehlt für {$event}.");
            }
        }
    }

    /** @return BelongsTo<AgileBoard, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(AgileBoard::class, 'board_id');
    }
}
