<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiKeyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\LearningLtiKey;
use Carbon\CarbonImmutable;
use ELearningToolkit\Lti\Keys;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use Jose\Component\Core\JWK;

/**
 * Signaturschlüssel der Instanz für LTI 1.3 (Feature 149).
 *
 * Genau ein Schlüssel signiert. Ein getauschter bleibt noch veröffentlicht, bis
 * kein damit signiertes Token mehr gültig sein kann — sonst scheitern Starts,
 * die kurz vor dem Tausch begonnen haben.
 */
final class LearningLtiKeyService {
    /** So lange bleibt ein zurückgezogener Schlüssel in der JWKS; kein Token lebt länger. */
    public const RETIRED_GRACE_HOURS = 24;

    /** Der signierende Schlüssel; beim ersten Bedarf wird er angelegt. */
    public function active(): LearningLtiKey {
        $key = LearningLtiKey::query()
            ->whereNull('retired_at')
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->first();

        return $key ?? $this->create(CarbonImmutable::now());
    }

    /** Neuer Schlüssel wird aktiv; der bisherige bleibt als zurückgezogen veröffentlicht. */
    public function rotate(?CarbonImmutable $now = null): LearningLtiKey {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($now): LearningLtiKey {
            LearningLtiKey::query()->whereNull('retired_at')->update(['retired_at' => Carbon::instance($now)]);

            return $this->create($now);
        });
    }

    public function isDue(int $days, ?CarbonImmutable $now = null): bool {
        $activatedAt = $this->active()->activated_at;

        return $activatedAt !== null && $activatedAt->lte(($now ?? CarbonImmutable::now())->subDays($days));
    }

    /** @return list<JWK> aktive und noch nicht abgelaufene zurückgezogene Schlüssel */
    public function published(?CarbonImmutable $now = null): array {
        $this->active();
        $cutoff = Carbon::instance(($now ?? CarbonImmutable::now())->subHours(self::RETIRED_GRACE_HOURS));
        $keys = [];

        $records = LearningLtiKey::query()
            ->where(static fn ($query) => $query->whereNull('retired_at')->orWhere('retired_at', '>', $cutoff))
            ->orderByDesc('id')
            ->get();

        foreach ($records as $record) {
            $keys[] = $record->publicKey();
        }

        return $keys;
    }

    /** Zurückgezogene Schlüssel nach der Karenz löschen. */
    public function prune(?CarbonImmutable $now = null): int {
        $cutoff = Carbon::instance(($now ?? CarbonImmutable::now())->subHours(self::RETIRED_GRACE_HOURS));

        return LearningLtiKey::query()->whereNotNull('retired_at')->where('retired_at', '<=', $cutoff)->delete();
    }

    private function create(CarbonImmutable $now): LearningLtiKey {
        $kid = 'wd-' . $now->format('Ymd') . '-' . Str::lower(Str::random(12));
        $jwk = Keys::generate($kid);

        return LearningLtiKey::query()->create([
            'kid' => $kid,
            'public_jwk' => $jwk->toPublic()->all(),
            'private_jwk' => $jwk->all(),
            'activated_at' => Carbon::instance($now),
        ]);
    }
}
