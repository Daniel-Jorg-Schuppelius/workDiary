<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningContentTranslation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningTranslationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Übersetzter Kursinhalt (Feature 149, MVP-748).
 *
 * **Eine Lesehilfe, kein zweiter Kurs** — dieselbe Kursversion, dieselbe
 * Freigabe, derselbe Nachweis. Maßgeblich bleibt die Ausgangssprache.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $translatable_type
 * @property int $translatable_id
 * @property string $locale
 * @property string $payload
 * @property string $source_hash
 * @property LearningTranslationStatus $status
 * @property string|null $provider
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 */
class LearningContentTranslation extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'translatable_type',
        'translatable_id',
        'locale',
        'payload',
        'source_hash',
        'status',
        'provider',
        'approved_by_user_id',
        'approved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningTranslationStatus::class,
        'approved_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function translatable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Übersetzte Felder.
     *
     * @return array<string, mixed>
     */
    public function fields(): array {
        $decoded = json_decode($this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Nutzbar? Nur freigegeben **und** zum aktuellen Stoffstand — eine
     * Übersetzung des vorletzten Textes wäre schlimmer als keine.
     */
    public function isUsableFor(string $sourceHash): bool {
        return $this->status->isVisibleToLearners() && $this->source_hash === $sourceHash;
    }
}
