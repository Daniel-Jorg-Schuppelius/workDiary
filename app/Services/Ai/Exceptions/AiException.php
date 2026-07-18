<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Basisklasse aller KI-Fundament-Fehler (Feature 025, MVP-398/399).
 * Meldungen sind redigiert: nie Prompt-Inhalte, nie Schlüssel.
 */
class AiException extends RuntimeException {
    /**
     * Redigierte, nutzersichtbare Kurzbeschreibung für Health-Tracking und
     * Flash (`last_error`): eigene AiExceptions tragen bereits lokalisierte
     * Meldungen; fremde (Transport-)Fehler werden als technischer Fehler
     * gekennzeichnet statt mit dem Exception-Klassennamen präfixiert.
     */
    public static function describe(Throwable $e): string {
        $message = Str::limit(trim($e->getMessage()), 240, '…');

        if ($e instanceof self && $message !== '') {
            return $message;
        }

        return $message === ''
            ? (string) __('ai.error.unknown')
            : (string) __('ai.error.technical', ['message' => $message]);
    }
}
