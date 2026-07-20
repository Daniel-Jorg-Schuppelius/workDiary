<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Form\FormTemplateStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Formularvorlage (Feature 032, MVP): Name + Felddefinitionen als JSON
 * ({key, label, type, required, options[], help, unit}). Nur aktive
 * Vorlagen sind ausfüllbar; jede Submission friert die Definition als
 * fields_snapshot ein (Versionssicherheit ohne eigene Versionstabelle).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property FormTemplateStatus $status
 * @property list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null}> $fields
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property array{entry_type_id?: int|null, customer_id?: int|null}|null $target
 * @property int $created_by_user_id
 */
class FormTemplate extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<FormTemplateFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'status',
        'fields',
        'valid_from',
        'valid_until',
        'target',
        'created_by_user_id',
    ];

    protected $casts = [
        'status' => FormTemplateStatus::class,
        'fields' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'target' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<FormSubmission, $this> */
    public function submissions(): HasMany {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Aktive (ausfüllbare) Vorlagen.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    /**
     * Aktiv UND am Stichtag gültig (Vollaudit 2026-07, M11): Vorlagen ohne
     * Zeitraum gelten unbefristet.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        $today = now()->toDateString();

        return $query->where('status', FormTemplateStatus::Active->value)
            ->where(fn(Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn(Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }

    /** Passt die Vorlage zur optionalen Zuordnung (M11)? Leere Zuordnung = überall. */
    public function matchesSubject(?int $entryTypeId, ?int $customerId): bool {
        $target = $this->target ?? [];
        $wantedEntryType = $target['entry_type_id'] ?? null;
        $wantedCustomer = $target['customer_id'] ?? null;

        if ($wantedEntryType !== null && (int) $wantedEntryType !== (int) $entryTypeId) {
            return false;
        }
        if ($wantedCustomer !== null && (int) $wantedCustomer !== (int) $customerId) {
            return false;
        }

        return true;
    }
}
