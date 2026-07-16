<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, User};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * KI-Gedächtnis-Eintrag (Feature 025, MVP-401). Ebenen-Logik:
 * customer_id gesetzt = Kundenebene (höchster Vorrang), capability
 * gesetzt = verb-/einsatzspezifischer Default (niedrigster Vorrang),
 * beides NULL = Organisationsebene. `origin=learned` entsteht NUR über
 * den bestätigten „Merken?"-Dialog ({@see \App\Services\Ai\AiMemoryService::rememberLearned()})
 * — stilles Lernen gibt es nicht. Einträge sind auditiert und folgen
 * dem Datenlebenszyklus des Kunden (DB-Kaskade).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $customer_id
 * @property string|null $capability
 * @property AiMemoryEntryType $entry_type
 * @property string|null $term
 * @property string $content
 * @property string|null $source_text
 * @property array<string, string>|null $translations
 * @property string $origin
 * @property bool $active
 * @property int $usage_count
 * @property Carbon|null $last_used_at
 */
class AiMemoryEntry extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_LEARNED = 'learned';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'capability',
        'entry_type',
        'term',
        'content',
        'source_text',
        'translations',
        'origin',
        'active',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entry_type' => AiMemoryEntryType::class,
        'translations' => 'array',
        'active' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Vorrang bei Budget-Knappheit: Kunde (0) vor Organisation (1) vor
     * Capability-Default (2) — Feature 025, Gedächtnis-Ebenen.
     */
    public function priorityRank(): int {
        if ($this->customer_id !== null) {
            return 0;
        }

        return $this->capability === null ? 1 : 2;
    }

    /** Grobe Prompt-Kosten des Eintrags in Zeichen (Budget-Trimmung). */
    public function promptWeight(): int {
        return mb_strlen((string) $this->term)
            + mb_strlen($this->content)
            + mb_strlen((string) $this->source_text);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
