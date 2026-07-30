<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bCatalogAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\B2b;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\{Carbon, Str};

/**
 * Punchout-Zugang eines B2B-Kunden zum eigenen Artikelkatalog (Feature 099,
 * MVP-457). Das OCI-PASSWORD wird ausschließlich als SHA-256-Hash persistiert;
 * der Klartext ist nur einmal bei Ausstellung/Rotation sichtbar — Muster wie
 * {@see \App\Models\ScimToken}. Deaktivierung über `revoked_at`, Rotation
 * ersetzt nur den Hash und erhält die Artikel-Freigaben.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $label
 * @property string $username
 * @property string $secret_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 */
class B2bCatalogAccess extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Der Hash ist zwar nicht umkehrbar, wird aber nie serialisiert/auditiert. */
    protected $hidden = [
        'secret_hash',
    ];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'label',
        'username',
        'secret_hash',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashSecret(string $plain): string {
        return CryptoHelper::hash($plain);
    }

    /**
     * Stellt einen neuen Zugang aus und gibt [Model, Klartext-Secret] zurück.
     * Der Klartext ist danach nicht mehr rekonstruierbar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(int $organizationId, int $customerId, string $label, string $username, ?int $createdBy = null): array {
        $plain = 'b2b_' . Str::random(40);

        $access = static::query()->create([
            'organization_id' => $organizationId,
            'customer_id' => $customerId,
            'label' => $label,
            'username' => $username,
            'secret_hash' => static::hashSecret($plain),
            'created_by' => $createdBy,
        ]);

        return [$access, $plain];
    }

    /** Ersetzt nur das Secret; Freigaben und Kundenbindung bleiben erhalten. */
    public function rotateSecret(): string {
        $plain = 'b2b_' . Str::random(40);
        $this->forceFill(['secret_hash' => static::hashSecret($plain), 'revoked_at' => null])->save();

        return $plain;
    }

    public function isActive(): bool {
        return $this->revoked_at === null;
    }

    /** @param Builder<B2bCatalogAccess> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<B2bCatalogItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(B2bCatalogItem::class, 'access_id');
    }
}
