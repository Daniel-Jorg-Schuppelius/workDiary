<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Processor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\ProcessorRole;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dienstleister/Vertragspartner im Datenschutzregister (Art. 28). Kann je nach
 * Leistung Verantwortlicher, Auftrags- oder Unterauftragsverarbeiter sein.
 *
 * @property int $id
 * @property int $organization_id
 */
class Processor extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_processors';

    protected $fillable = [
        'organization_id',
        'name',
        'role',
        'contact',
        'location',
        'third_country',
        'notes',
        'is_active',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'role' => ProcessorRole::class,
        'third_country' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<ProcessingAgreement, $this> */
    public function agreements(): HasMany {
        return $this->hasMany(ProcessingAgreement::class, 'processor_id')->latest('id');
    }
}
