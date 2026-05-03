<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
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

    /** @return MorphMany<AuditLog, \Illuminate\Database\Eloquent\Model> */
    public function auditLogs(): MorphMany {
        /** @var MorphMany<AuditLog, \Illuminate\Database\Eloquent\Model> $relation */
        $relation = $this->morphMany(AuditLog::class, 'auditable');
        return $relation->latest();
    }

    /** @param array<string, mixed> $changes */
    public function audit(string $event, array $changes = []): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'changes' => $changes ?: null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function getAuditAttributes(array $attributes): array {
        $excluded = array_merge($this->auditExclude, $this->getHidden());

        return collect($attributes)
            ->except($excluded)
            ->all();
    }
}
