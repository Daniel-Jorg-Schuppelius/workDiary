<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleIgnoredLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Lizenzposition im Belegspiegel, die bewusst kein Abo erklärt (Phase 163,
 * MVP-1432): Historie, Partner-Sammelrechnung, Produkt außerhalb des
 * Registers. Ausgeblendet in „Positionen ohne Abo“, jederzeit zurückholbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $linkable_type Morph-Alias der Spiegelposition
 * @property int $linkable_id
 * @property string|null $note
 * @property int|null $created_by
 */
class ResaleIgnoredLine extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'linkable_type',
        'linkable_id',
        'note',
        'created_by',
    ];

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
