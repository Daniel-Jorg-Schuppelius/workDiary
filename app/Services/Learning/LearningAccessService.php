<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAccessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningAccessToken, LearningEnrollment};
use App\Models\User;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einmal-Zugang für Lernende ohne Benutzerkonto (Feature 149, MVP-742) —
 * einzige Schreibstelle.
 *
 * Regeln:
 *  1. Der **Klartext-Token verlässt diese Methode genau einmal**; gespeichert
 *     wird nur sein Hash (Muster der Portal-Einladung).
 *  2. Ein neuer Link **entwertet den alten** — sonst kursieren mehrere
 *     gültige Zugänge zur selben Einschreibung.
 *  3. Der Zugang gilt nur für **externe** Lernende. Wer ein Konto hat, meldet
 *     sich an; ein Umgehungsweg an der Anmeldung vorbei wäre eine Lücke.
 *  4. Abgelaufen oder widerrufen ⇒ **kein Hinweis darauf, ob es den Token
 *     je gab**. Aufrufer bekommen dieselbe neutrale Antwort.
 */
class LearningAccessService {
    /** Gültigkeitsdauer eines Zugangs in Tagen, wenn nichts anderes gesetzt ist. */
    public const DEFAULT_VALID_DAYS = 30;

    /**
     * Zugang ausstellen. Rückgabe ist der **Klartext-Token** — er lässt sich
     * danach nicht wiederherstellen.
     */
    public function issue(LearningEnrollment $enrollment, ?User $actor = null, ?int $validDays = null, ?Carbon $now = null): string {
        $now ??= Carbon::now();

        if ($enrollment->external_participant_id === null) {
            throw ValidationException::withMessages([
                'enrollment' => (string) __('learning.errors.access_link_internal'),
            ]);
        }

        $token = Str::random(48);

        DB::transaction(function () use ($enrollment, $actor, $validDays, $now, $token): void {
            // Ein neuer Link entwertet den alten.
            LearningAccessToken::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            LearningAccessToken::query()->create([
                'organization_id' => $enrollment->organization_id,
                'learning_enrollment_id' => $enrollment->id,
                'token_hash' => CryptoHelper::hash($token),
                'expires_at' => $now->copy()->addDays($validDays ?? self::DEFAULT_VALID_DAYS),
                'created_by_user_id' => $actor?->id,
            ]);
        });

        return $token;
    }

    /**
     * Token einlösen. Gibt die Einschreibung zurück oder null — **ohne** zu
     * verraten, ob der Token unbekannt, abgelaufen oder widerrufen ist.
     */
    public function resolve(string $token, ?Carbon $now = null): ?LearningEnrollment {
        $now ??= Carbon::now();

        $record = LearningAccessToken::query()
            ->with('enrollment.course')
            ->where('token_hash', CryptoHelper::hash($token))
            ->first();

        if ($record === null || ! $record->isUsable($now)) {
            return null;
        }

        $record->update([
            'first_used_at' => $record->first_used_at ?? $now,
            'last_used_at' => $now,
            'use_count' => $record->use_count + 1,
        ]);

        return $record->enrollment;
    }

    public function revoke(LearningEnrollment $enrollment, ?Carbon $now = null): int {
        return LearningAccessToken::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now ?? Carbon::now()]);
    }
}
