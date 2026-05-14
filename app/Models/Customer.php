<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $number
 * @property string|null $company
 * @property string|null $vat_id
 * @property string|null $contact_name
 * @property array<int, array{name?: string, email?: string, phone?: string, primary?: bool}>|null $contact_persons
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $fax
 * @property string|null $homepage
 * @property string|null $address
 * @property string|null $address_street
 * @property string|null $address_zip
 * @property string|null $address_city
 * @property string|null $country
 * @property string $currency
 * @property string|null $timezone
 * @property string|null $color
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property string|null $comment
 * @property string|null $invoice_text
 * @property bool $billable
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 */
class Customer extends Model
{
    use BelongsToOrganization;
    use HasAttachments;
    use HasTags;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'number',
        'company',
        'vat_id',
        'contact_name',
        'contact_persons',
        'email',
        'phone',
        'mobile',
        'fax',
        'homepage',
        'address',
        'address_street',
        'address_zip',
        'address_city',
        'country',
        'currency',
        'timezone',
        'color',
        'hourly_rate',
        'internal_rate',
        'comment',
        'invoice_text',
        'billable',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array {
        return [
            'billable' => 'boolean',
            'archived_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'internal_rate' => 'decimal:2',
            'contact_persons' => 'array',
        ];
    }

    protected static function booted(): void {
        static::creating(function (self $customer): void {
            if ($customer->number === null || $customer->number === '') {
                $customer->number = self::nextNumberFor($customer->organization_id);
            }
        });
    }

    /**
     * Berechnet die nächste freie Kundennummer im Schema "K-XXXX" für die Organisation.
     */
    public static function nextNumberFor(?int $organizationId): string {
        $max = self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('number', 'like', 'K-%')
            ->pluck('number')
            ->map(static fn($n) => (int) preg_replace('/\D/', '', (string) $n))
            ->max() ?? 0;

        return sprintf('K-%04d', $max + 1);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany {
        return $this->hasMany(Project::class);
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        /** @var MorphMany<ExternalReference, $this> $relation */
        $relation = $this->morphMany(ExternalReference::class, 'referenceable');
        return $relation;
    }

    public function isArchived(): bool {
        return $this->archived_at !== null;
    }

    public function hasProjects(): bool {
        return $this->projects()->exists();
    }

    /**
     * Liefert den primären Ansprechpartner (oder den ersten in der Liste)
     * gemerged mit den Legacy-Einzelfeldern. Wird vom Lexoffice-Mapper genutzt.
     *
     * @return array{name: ?string, email: ?string, phone: ?string}
     */
    public function primaryContact(): array {
        $persons = $this->contact_persons ?? [];
        $primary = collect($persons)->firstWhere('primary', true) ?? ($persons[0] ?? []);

        return [
            'name' => $primary['name'] ?? $this->contact_name,
            'email' => $primary['email'] ?? $this->email,
            'phone' => $primary['phone'] ?? ($this->phone ?: $this->mobile),
        ];
    }

    /**
     * Suche über Name/Nummer/Firma/E-Mail.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('number', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }

    /**
     * Kunden mit mindestens einem abrechenbaren, noch nicht exportierten Zeiteintrag.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithUnexportedBillable(Builder $query): Builder {
        return $query->whereHas('projects.timeEntries', function (Builder $q): void {
            $q->where('billable', true)->where('exported', false);
        });
    }
}
