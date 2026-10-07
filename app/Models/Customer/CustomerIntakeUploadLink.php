<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeUploadLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Upload-Link eines Kundeneingangs bei einem externen Kanal (MVP-1078):
 * Ordner und Freigabe beim Anbieter, verschlüsselte URL und Passwort,
 * Stand der letzten Abholung. Nicht auditiert, damit URL und Passwort nie
 * im Audit-Log landen — der Verlauf steht im Journal des Eingangs.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_intake_id
 * @property string $channel
 * @property string|null $external_id
 * @property string $folder
 * @property string $url
 * @property string|null $password
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property list<string>|null $processed_keys
 * @property int|null $created_by
 */
class CustomerIntakeUploadLink extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'customer_intake_id', 'channel', 'external_id', 'folder', 'url', 'password',
        'expires_at', 'revoked_at', 'last_synced_at', 'last_error', 'last_error_at', 'processed_keys', 'created_by',
    ];

    protected $hidden = ['url', 'password'];

    /** @var array<string, string> */
    protected $casts = [
        'url' => 'encrypted',
        'password' => 'encrypted',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'processed_keys' => 'array',
    ];

    /** @return BelongsTo<CustomerIntake, $this> */
    public function intake(): BelongsTo {
        return $this->belongsTo(CustomerIntake::class, 'customer_intake_id');
    }

    /** Nutzbar: nicht widerrufen und nicht abgelaufen. */
    public function isActive(): bool {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
