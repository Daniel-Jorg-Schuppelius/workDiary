<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderFilterProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Tenders;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suchprofil einer Organisation: Wonach in Bekanntmachungen gesucht wird.
 *
 * CPV sagt, **was** beschafft wird, NUTS **wo** — beides zusammen trifft die
 * Ausschreibungen, die ein Betrieb überhaupt bedienen kann.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property bool $active
 * @property list<string>|null $cpv_codes
 * @property list<string>|null $nuts_codes
 * @property list<string>|null $keywords
 * @property list<string>|null $excluded_keywords
 * @property string|null $min_value
 * @property string|null $max_value
 */
class TenderFilterProfile extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'tender_filter_profiles';

    protected $fillable = [
        'organization_id', 'name', 'active', 'cpv_codes', 'nuts_codes',
        'keywords', 'excluded_keywords', 'min_value', 'max_value', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'cpv_codes' => 'array',
        'nuts_codes' => 'array',
        'keywords' => 'array',
        'excluded_keywords' => 'array',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
    ];

    /** @return HasMany<TenderNoticeMatch, $this> */
    public function matches(): HasMany {
        return $this->hasMany(TenderNoticeMatch::class, 'tender_filter_profile_id');
    }
}
