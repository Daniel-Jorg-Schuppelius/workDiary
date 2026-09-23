<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditRedaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachweis einer Schwärzung im Audit-Protokoll (Sicherheitsscan 2026-08-23,
 * S-21) — siehe {@see \App\Services\Audit\AuditRedactionService}.
 *
 * Selbst hash-verkettet und append-only: der Eingriff in die eine Kette wird
 * in einer zweiten festgehalten, die sich nicht stillschweigend bereinigen
 * lässt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $chain
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<int, string> $fields
 * @property int $rows_affected
 * @property int $first_audit_log_id
 * @property int $last_audit_log_id
 * @property string $reason
 * @property string|null $request_reference
 * @property int|null $performed_by
 * @property string|null $head_before
 * @property string|null $head_after
 *
 * @phpstan-consistent-constructor
 */
class AuditRedaction extends Model implements HashChainable {
    use BelongsToOrganization;
    use HashChained;

    protected $fillable = [
        'organization_id',
        'chain',
        'auditable_type',
        'auditable_id',
        'fields',
        'rows_affected',
        'first_audit_log_id',
        'last_audit_log_id',
        'reason',
        'request_reference',
        'performed_by',
        'head_before',
        'head_after',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fields' => 'array',
        'rows_affected' => 'integer',
        'first_audit_log_id' => 'integer',
        'last_audit_log_id' => 'integer',
    ];

    /**
     * Nutzdaten der Kette. `head_before`/`head_after` gehören dazu: sie sind
     * der eigentliche Nachweis, welchen Zustand die geschwärzte Kette vor und
     * nach dem Eingriff hatte.
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->getAttribute('organization_id') === null ? null : (int) $this->getAttribute('organization_id'),
            'chain' => $this->getAttribute('chain'),
            'auditable_type' => $this->getAttribute('auditable_type'),
            'auditable_id' => (int) $this->getAttribute('auditable_id'),
            'fields' => $this->getAttributes()['fields'] ?? null,
            'rows_affected' => (int) $this->getAttribute('rows_affected'),
            'first_audit_log_id' => (int) $this->getAttribute('first_audit_log_id'),
            'last_audit_log_id' => (int) $this->getAttribute('last_audit_log_id'),
            'reason' => $this->getAttribute('reason'),
            'request_reference' => $this->getAttribute('request_reference'),
            'performed_by' => $this->getAttribute('performed_by') === null ? null : (int) $this->getAttribute('performed_by'),
            'head_before' => $this->getAttribute('head_before'),
            'head_after' => $this->getAttribute('head_after'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
