<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragsversion (Feature 068, MVP-195): Entwurf/Gegenentwurf/Endstand —
 * append-only, Konditionen verschlüsselt (Personal-Konditionen sind
 * besonders schutzwürdig), optional mit DMS-Dokument + Hash.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $negotiation_id
 * @property int $version
 * @property string $kind
 * @property string|null $summary
 * @property string|null $conditions
 * @property int|null $document_id
 * @property string|null $sha256
 * @property int|null $created_by
 */
#[Hidden(['conditions'])]
class ApplicationContractVersion extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const KINDS = ['draft', 'counter', 'final'];

    protected $fillable = [
        'organization_id', 'negotiation_id', 'version', 'kind', 'summary',
        'conditions', 'document_id', 'sha256', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'conditions' => 'encrypted', // JSON-String, verschlüsselt at rest
    ];

    /** @return BelongsTo<ApplicationContractNegotiation, $this> */
    public function negotiation(): BelongsTo {
        return $this->belongsTo(ApplicationContractNegotiation::class, 'negotiation_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return array<string, mixed> */
    public function conditionsArray(): array {
        $raw = $this->conditions;

        return is_string($raw) && $raw !== '' ? (array) json_decode($raw, true) : [];
    }
}
