<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredential.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\{Supplier, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hinterlegter Pflichtnachweis eines Lieferanten (Feature 117, MVP-606).
 *
 * Das Dokument hängt als Anhang — ohne die Urkunde ist der Nachweis im
 * Prüfungsfall wertlos.
 *
 * @property Carbon|null $valid_until
 */
class SupplierCredential extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'supplier_credential_type_id',
        'issuer',
        'reference',
        'issued_on',
        'valid_until',
        'checked_by',
        'checked_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_on' => 'date',
        'valid_until' => 'date',
        'checked_at' => 'date',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<SupplierCredentialType, $this> */
    public function type(): BelongsTo {
        return $this->belongsTo(SupplierCredentialType::class, 'supplier_credential_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checkedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** Unbefristete Nachweise laufen nie ab. */
    public function isExpired(): bool {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
