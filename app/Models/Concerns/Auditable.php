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

use App\Models\{AuditLog, Organization, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\{Auth, Request};

/**
 * @method static void created(\Closure $callback)
 * @method static void updated(\Closure $callback)
 * @method static void deleted(\Closure $callback)
 * @method mixed getKey()
 */
trait Auditable {
    /** @var array<int, string> Felder, die nie geloggt werden. */
    protected array $auditExclude = ['password', 'remember_token', 'updated_at'];

    /**
     * Felder, deren **Änderung** protokolliert wird, aber ohne den Wert
     * (Sicherheitsscan 2026-08-23, S-21).
     *
     * Zwischen Ausschluss und Klartext liegt genau das, was ein Protokoll
     * hier leisten soll: dass jemand die IBAN oder die AU-Nummer geändert hat,
     * bleibt nachvollziehbar; der Wert selbst gehört nicht in eine Tabelle,
     * die zehn Jahre aufbewahrt wird und sich wegen der Hash-Kette nicht mehr
     * bereinigen lässt. `null` bleibt `null`, damit „Feld geleert" sichtbar
     * ist.
     *
     * Der Standard leitet sich aus dem Modell selbst ab, statt je Modell
     * gepflegt zu werden: **was at-rest verschlüsselt ist, erscheint nie als
     * Wert im Protokoll.** Sonst stünde der Chiffretext derselben Daten in
     * einer Tabelle, die zehn Jahre steht — mit demselben APP_KEY lesbar und
     * wegen der Hash-Kette nicht mehr zu bereinigen. Dazu kommen Zugangs-
     * Geheimnisse, die kein Cast als solche ausweist (Magic-Token,
     * Kalender-Feed-Token, Lizenzschlüssel).
     *
     * Modelle können die Liste erweitern; das Muster ist eng gefasst, damit
     * fachliche Schlüssel (`match_key`, `sort_key`) nicht mitgeschwärzt
     * werden.
     *
     * Bewusst eine Methode statt einer Eigenschaft: PHP lässt eine
     * Trait-Eigenschaft nicht mit abweichendem Vorgabewert überschreiben.
     *
     * @return array<int, string>
     */
    protected function auditRedact(): array {
        $fields = [];

        foreach ($this->getCasts() as $column => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $fields[] = $column;
            }
        }

        foreach ($this->getFillable() as $column) {
            if (preg_match('/(_token|_secret|api_key|license_key|private_key|_password)$/', $column) === 1) {
                $fields[] = $column;
            }
        }

        return array_values(array_unique($fields));
    }

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
            $model->audit($model->resolveAuditEvent('updated', $changes), [
                'before' => $model->getAuditAttributes($original),
                'after' => $model->getAuditAttributes($changes),
            ]);
        });

        static::deleted(function (Model $model): void {
            assert($model instanceof self);
            $model->audit('deleted', $model->getAuditAttributes($model->getAttributes()));
        });
    }

    /**
     * Hook: Event-Name vor dem Schreiben anpassen. Default: unverändert —
     * Modelle mit archived/restored-Semantik überschreiben auf
     * {@see mapArchivedAtAuditEvent} (opt-in, sonst bleibt `updated`).
     *
     * @param  array<string, mixed>  $changes  rohe getChanges() des Updates
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        return $event;
    }

    /**
     * Mappt `updated` → `archived`/`restored` bei archived_at-Wechsel
     * (Event-Namen revisionsrelevant/GoBD; Logik wie im früheren Observer-Trio).
     *
     * @param  array<string, mixed>  $changes
     */
    protected function mapArchivedAtAuditEvent(string $event, array $changes): string {
        if ($event === 'updated' && array_key_exists('archived_at', $changes)) {
            return $changes['archived_at'] === null ? 'restored' : 'archived';
        }

        return $event;
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
            'user_id' => $this->resolveAuditUserId(),
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
     * user_id für den AuditLog. Bewusst Auth::user() statt Auth::id(): eine
     * veraltete Session (Auth::id() liest nur die Session) würde sonst den FK auf users.id verletzen.
     */
    protected function resolveAuditUserId(): ?int {
        $user = Auth::user();

        return $user instanceof User ? (int) $user->getKey() : null;
    }

    /**
     * organization_id für den AuditLog. Reihenfolge: eigenes Modell/Org →
     * organization_id des Modells → aktive Org (Container) → Org des Users.
     */
    protected function resolveAuditOrganizationId(string $event = ''): ?int {
        if ($this instanceof Organization) {
            // Bei `deleted` ist die Org-Zeile weg; ein FK-Insert würde die DB als "unavailable" markieren lassen.
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
            ->map(fn(mixed $value, string $key): mixed => $value !== null && in_array($key, $this->auditRedact(), true)
                ? \App\Models\AuditLog::REDACTED
                : $value)
            ->all();
    }
}
