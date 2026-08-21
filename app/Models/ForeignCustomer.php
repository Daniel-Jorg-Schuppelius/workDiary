<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Project\ProjectStatus;
use App\Models\Concerns\{Archivable, Auditable, BelongsToOrganization, HasPartyDisplayLabel, HasPhoneSearchKeys, HasSqid, Searchable};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Fremdkunde (Endkunde): gehört zu einem {@see Customer} (Firma) und bildet
 * dessen eigene Kundschaft ab. Projekte verweisen optional per
 * `foreign_customer_id` auf einen Fremdkunden — so bleibt die Trennung der
 * Endkunden für Auswertung/Abrechnung erhalten, ohne die Kette
 * Customer→Project→TimeEntry→Invoice zu brechen.
 *
 * Bewusst leichtgewichtig: nur Kontaktstammdaten, keine eigenen Sätze/Bankdaten.
 * Die Abrechnung läuft über die Firma. Ein Fremdkunde kann später zu einem
 * vollwertigen {@see Customer} „befördert" werden.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_id
 * @property string $name
 * @property string|null $number
 * @property string|null $matchcode
 * @property string|null $company
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $homepage
 * @property string|null $address
 * @property string|null $country
 * @property string|null $color
 * @property string|null $comment
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 */
class ForeignCustomer extends Model {
    use Archivable;
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasPartyDisplayLabel;
    use HasPhoneSearchKeys;

    use HasSqid;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'name',
        'number',
        'matchcode',
        'company',
        'contact_name',
        'email',
        'phone',
        'mobile',
        'homepage',
        'address',
        'country',
        'color',
        'comment',
        'archived_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'archived_at' => 'datetime',
    ];

    /**
     * Archivieren/Entarchivieren als eigene Audit-Events loggen (GoBD).
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        return $this->mapArchivedAtAuditEvent($event, $changes);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany {
        return $this->hasMany(Project::class)->orderBy('name');
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany {
        return $this->hasMany(Asset::class)->orderBy('name');
    }

    /**
     * Buchungsprojekt des Fremdkunden oder lazy anlegen — Pendant zu
     * {@see Customer::defaultProjectOrCreate()}. `is_default` bleibt dem
     * Kunden-Standardprojekt vorbehalten.
     */
    public function defaultProjectOrCreate(): Project {
        $existing = $this->projects()->first();
        if ($existing instanceof Project) {
            return $existing;
        }

        /** @var Project $project */
        $project = $this->projects()->create([
            'organization_id' => $this->organization_id,
            'customer_id' => $this->customer_id,
            'name' => (string) config('project.default_project.name', 'Wartung'),
            'color' => (string) config('project.default_project.color', '#64748b'),
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);

        return $project;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<ExternalReference, $this> */
    public function externalReferences(): MorphMany {
        return $this->morphMany(ExternalReference::class, 'referenceable');
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'company', 'email'];
    }
}
