<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
use App\Enums\Project\ProjectStatus;

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
class Customer extends Model {
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasTags;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
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
        'address_lat',
        'address_lng',
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

    /** @var array<string, string> */
    protected $casts = [
        'billable' => 'boolean',
        'archived_at' => 'datetime',
        'hourly_rate' => 'decimal:2',
        'internal_rate' => 'decimal:2',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'contact_persons' => 'array',
    ];

    protected static function booted(): void {
        static::creating(function (self $customer): void {
            if ($customer->number === null || $customer->number === '') {
                $customer->number = self::nextNumberFor($customer->organization_id);
            }
        });

        static::saving(function (self $customer): void {
            if ($customer->slug === null || $customer->slug === '') {
                $customer->slug = self::uniqueSlug(
                    (string) $customer->name,
                    $customer->organization_id,
                    $customer->exists ? $customer->id : null,
                );
            }
        });
    }

    /**
     * Liefert einen Slug, der innerhalb der angegebenen Organisation
     * eindeutig ist (Sentinel "kunde" falls Name keinen Slug ergibt).
     */
    public static function uniqueSlug(string $name, ?int $organizationId, ?int $ignoreId = null): string {
        $base = \Illuminate\Support\Str::slug($name) ?: 'kunde';
        $slug = $base;
        $i = 2;
        while (
            static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
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

    public function defaultProject(): ?Project {
        return $this->projects()->where('is_default', true)->first();
    }

    /**
     * Liefert das Standardprojekt des Kunden oder legt es lazy an.
     * Wird vom CustomerObserver bei `created` aufgerufen und steht auch
     * UI-/Service-seitig als Fallback bereit (z. B. Quick-Stundenzettel).
     */
    public function defaultProjectOrCreate(): Project {
        $existing = $this->defaultProject();
        if ($existing instanceof Project) {
            return $existing;
        }

        /** @var Project $project */
        $project = $this->projects()->create([
            'organization_id' => $this->organization_id,
            'name' => (string) config('project.default_project.name', 'Wartung'),
            'color' => (string) config('project.default_project.color', '#64748b'),
            'status' => ProjectStatus::Active->value,
            'is_default' => true,
            'billable' => (bool) config('project.default_project.billable', true),
            'global_activities' => true,
        ]);

        return $project;
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        return $this->morphMany(ExternalReference::class, 'referenceable');
    }

    public function isArchived(): bool {
        return $this->archived_at !== null;
    }

    public function hasProjects(): bool {
        return $this->projects()->exists();
    }

    public function hasNonDefaultProjects(): bool {
        return $this->projects()->where('is_default', false)->exists();
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
