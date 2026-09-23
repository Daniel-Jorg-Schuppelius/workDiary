<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSynonymGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Search;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Platform\User;

/**
 * Gleichbedeutende Suchbegriffe einer Organisation (Feature 153, MVP-772):
 * Wer einen Begriff der Gruppe sucht, findet auch die anderen
 * („smtp" ↔ „mailrelay" ↔ „sendeconnector").
 *
 * @property int $id
 * @property int $organization_id
 * @property list<string> $terms
 * @property bool $active
 * @property int|null $created_by_user_id
 */
class SearchSynonymGroup extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const MAX_TERMS = 20;

    public const MAX_TERM_LENGTH = 60;

    protected $fillable = [
        'organization_id',
        'terms',
        'active',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'terms' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Begriffe aus einer Eingabe (Zeilen, Komma oder Semikolon getrennt):
     * Whitespace kollabiert, Dubletten ohne Rücksicht auf Groß-/Kleinschreibung
     * entfernt, Reihenfolge wie eingegeben.
     *
     * @return list<string>
     */
    public static function parseTerms(string $input): array {
        $terms = [];
        foreach (preg_split('/[\r\n,;]+/u', $input) ?: [] as $raw) {
            $term = StringHelper::normalizeWhitespace($raw);
            if ($term === '') {
                continue;
            }
            $terms[StringHelper::toLower($term)] ??= $term;
        }

        return array_values($terms);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
