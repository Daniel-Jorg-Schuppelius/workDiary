<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompanyMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\CompanyMappingMode;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Customer, Organization, User};
use App\Services\Reselling\Marketplace\MarketplaceCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gespeicherte Zuordnung einer Marketplace-Firma (Feature 151). Der Abgleich
 * liest sie wie eine Zuordnungsdatei — vor jeder automatischen Erkennung.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $company_key
 * @property string $company_name
 * @property string $normalized_name
 * @property CompanyMappingMode $mode
 * @property int|null $customer_id
 * @property string|null $contact_external_id
 * @property int|null $created_by_user_id
 */
class CompanyMapping extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'reselling_company_mappings';

    protected $fillable = [
        'organization_id',
        'company_key',
        'company_name',
        'normalized_name',
        'mode',
        'customer_id',
        'contact_external_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'mode' => CompanyMappingMode::class,
    ];

    protected static function booted(): void {
        static::saving(static function (self $mapping): void {
            $mapping->normalized_name = MarketplaceCompany::normalizeName($mapping->company_name);
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Ziel im Format der Zuordnungsdatei (`customer:<Sqid>`, `partner:<Sqid>`, UUID).
     */
    public function target(): ?string {
        return match ($this->mode) {
            CompanyMappingMode::Customer => $this->customer instanceof Customer ? 'customer:' . $this->customer->sqid : null,
            CompanyMappingMode::Partner => $this->customer instanceof Customer ? 'partner:' . $this->customer->sqid : null,
            CompanyMappingMode::Contact => $this->contact_external_id !== null && $this->contact_external_id !== '' ? $this->contact_external_id : null,
        };
    }

    /**
     * Alle gespeicherten Zuordnungen einer Organisation als Schlüssel → Ziel,
     * unter Firmen-Schlüssel UND normalisiertem Namen.
     *
     * @return array<string, string>
     */
    public static function targetsFor(Organization|int $organization): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $targets = [];
        $mappings = self::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with('customer')
            ->get();
        foreach ($mappings as $mapping) {
            $target = $mapping->target();
            if ($target === null) {
                continue;
            }
            $targets[$mapping->normalized_name] = $target;
            if ($mapping->company_key !== null && $mapping->company_key !== '') {
                $targets[$mapping->company_key] = $target;
            }
        }

        return $targets;
    }
}
