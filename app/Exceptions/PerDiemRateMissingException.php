<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateMissingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Formats;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Für Land/Datum der Reise ist kein Verpflegungssatz hinterlegt (z. B. eine
 * nachgetragene Reise vor dem ältesten Satz). Fachliche Lücke, kein
 * Serverfehler: rendert als Feldfehler am Reisebeginn (UI-Fuzz 2026-09-21).
 */
class PerDiemRateMissingException extends RuntimeException {
    public function __construct(
        public readonly string $country,
        public readonly CarbonImmutable $date,
        public readonly ?string $region = null,
    ) {
        parent::__construct((string) __('Kein Verpflegungssatz für :country am :date hinterlegt.', [
            'country' => $country . ($region !== null ? ' (' . $region . ')' : ''),
            'date' => $date->format(Formats::date()),
        ]));
    }

    public function render(Request $request): Response {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $this->getMessage(), 'errors' => ['started_at' => [$this->getMessage()]]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return back()->withInput()->withErrors(['started_at' => $this->getMessage()]);
    }
}
