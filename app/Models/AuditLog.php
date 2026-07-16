<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Revisionssicheres Änderungsprotokoll (GoBD): SHA-256-Hash-Kette + append-only
 * über {@see HashChained} (geteilt mit {@see OrganizationAuditLog}), prüfbar via
 * `php artisan audit:verify`. Einziger Schreibpfad ist {@see static::create()}
 * (keine rohen Inserts).
 *
 * @phpstan-consistent-constructor
 */
class AuditLog extends Model implements HashChainable {
    use BelongsToOrganization;
    use HashChained;

    protected $fillable = [
        'organization_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'changes',
        'ip',
        'user_agent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'changes' => 'array',
    ];

    /**
     * In den Hash eingehende Nutzdaten. IDs werden null-erhaltend zu int
     * normalisiert, damit Schreib- und Prüfpfad DB-treiberübergreifend gleich hashen.
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'user_id' => $this->nullableInt($this->getAttribute('user_id')),
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'event' => $this->getAttribute('event'),
            'auditable_type' => $this->getAttribute('auditable_type'),
            'auditable_id' => $this->nullableInt($this->getAttribute('auditable_id')),
            // Bewusst getAttribute(): Spalte `changes` kollidiert mit Eloquents interner $changes-Property.
            'changes' => $this->getAttribute('changes'),
            'ip' => $this->getAttribute('ip'),
            'user_agent' => $this->getAttribute('user_agent'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo {
        return $this->morphTo();
    }

    public function eventLabel(): string {
        $key = 'audit-events.' . $this->event;
        $label = __($key);

        return $label === $key ? $this->event : $label;
    }

    public function auditableTypeLabel(): string {
        $type = class_basename($this->auditable_type);
        $key = 'entity-types.' . $type;
        $label = __($key);

        return $label === $key ? $type : $label;
    }
}
