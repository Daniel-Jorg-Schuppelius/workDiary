<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonitoringTokenService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Audit\AuditLog;
use App\Models\Diagnostics\MonitoringToken;
use App\Models\Platform\User;
use App\Support\Auth\OrganizationAccessToken;
use App\Support\MorphMap;
use Illuminate\Support\{Carbon, Str};

/**
 * Zugangstoken des Diagnose-JSON für externe Überwachung (MVP-1618,
 * Konzept Diagnose-Seite §5). Gleiche Regeln wie die öffentlichen Token einer
 * Organisation: nur der SHA-256-Abdruck wird gespeichert, der Klartext
 * erscheint genau einmal, Widerruf wirkt sofort.
 */
class MonitoringTokenService {
    private const int TOKEN_LENGTH = 48;

    /** Abrufe einer Überwachung kommen im Minutentakt; protokolliert wird höchstens einmal je Stunde und Token. */
    private const int AUDIT_INTERVAL_MINUTES = 60;

    /** @return array{token: MonitoringToken, plain: string} */
    public function issue(string $name, User $actor): array {
        $plain = Str::lower(Str::random(self::TOKEN_LENGTH));
        $token = MonitoringToken::query()->create([
            'name' => $name,
            'token_hash' => (string) OrganizationAccessToken::fingerprint($plain),
            'token_hint' => mb_substr($plain, 0, 6),
            'created_by' => $actor->id,
        ]);
        $this->audit('diagnostics.tokenCreated', $token, $actor, ['name' => $name, 'hint' => $token->token_hint]);

        return ['token' => $token, 'plain' => $plain];
    }

    public function revoke(MonitoringToken $token, User $actor): void {
        if (! $token->isActive()) {
            return;
        }
        $token->forceFill(['revoked_at' => Carbon::now()])->save();
        $this->audit('diagnostics.tokenRevoked', $token, $actor, ['name' => $token->name, 'hint' => $token->token_hint]);
    }

    /** Einzige Stelle, die einen Klartext auflöst; widerrufene Token gelten nicht. */
    public function resolve(?string $plain): ?MonitoringToken {
        $fingerprint = OrganizationAccessToken::fingerprint(trim((string) $plain));
        if ($fingerprint === null) {
            return null;
        }

        return MonitoringToken::query()->where('token_hash', $fingerprint)->whereNull('revoked_at')->first();
    }

    /** Merkt den Abruf und protokolliert ihn gedrosselt als `diagnostics.viewed`. */
    public function recordUse(MonitoringToken $token): void {
        $now = Carbon::now();
        $audit = $token->last_audited_at === null || $token->last_audited_at->lt($now->copy()->subMinutes(self::AUDIT_INTERVAL_MINUTES));
        $token->forceFill(['last_used_at' => $now] + ($audit ? ['last_audited_at' => $now] : []))->save();
        if ($audit) {
            $this->audit('diagnostics.viewed', $token, null, ['format' => 'json', 'via' => 'token', 'hint' => $token->token_hint]);
        }
    }

    /** @param  array<string, scalar|null>  $changes */
    private function audit(string $event, MonitoringToken $token, ?User $actor, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => null,
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => MorphMap::stableKey(MonitoringToken::class),
            'auditable_id' => $token->id,
            'changes' => $changes,
        ]);
    }
}
