<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Steuerregel (Phase 23, MVP-238): versionierter Katalog — Land/Region/
 * Kategorie/Satztyp mit Gültigkeit, Quelle und Beleg-Hinweistext. Eine
 * Gesetzesänderung ist Datenpflege, kein Release (P1).
 *
 * ACHTUNG: bewusst OHNE BelongsToOrganization-Scope — der ausgelieferte
 * Katalog (organization_id NULL) gilt für alle Mandanten; Org-Zeilen
 * überschreiben bei der Auflösung (TaxResolver, analog PerDiemRate).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $country
 * @property string|null $region
 * @property string $category
 * @property string $rate_type
 * @property string $rate
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property string|null $source
 * @property string|null $note
 * @property string $status
 */
class TaxRule extends Model {
    use Auditable;
    use HasSqid;

    public const CATEGORIES = ['services', 'goods', 'shipping', 'materials', 'expenses', 'construction', 'media', 'other'];

    public const RATE_TYPES = ['standard', 'reduced', 'zero', 'exempt', 'reverse_charge', 'export'];

    protected $fillable = [
        'organization_id', 'country', 'region', 'category', 'rate_type', 'rate',
        'valid_from', 'valid_to', 'source', 'note', 'status', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
