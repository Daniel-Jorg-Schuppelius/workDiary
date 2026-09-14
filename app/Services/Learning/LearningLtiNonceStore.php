<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiNonceStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\LearningLtiNonce;
use CommonToolkit\Helper\Data\CryptoHelper;
use DateTimeImmutable;
use ELearningToolkit\Lti\NonceStore;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Nonce-Ablage für LTI 1.3 (Feature 149). Der eindeutige Abdruck macht „schon
 * verbraucht" zur Datenbankregel — auch zwei gleichzeitige Starts bekommen nie
 * beide ein `true`.
 */
final class LearningLtiNonceStore implements NonceStore {
    public function consume(string $nonce, DateTimeImmutable $expiresAt): bool {
        if ($nonce === '') {
            return false;
        }

        try {
            LearningLtiNonce::query()->create([
                'nonce_hash' => CryptoHelper::hash($nonce),
                'expires_at' => Carbon::instance($expiresAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function prune(?Carbon $now = null): int {
        return LearningLtiNonce::query()->where('expires_at', '<', $now ?? Carbon::now())->delete();
    }
}
