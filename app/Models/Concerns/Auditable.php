<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Auditable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * @method static void created(\Closure $callback)
 * @method static void updated(\Closure $callback)
 * @method static void deleted(\Closure $callback)
 * @method mixed getKey()
 */
trait Auditable {
    /** @var array<int, string> Felder, die nie geloggt werden. */
    protected array $auditExclude = ['password', 'remember_token', 'updated_at'];

    public static function bootAuditable(): void {
        static::created(function (Model $model): void {
            assert($model instanceof self);
            $model->audit('created', $model->getAuditAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            assert($model instanceof self);
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }
            $original = collect($changes)->mapWithKeys(fn($v, $k) => [$k => $model->getOriginal($k)])->all();
            $model->audit('updated', [
                'before' => $model->getAuditAttributes($original),
                'after' => $model->getAuditAttributes($changes),
            ]);
        });

        static::deleted(function (Model $model): void {
            assert($model instanceof self);
            $model->audit('deleted', $model->getAuditAttributes($model->getAttributes()));
        });
    }

    /** @return MorphMany<AuditLog, Model> */
    public function auditLogs(): MorphMany {
        /** @var MorphMany<AuditLog, Model> $relation */
        $relation = $this->morphMany(AuditLog::class, 'auditable');

        return $relation->latest();
    }

    /** @param array<string, mixed> $changes */
    public function audit(string $event, array $changes = []): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $this->resolveAuditOrganizationId($event),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'changes' => $changes ?: null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * Ermittelt die organization_id für den AuditLog-Eintrag.
     * Reihenfolge: eigenes Modell ist Organization → eigene ID (außer bei
     * `deleted`, dann null, da die FK-Referenz sonst ins Leere zeigt);
     * sonst organization_id des Modells; sonst aktive Org aus dem Container;
     * sonst Org des eingeloggten Users.
     */
    protected function resolveAuditOrganizationId(string $event = ''): ?int {
        if ($this instanceof Organization) {
            // Bei `deleted` ist die Org-Zeile bereits weg; ein FK-Insert
            // mit dieser ID würde eine PDOException (FK-Constraint) werfen
            // und den globalen Exception-Handler die DB als "unavailable"
            // markieren lassen. Wir loggen das Delete daher org-übergreifend.
            if ($event === 'deleted') {
                return null;
            }

            return (int) $this->getKey();
        }

        $own = $this->getAttribute('organization_id');
        if (! empty($own)) {
            return (int) $own;
        }

        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return (int) $org->id;
            }
        }

        $authUser = Auth::user();
        if ($authUser instanceof User && ! empty($authUser->organization_id)) {
            return (int) $authUser->organization_id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function getAuditAttributes(array $attributes): array {
        $excluded = array_merge($this->auditExclude, $this->getHidden());

        return collect($attributes)
            ->except($excluded)
            ->all();
    }
}
