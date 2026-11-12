<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternallyLedQueueException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ServiceTicket\ServiceQueue;
use Illuminate\Http\{JsonResponse, Request};
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lokale Ticketanlage in einer extern geführten Queue (MVP-1592): dort legt nur
 * der Import des führenden Systems an. Jeder Anlageweg (Katalog, Portal,
 * Problemmeldung, offener Punkt, Kundeneingang, API) zeigt die Meldung statt
 * eines Serverfehlers.
 */
class ExternallyLedQueueException extends RuntimeException {
    public static function for(ServiceQueue $queue): self {
        return new self((string) __('helpdesk.external.createLocked', ['queue' => $queue->name]));
    }

    public function render(Request $request): Response {
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse(['error' => 'queue_externally_led', 'message' => $this->getMessage()], 422);
        }

        return redirect()->back()->withInput()->with('error', $this->getMessage());
    }
}
