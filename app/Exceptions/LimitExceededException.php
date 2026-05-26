<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LimitExceededException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use Illuminate\Http\{JsonResponse, Request};
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wird vom {@see \App\Services\Licensing\LimitGuard} geworfen, wenn ein
 * Lizenz-Limit (max_users etc.) eine Create-Aktion blockiert. Rendert
 * als HTTP 423 Locked mit klarer deutscher Meldung.
 */
class LimitExceededException extends RuntimeException {
    public function __construct(
        public readonly string $limit,
        public readonly int $current,
        public readonly int $max,
        ?string $message = null,
    ) {
        parent::__construct($message ?? self::defaultMessage($limit, $current, $max));
    }

    public static function defaultMessage(string $limit, int $current, int $max): string {
        return match ($limit) {
            'max_users' => __('Nutzerlimit (:current/:max) der aktuellen Lizenz erreicht. Bitte Lizenz erweitern.', [
                'current' => $current,
                'max' => $max,
            ]),
            'max_orgs' => __('Organisationslimit (:current/:max) der aktuellen Lizenz erreicht. Bitte Lizenz erweitern.', [
                'current' => $current,
                'max' => $max,
            ]),
            'storage_quota_gb' => __('Speicherkontingent erreicht (:current/:max Bytes). Bitte Lizenz erweitern oder Speicher freigeben.', [
                'current' => $current,
                'max' => $max,
            ]),
            default => __('Lizenz-Limit ":limit" (:current/:max) erreicht.', [
                'limit' => $limit,
                'current' => $current,
                'max' => $max,
            ]),
        };
    }

    public function render(Request $request): Response {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'limit_exceeded',
                'limit' => $this->limit,
                'current' => $this->current,
                'max' => $this->max,
                'message' => $this->getMessage(),
            ], Response::HTTP_LOCKED);
        }

        return back()->withErrors([
            'limit' => $this->getMessage(),
        ]);
    }
}
