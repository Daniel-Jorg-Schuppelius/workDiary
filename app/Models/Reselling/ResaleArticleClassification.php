<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleArticleClassification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Einstufung eines Katalogartikels fürs Reselling-Register (MVP-1025):
 * Abo-Produkt oder nie. Ohne Zeile entscheidet die Namenserkennung. Früher
 * eine Spalte im Artikelstamm und in der Lexoffice-Tabelle — jetzt eine Stelle
 * für jede Katalogquelle.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $article_ref
 * @property ResaleArticleRole $role
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ResaleArticleClassification extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'article_ref',
        'role',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'role' => ResaleArticleRole::class,
    ];

    protected static function booted(): void {
        // Der Klassifizierer cacht Einstufungen je Request/Job.
        static::saved(static fn () => app(LicenseArticleClassifier::class)->flush());
        static::deleted(static fn () => app(LicenseArticleClassifier::class)->flush());
    }
}
