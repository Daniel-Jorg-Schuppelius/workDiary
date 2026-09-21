<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Nutzertext einer abgefangenen Exception (MVP-827): Fachtexte aus eigenem Code
 * bleiben, technische Texte aus vendor/ (Transport, SDK, SQL) erscheinen nur als
 * Verweis auf das Protokoll mit Request-ID — sie verrieten interne Hosts/Proxys
 * und halfen Nutzern nicht. Das Detail wird hier als WARNING protokolliert.
 */
final class ErrorText {
    public static function for(Throwable $e): string {
        if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
            return $e->getMessage();
        }

        $previous = $e->getPrevious();
        if (self::isOwn($e) && ($previous === null || self::isOwn($previous))) {
            return $e->getMessage();
        }

        Log::warning('Technischer Fehler in einer Nutzeraktion', ['exception' => $e]);
        $hint = (string) __('Technischer Fehler — Details im Protokoll (Request-ID: :rid).', ['rid' => self::requestId()]);

        // Eigene Hülle um einen Fremdfehler: fachlichen Rahmen behalten, nur den Fremdtext ersetzen.
        if (self::isOwn($e)) {
            $inner = $previous !== null ? $previous->getMessage() : '';

            return $inner !== '' ? str_replace($inner, $hint, $e->getMessage()) : $e->getMessage();
        }

        return $hint;
    }

    private static function isOwn(Throwable $e): bool {
        return str_starts_with($e->getFile(), app_path() . DIRECTORY_SEPARATOR);
    }

    private static function requestId(): string {
        return app()->bound(AssignRequestId::CONTAINER_KEY) ? (string) app(AssignRequestId::CONTAINER_KEY) : '—';
    }
}
